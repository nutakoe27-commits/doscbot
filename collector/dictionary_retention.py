"""Opt-in compaction of repeated dictionaries, never match evidence or samples."""
from contextlib import closing
import fcntl
import hashlib
import json
from pathlib import Path
import shutil
import sqlite3

from .monitor_data import readonly
from .snapshot import training_snapshot
from .store import digest

KINDS = ('sports', 'countries', 'championships')
POLICY = 'latest-dictionaries.v1'
PLACEHOLDERS = ','.join('?' for _ in KINDS)


def enabled(db):
    row = db.execute("SELECT value FROM metadata WHERE key='dictionary_retention'").fetchone()
    return bool(row and row[0] == POLICY)


def observe_dictionary(store, db, kind, entity, at, payload, *, retain=False):
    if kind not in KINDS:
        raise ValueError('Only reference dictionaries may be compacted')
    if not retain:
        return store.observe(db, kind, entity, at, payload)
    previous = db.execute('SELECT id,sha256 FROM observations WHERE kind=? AND entity_id=? ORDER BY id DESC LIMIT 1',
                          (kind, str(entity))).fetchone()
    if previous and previous['sha256'] == digest(payload):
        return 0
    inserted = store.observe(db, kind, entity, at, payload)
    if inserted:
        db.execute('DELETE FROM observations WHERE kind=? AND entity_id=? AND id NOT IN '
                   '(SELECT max(id) FROM observations WHERE kind=? AND entity_id=?)',
                   (kind, str(entity), kind, str(entity)))
    return inserted


def verify_owner(db):
    row = db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
    if not row or row[0] != 'doscbot.fresh-collector.v1':
        raise ValueError('Only the owned collector database is allowed')


def plan(db):
    verify_owner(db)
    kinds = []
    for kind in KINDS:
        count, size = db.execute('SELECT count(*),coalesce(sum(length(CAST(payload AS BLOB))),0) '
                                 'FROM observations WHERE kind=?', (kind,)).fetchone()
        kept, kept_size = db.execute('SELECT count(*),coalesce(sum(length(CAST(payload AS BLOB))),0) '
            'FROM observations WHERE id IN (SELECT max(id) FROM observations WHERE kind=? GROUP BY entity_id)',
            (kind,)).fetchone()
        kinds.append({'kind':kind, 'before':count, 'keep':kept, 'remove':count-kept,
                      'payload_bytes_removed':size-kept_size})
    return {'format':'doscbot.dictionary-compaction.v1', 'policy':POLICY, 'state':'preview',
            'retention_enabled':enabled(db), 'kinds':kinds,
            'remove_records':sum(k['remove'] for k in kinds),
            'payload_bytes_removed':sum(k['payload_bytes_removed'] for k in kinds),
            'samples':db.execute('SELECT count(*) FROM samples').fetchone()[0],
            'scope':'Only superseded sports/countries/championships; all match evidence and sample payloads preserved'}


def protected_fingerprint(db):
    """Read a stable content digest, not only counts, before committing deletion."""
    h = hashlib.sha256()
    for table, where, params in (
        ('observations', f' WHERE kind NOT IN ({PLACEHOLDERS})', KINDS),
        ('samples', '', ()), ('cursors', '', ()), ('runs', '', ())):
        h.update(table.encode())
        order = 'table_name' if table == 'cursors' else 'id'
        for row in db.execute('SELECT * FROM '+table+where+' ORDER BY '+order, params):
            h.update(json.dumps(list(row), ensure_ascii=False, separators=(',', ':')).encode())
            h.update(b'\n')
    return h.hexdigest()


def compact_dictionaries(state, finding, *, apply=False, backup_output=None):
    state, finding = Path(state).resolve(), Path(finding).resolve()
    if state == finding or finding in state.parents or state in finding.parents:
        raise ValueError('Collector state must be outside finding')
    path = state/'dataset.sqlite3'
    if not path.is_file() or any(Path(str(path)+s).is_symlink() for s in ('','-wal','-shm','-journal')):
        raise ValueError('Missing or redirected collector database')
    with closing(readonly(path)) as db:
        report = plan(db)
    report['database_bytes_before'] = path.stat().st_size
    if not apply:
        return report
    if backup_output is None:
        raise ValueError('A new full backup directory is required for apply')
    # Backup + VACUUM workspace + room for WAL/checkpoints. Never delete backups to gain room.
    if shutil.disk_usage(state).free < 3*path.stat().st_size + 256*1024**2:
        raise ValueError('Insufficient free space for backup and compaction')
    lock_path = state/'collector.lock'
    if lock_path.is_symlink():
        raise ValueError('Redirected collector lock')
    with lock_path.open('a+') as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise ValueError('Stop doscbot-collector.service before applying compaction') from None
        # A complete current backup is made under the same lock, including committed WAL.
        backup = training_snapshot(state, finding, backup_output)
        with closing(sqlite3.connect(path, timeout=30)) as db:
            db.row_factory = sqlite3.Row
            db.execute('BEGIN IMMEDIATE')
            try:
                report = {**report, **plan(db)}
                before = protected_fingerprint(db)
                deleted = db.execute(f'DELETE FROM observations WHERE kind IN ({PLACEHOLDERS}) AND id NOT IN '
                    f'(SELECT max(id) FROM observations WHERE kind IN ({PLACEHOLDERS}) GROUP BY kind,entity_id)',
                    (*KINDS, *KINDS)).rowcount
                if deleted != report['remove_records'] or protected_fingerprint(db) != before:
                    raise ValueError('Protected data or compaction population changed')
                db.execute("INSERT OR REPLACE INTO metadata(key,value) VALUES ('dictionary_retention',?)", (POLICY,))
                db.commit()
            except BaseException:
                db.rollback()
                raise
            report.update(state='compacted', backup=str(Path(backup_output).resolve()),
                          backup_sha256=backup['sha256'], protected_content_sha256=before,
                          removed_records=deleted, retention_enabled=True, vacuum_completed=False)
            # VACUUM itself is atomic; if a reader prevents it, records remain safely compacted.
            try:
                checkpoint = db.execute('PRAGMA wal_checkpoint(TRUNCATE)').fetchone()
                if checkpoint[0]:
                    raise sqlite3.OperationalError('checkpoint busy')
                db.execute('VACUUM')
                checkpoint = db.execute('PRAGMA wal_checkpoint(TRUNCATE)').fetchone()
                report['vacuum_completed'] = checkpoint[0] == 0
                if not report['vacuum_completed']:
                    report['state'] = 'compacted_vacuum_pending'
                if db.execute('PRAGMA quick_check').fetchone()[0] != 'ok':
                    raise ValueError('Database integrity check failed; preserve backup')
            except sqlite3.OperationalError:
                report['state'] = 'compacted_vacuum_pending'
            report['database_bytes_after'] = path.stat().st_size
        destination = Path(backup_output)/'compaction-report.json'
        destination.write_text(json.dumps(report, ensure_ascii=False, indent=2)+'\n')
        return report
