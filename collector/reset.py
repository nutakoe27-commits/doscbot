"""Reset owned collector data, with an explicit option to discard old storage."""

from contextlib import closing
from datetime import datetime,timezone
import fcntl
import json
import os
from pathlib import Path
import shutil
import sqlite3
import stat
from uuid import uuid4

from .audit import audit
from .monitor_data import readonly
from .profiles import DEFAULT_SPORTS
from .store import Store


ENTRIES = ('dataset.sqlite3', 'dataset.sqlite3-wal', 'dataset.sqlite3-shm', 'source')


def owned_backups(state):
    """Identify only this collector's completed resets; leave unknown files alone."""
    root = state/'backups'
    if root.is_symlink():
        raise ValueError('Backup directory must be inside collector state')
    if not root.exists():
        return [], []
    owned, retained = [], []
    for path in sorted(root.iterdir()):
        try:
            if path.is_symlink() or not path.is_dir():
                raise ValueError('not_owned_directory')
            if any(item.is_symlink() for item in path.iterdir()):
                raise ValueError('redirected_backup_entry')
            manifest = path/'manifest.json'
            if manifest.is_symlink() or not manifest.is_file() or manifest.stat().st_size > 1024*1024:
                raise ValueError('reset_manifest_missing_or_redirected')
            value = json.loads(manifest.read_text(encoding='utf-8'))
            if (not isinstance(value, dict) or value.get('reset') is not True or
                    value.get('state_dir') != str(state) or value.get('backup') != str(path) or
                    value.get('move') != list(ENTRIES)):
                raise ValueError('reset_manifest_mismatch')
            if {item.name for item in path.iterdir()} - set(ENTRIES) - {'manifest.json'}:
                raise ValueError('unexpected_backup_files')
            dataset = path/'dataset.sqlite3'
            if dataset.is_symlink() or not dataset.is_file() or (path/'source').is_symlink():
                raise ValueError('backup_database_missing_or_redirected')
            with closing(readonly(dataset)) as db:
                owner = db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
                archive = db.execute("SELECT value FROM metadata WHERE key='source_archive'").fetchone()
                if not owner or owner[0] != 'doscbot.fresh-collector.v1':
                    raise ValueError('foreign_backup_database')
                if (path/'source').exists() and (not archive or archive[0] != str(state/'source/winline.sqlite3')):
                    raise ValueError('backup_source_ownership_unconfirmed')
            owned.append(path)
        except (OSError, ValueError, sqlite3.Error) as error:
            retained.append({'path':str(path), 'reason':'not_verified_collector_backup', 'error_type':type(error).__name__})
    return owned, retained


def reset_data(state, finding, revision, *, apply=False,sports=DEFAULT_SPORTS,discard_old_data=False):
    state,finding=Path(state).resolve(),Path(finding).resolve()
    if state==finding or finding in state.parents or state in finding.parents:
        raise ValueError('Reset cannot include finding')
    dataset=state/'dataset.sqlite3'
    if not dataset.is_file() or dataset.is_symlink():
        raise ValueError('An owned collector dataset is required')
    if any((state/entry).is_symlink() for entry in ENTRIES):
        raise ValueError('Refusing redirected collector data')
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
    discard, retained = owned_backups(state) if discard_old_data else ([], [])
    plan={'state_dir':str(state),'apply':apply,'ready':evidence['ready_to_reset'],
          'move':list(ENTRIES), 'discard_old_data':discard_old_data,
          'discard_backups':[str(path) for path in discard], 'retained_backups':retained,
          'discarded_backups':[], 'discard_errors':[], 'old_data_discarded':False,
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
        # Commit initialization before any irreversible deletion. Cleanup
        # failures must never restore a partly deleted archive as live data.
        if discard_old_data:
            plan['free_bytes_before_discard'] = shutil.disk_usage(state).free
            discard, plan['retained_backups'] = owned_backups(state)
            if backup not in discard:
                plan['discard_errors'].append({'path':str(backup), 'type':'BackupValidationError'})
            else:
                for path in discard:
                    try:
                        shutil.rmtree(path)
                        plan['discarded_backups'].append(str(path))
                    except OSError as error:
                        plan['discard_errors'].append({'path':str(path), 'type':type(error).__name__})
            if not backup.exists():
                plan['backup'] = None
            plan['old_data_discarded'] = not plan['discard_errors'] and plan['backup'] is None
            plan['free_bytes_after_discard'] = shutil.disk_usage(state).free
    return plan
