"""Create a consistent, standalone SQLite copy for an offline training host."""

from contextlib import closing
import hashlib
import json
from pathlib import Path
import shutil
import sqlite3

from .monitor_data import readonly
from .store import now


def training_snapshot(state, finding, output):
    state,finding,output=Path(state).resolve(),Path(finding).resolve(),Path(output).resolve()
    if state==finding or finding in state.parents or state in finding.parents:
        raise ValueError('Only snapshot the new collector database outside finding')
    if output==finding or finding in output.parents or output==state or state in output.parents:
        raise ValueError('Snapshot output must be outside finding and collector data')
    source=state/'dataset.sqlite3'
    if source.is_symlink() or any(Path(str(source)+suffix).is_symlink() for suffix in ('-wal','-shm','-journal')):
        raise ValueError('Refusing redirected collector database files')
    if not source.is_file():
        raise ValueError('Collector database is missing')
    if output.exists():
        raise ValueError('Snapshot directory already exists; choose a new name')
    started=now()
    with closing(readonly(source)) as db:
        if not db.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name='metadata'").fetchone():
            raise ValueError('Only the new collector database is allowed')
        owner=db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
        if not owner or owner[0]!='doscbot.fresh-collector.v1':
            raise ValueError('Only the new collector database is allowed')
        # The existing read transaction pins one snapshot, including committed
        # WAL pages; copying the live main file alone would lose those pages.
        counts={table:db.execute(f'SELECT count(*) FROM {table}').fetchone()[0]
                for table in ('samples','observations','runs')}
        output.mkdir(parents=True,exist_ok=False)
        try:
            destination=output/'dataset.sqlite3'
            with closing(sqlite3.connect(destination)) as target:
                db.backup(target,pages=4096,sleep=.05)
                target.execute('PRAGMA journal_mode=DELETE')
                if target.execute('PRAGMA quick_check').fetchone()[0]!='ok':
                    raise ValueError('Snapshot integrity check failed')
            checksum=hashlib.sha256()
            with destination.open('rb') as handle:
                for chunk in iter(lambda:handle.read(1024*1024),b''):checksum.update(chunk)
            manifest={'format':'doscbot.training-snapshot.v1','started_at':started,'finished_at':now(),
                      'source_database':str(source),'database':'dataset.sqlite3','bytes':destination.stat().st_size,
                      'sha256':checksum.hexdigest(),'counts':counts,'standalone':True,
                      'scope':'Offline copy of owned collector dataset; source database and collection are unchanged'}
            (output/'SHA256SUMS').write_text(checksum.hexdigest()+'  dataset.sqlite3\n',encoding='ascii')
            (output/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
        except BaseException:
            shutil.rmtree(output)
            raise
    return manifest
