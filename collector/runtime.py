"""Small, atomic collector telemetry; a monitor never needs to own the writer."""

from contextlib import closing
import json
import os
import sqlite3
import threading

from .store import encoded, now


def update_runtime(store, **fields):
    try:
        with closing(store.connect()) as db, db:
            db.execute("BEGIN IMMEDIATE")
            row = db.execute("SELECT value FROM metadata WHERE key='runtime'").fetchone()
            state = json.loads(row[0]) if row else {}
            state.update(fields)
            state["updated_at"] = now()
            db.execute("INSERT OR REPLACE INTO metadata VALUES ('runtime',?)", (encoded(state),))
    except sqlite3.Error:
        # Collection owns data persistence; telemetry must not abort a pass.
        return False
    return True


class Runtime:
    def __init__(self, store, **settings):
        self.store, self.settings = store, settings
        self.stop = threading.Event()
        self.thread = threading.Thread(target=self.beat, name="collector-heartbeat", daemon=True)

    def beat(self):
        while not self.stop.wait(5):
            update_runtime(self.store, heartbeat_at=now())

    def __enter__(self):
        update_runtime(self.store, phase="starting", pid=os.getpid(), started_at=now(),
                       heartbeat_at=now(), stopped_at=None, next_run_at=None,
                       current_match=None, current_event=None, completed=0, total=0, **self.settings)
        self.thread.start()
        return self

    def __exit__(self, *_):
        self.stop.set()
        self.thread.join(timeout=2)
        update_runtime(self.store, phase="stopped", stopped_at=now(), next_run_at=None)
