from contextlib import closing
from datetime import timedelta
import fcntl
import json
import sqlite3
import unittest
from unittest.mock import patch

from collector.dictionary_retention import compact_dictionaries, enabled, observe_dictionary, KINDS
from collector.training import prepare_training
import test_training as fixtures


class RetentionTests(unittest.TestCase):
    setUp = fixtures.TrainingPreparationTests.setUp
    sample = fixtures.TrainingPreparationTests.sample
    outcome = fixtures.TrainingPreparationTests.outcome

    def populate(self):
        for i,sport in enumerate(fixtures.SCORES):
            self.sample(str(i+1), sport)
        for version in range(3):
            at=(self.base+timedelta(minutes=version)).isoformat()
            for kind in (*KINDS,'widget_json','widget_data','event','market','team_statistics'):
                self.store.observation(kind,'1',at,{'version':version,'unicode':'Статистика','padding':'x'*10000})
        with closing(self.store.connect()) as db,db:
            db.execute("INSERT INTO cursors VALUES ('event_history',123,'hash')")
            db.execute("INSERT INTO runs(started_at,state,details) VALUES ('now','completed','{}')")

    def rows(self):
        with closing(self.store.connect()) as db:
            return [tuple(r) for r in db.execute('SELECT * FROM observations ORDER BY id')]

    def test_preview_is_read_only_and_apply_preserves_training_export_exactly(self):
        self.populate();before=self.rows()
        output1=self.root/'export-before';prepare_training(self.state,self.finding,output=output1)
        r=compact_dictionaries(self.state,self.finding)
        self.assertEqual(r['remove_records'],6);self.assertEqual(self.rows(),before)
        self.assertFalse((self.state/'collector.lock').exists())
        backup=self.root/'backup'
        r=compact_dictionaries(self.state,self.finding,apply=True,backup_output=backup)
        self.assertEqual(r['removed_records'],6);self.assertTrue(r['vacuum_completed'])
        self.assertLess(r['database_bytes_after'],r['database_bytes_before'])
        after=self.rows();latest={kind:max(row[0] for row in before if row[1]==kind) for kind in KINDS}
        self.assertEqual(after,[row for row in before if row[1] not in KINDS or row[0]==latest[row[1]]])
        with closing(sqlite3.connect(backup/'dataset.sqlite3')) as db:
            self.assertEqual(db.execute('SELECT * FROM observations ORDER BY id').fetchall(),before)
        output2=self.root/'export-after';prepare_training(self.state,self.finding,output=output2)
        self.assertEqual((output1/'rows.jsonl').read_bytes(),(output2/'rows.jsonl').read_bytes())
        self.assertEqual(json.loads((output1/'manifest.json').read_text())['labelled_events'],5)
        self.assertTrue((backup/'compaction-report.json').is_file())

    def test_refuses_running_collector_foreign_database_and_existing_backup(self):
        self.populate();before=self.rows()
        with (self.state/'collector.lock').open('a+') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            with self.assertRaisesRegex(ValueError,'Stop doscbot'):
                compact_dictionaries(self.state,self.finding,apply=True,backup_output=self.root/'bad')
        self.assertFalse((self.root/'bad').exists());self.assertEqual(self.rows(),before)
        existing=self.root/'existing';existing.mkdir()
        with self.assertRaises(ValueError):compact_dictionaries(self.state,self.finding,apply=True,backup_output=existing)
        self.assertEqual(self.rows(),before)
        with closing(self.store.connect()) as db,db:db.execute("UPDATE metadata SET value='other' WHERE key='owner'")
        with self.assertRaises(ValueError):compact_dictionaries(self.state,self.finding)
        with self.assertRaises(ValueError):compact_dictionaries(self.finding,self.finding)

    def test_failed_verification_rolls_back_and_keeps_full_backup(self):
        self.populate();before=self.rows()
        with patch('collector.dictionary_retention.protected_fingerprint',side_effect=['before','different']):
            with self.assertRaises(ValueError):
                compact_dictionaries(self.state,self.finding,apply=True,backup_output=self.root/'rollback')
        self.assertEqual(self.rows(),before)
        with closing(self.store.connect()) as db:self.assertFalse(enabled(db))
        self.assertTrue((self.root/'rollback/dataset.sqlite3').is_file())

    def test_future_import_is_opt_in_and_keeps_last_actual_change(self):
        at=self.base.isoformat()
        with closing(self.store.connect()) as db,db:
            for n in range(3):
                observe_dictionary(self.store,db,'sports','1',(self.base+timedelta(minutes=n)).isoformat(),{'name':'Football'})
            self.assertEqual(db.execute('SELECT count(*) FROM observations').fetchone()[0],3)
        compact_dictionaries(self.state,self.finding,apply=True,backup_output=self.root/'backup')
        with closing(self.store.connect()) as db,db:
            before=dict(db.execute('SELECT * FROM observations').fetchone());self.assertTrue(enabled(db))
            self.assertEqual(observe_dictionary(self.store,db,'sports','1',at,{'name':'Football'},retain=True),0)
            self.assertEqual(dict(db.execute('SELECT * FROM observations').fetchone()),before)
            self.assertEqual(observe_dictionary(self.store,db,'sports','1',at,{'name':'Футбол'},retain=True),1)
            self.assertEqual(db.execute('SELECT count(*) FROM observations').fetchone()[0],1)
            self.assertEqual(json.loads(db.execute('SELECT payload FROM observations').fetchone()[0]),{'name':'Футбол'})
            with self.assertRaises(ValueError):observe_dictionary(self.store,db,'widget_json','1',at,{},retain=True)

    def test_disk_shortage_does_not_modify_data_or_create_backup(self):
        self.populate();before=self.rows()
        with patch('collector.dictionary_retention.shutil.disk_usage') as usage:
            usage.return_value.free=0
            with self.assertRaisesRegex(ValueError,'Insufficient'):
                compact_dictionaries(self.state,self.finding,apply=True,backup_output=self.root/'bad')
        self.assertFalse((self.root/'bad').exists());self.assertEqual(self.rows(),before)
