"""Rotate only the owned collector databases after successful collection audit."""

from contextlib import closing
from datetime import datetime,timezone
import fcntl
import json
import os
from pathlib import Path
import stat
from uuid import uuid4

from .audit import audit
from .monitor_data import readonly
from .profiles import DEFAULT_SPORTS
from .store import Store


def reset_data(state, finding, revision, *, apply=False,sports=DEFAULT_SPORTS):
    state,finding=Path(state).resolve(),Path(finding).resolve()
    if state==finding or finding in state.parents or state in finding.parents:
        raise ValueError('Reset cannot include finding')
    dataset=state/'dataset.sqlite3'
    if not dataset.is_file() or dataset.is_symlink():
        raise ValueError('An owned collector dataset is required')
    for file in state.rglob('*.sqlite3'):
        if file.is_symlink():
            raise ValueError('Refusing redirected database files')
    with closing(readonly(dataset)) as db:
        owner=db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
        archive=db.execute("SELECT value FROM metadata WHERE key='source_archive'").fetchone()
        if not owner or owner[0]!='doscbot.fresh-collector.v1':
            raise ValueError('Refusing a foreign database')
        if (state/'source').exists() and (not archive or archive[0]!=str(state/'source/winline.sqlite3')):
            raise ValueError('Source archive ownership is not confirmed')
    if (state/'source').is_symlink():
        raise ValueError('Refusing redirected source directory')
    evidence=audit(state,sports)
    plan={'state_dir':str(state),'apply':apply,'ready':evidence['ready_to_reset'],
          'move':['dataset.sqlite3','dataset.sqlite3-wal','dataset.sqlite3-shm','source'],
          'audit':evidence,'backup':None,'reset':False}
    if not apply:
        return plan
    if not evidence['ready_to_reset']:
        raise ValueError('Reset refused: obtain a qualified rich capture and v2 input for every requested sport, then run audit')
    ownership=dataset.stat()
    if os.geteuid()!=0 and ownership.st_uid!=os.geteuid():
        raise ValueError('Run reset as the collector owner or root')
    with (state/'collector.lock').open('a+') as lock:
        try:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError:
            raise ValueError('Stop doscbot-collector.service before resetting') from None
        # Retain the stable lock while moving the DB+WAL+SHM bundle and source.
        # A collector trying to start cannot enter its collection loop.
        evidence=audit(state,sports)
        if not evidence['ready_to_reset']:
            raise ValueError('Collection evidence changed; reset refused')
        root=state/'backups'
        root.mkdir(mode=0o700,exist_ok=True)
        if root.is_symlink():
            raise ValueError('Backup directory must be inside collector state')
        name=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'-'+uuid4().hex[:8]
        backup=root/name
        backup.mkdir(mode=0o700)
        moved=[]
        try:
            for entry in plan['move']:
                source=state/entry
                if source.exists():
                    source.rename(backup/entry)
                    moved.append(entry)
            Store(dataset,revision)
            if os.geteuid()==0:
                for file in [root,backup,state/'collector.lock',*state.glob('dataset.sqlite3*')]:
                    os.chown(file,ownership.st_uid,ownership.st_gid)
            os.chmod(dataset,stat.S_IMODE(ownership.st_mode))
            plan.update(backup=str(backup),reset=True,audit=evidence)
            with (backup/'manifest.json').open('x',encoding='utf-8') as output:
                json.dump(plan,output,ensure_ascii=False,indent=2)
                output.flush();os.fsync(output.fileno())
        except BaseException:
            # Preserve even an incomplete new DB, then restore all original
            # files. Never unlink original or user-owned databases.
            failed=backup/'failed-initialization'
            failed.mkdir(exist_ok=True)
            for entry in plan['move']:
                new=state/entry
                if new.exists():
                    new.rename(failed/entry)
            for entry in moved:
                (backup/entry).rename(state/entry)
            raise
    return plan
