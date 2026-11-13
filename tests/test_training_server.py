from contextlib import closing
import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import sqlite3
import unittest
from unittest.mock import patch

from collector.snapshot import training_snapshot
from collector.store import encoded
from collector.training import prepare_training
from collector.train_baseline import metrics,read_dataset,train_baseline
if __package__:
    from . import test_training as fixtures
else:
    # finding inserts its own root (and unrelated tests package) on sys.path.
    import test_training as fixtures

SCORES=fixtures.SCORES


class TrainingFixture(unittest.TestCase):
    setUp=fixtures.TrainingPreparationTests.setUp
    sample=fixtures.TrainingPreparationTests.sample
    outcome=fixtures.TrainingPreparationTests.outcome
    read_rows=fixtures.TrainingPreparationTests.read_rows


class SnapshotTests(TrainingFixture):
    def test_snapshot_includes_uncheckpointed_wal_and_source_keeps_working(self):
        event,stats,widget,_=self.sample()
        with closing(self.store.connect()) as writer:
            writer.execute('PRAGMA wal_autocheckpoint=0')
            writer.execute("INSERT INTO metadata VALUES ('wal_only','committed WAL content')");writer.commit()
            self.assertTrue((self.state/'dataset.sqlite3-wal').exists())
            target=self.root/'snapshot';report=training_snapshot(self.state,self.finding,target)
            with closing(sqlite3.connect(target/'dataset.sqlite3')) as result:
                self.assertEqual(result.execute("SELECT value FROM metadata WHERE key='wal_only'").fetchone()[0],'committed WAL content')
                self.assertEqual(result.execute('PRAGMA journal_mode').fetchone()[0],'delete')
            writer.execute("INSERT INTO metadata VALUES ('after_copy','collection continued')");writer.commit()
            with closing(sqlite3.connect(target/'dataset.sqlite3')) as result:
                self.assertIsNone(result.execute("SELECT value FROM metadata WHERE key='after_copy'").fetchone())
        self.assertEqual(report['sha256'],hashlib.sha256((target/'dataset.sqlite3').read_bytes()).hexdigest())
        self.assertEqual(report['counts']['samples'],1)
        self.assertEqual(sorted(p.name for p in target.iterdir()),['SHA256SUMS','dataset.sqlite3','manifest.json'])
        copied=prepare_training(target,self.finding)
        self.assertEqual(copied['eligible_events'],1)

    def test_snapshot_refuses_finding_foreign_existing_and_nested_destinations(self):
        self.sample();target=self.root/'snapshot'
        training_snapshot(self.state,self.finding,target)
        original=(target/'dataset.sqlite3').read_bytes()
        with self.assertRaises(ValueError):training_snapshot(self.state,self.finding,target)
        self.assertEqual((target/'dataset.sqlite3').read_bytes(),original)
        with self.assertRaises(ValueError):training_snapshot(self.state,self.finding,self.state/'copy')
        with patch('collector.snapshot.readonly',side_effect=AssertionError('finding must never open')):
            with self.assertRaises(ValueError):training_snapshot(self.finding,self.finding,self.root/'bad')
        foreign=self.root/'foreign';foreign.mkdir()
        with closing(sqlite3.connect(foreign/'dataset.sqlite3')) as db,db:db.execute('CREATE TABLE unrelated(value TEXT)')
        with self.assertRaises(ValueError):training_snapshot(foreign,self.finding,self.root/'bad')
        self.assertFalse((self.root/'bad').exists())

    def test_snapshot_failure_removes_only_its_own_incomplete_export(self):
        self.sample();before=self.store.status()
        with patch('collector.snapshot.hashlib.sha256',side_effect=RuntimeError('test checksum failure')):
            with self.assertRaises(RuntimeError):training_snapshot(self.state,self.finding,self.root/'failed')
        self.assertFalse((self.root/'failed').exists())
        self.assertEqual(self.store.status(),before)


class BaselineTests(TrainingFixture):
    def dataset(self):
        from datetime import timedelta
        for day in range(3):
            for index,sport in enumerate(SCORES):self.sample(str(day*10+index),sport,day)
        output=self.root/'dataset'
        prepare_training(self.state,self.finding,output=output,
            valid_from=(self.base+timedelta(days=1)).replace(hour=0).isoformat(),
            test_from=(self.base+timedelta(days=2)).replace(hour=0).isoformat())
        return output

    def rewrite(self,dataset,records):
        content=''.join(encoded(record)+'\n' for record in records).encode()
        (dataset/'rows.jsonl').write_bytes(content)
        manifest=json.loads((dataset/'manifest.json').read_text())
        manifest.update(rows_sha256=hashlib.sha256(content).hexdigest(),export_rows=len(records))
        (dataset/'manifest.json').write_text(json.dumps(manifest))

    @unittest.skipUnless(importlib.util.find_spec('catboost'),'Optional: run in .venv-training for real CPU fit')
    def test_real_cpu_fit_reload_and_test_labels_do_not_affect_training(self):
        from catboost import CatBoostClassifier
        dataset=self.dataset();output=self.root/'model1'
        report=train_baseline(dataset,self.finding,output,threads=1,iterations=30,depth=3)
        self.assertEqual(report['parameters']['device'],'CPU')
        self.assertFalse(report['validated_for_betting'])
        self.assertEqual(report['evaluation']['test']['overall']['events'],5)
        self.assertEqual(report['evaluation']['test']['overall']['rows'],10)
        self.assertEqual(set(report['evaluation']['test']['sports']),set(SCORES))
        self.assertTrue((output/'model.cbm').is_file())
        restored=CatBoostClassifier();restored.load_model(str(output/'model.cbm'))
        records=self.read_rows(dataset)
        for row in records:
            if row['partition']=='test':row['target']=1-row['target']
        self.rewrite(dataset,records)
        output2=self.root/'model2';train_baseline(dataset,self.finding,output2,threads=1,iterations=30,depth=3)
        other=CatBoostClassifier();other.load_model(str(output2/'model.cbm'))
        _,_,folds=read_dataset(dataset,1000)
        data=[r['x'] for r in folds['test']]
        self.assertEqual(restored.predict_proba(data).tolist(),other.predict_proba(data).tolist())

    def test_temporal_leakage_duplicate_groups_and_checksum_corruption_are_rejected(self):
        dataset=self.dataset();records=self.read_rows(dataset)
        invalid=copy.deepcopy(records);invalid[0]['partition']='test';self.rewrite(dataset,invalid)
        with self.assertRaises(ValueError):read_dataset(dataset,1000)
        self.rewrite(dataset,records+[records[0]])
        with self.assertRaises(ValueError):read_dataset(dataset,1000)
        self.rewrite(dataset,records)
        with (dataset/'rows.jsonl').open('ab') as handle:handle.write(b'\n')
        with self.assertRaises(ValueError):read_dataset(dataset,1000)

    def test_missing_folds_overwrite_and_resource_limits_are_rejected(self):
        dataset=self.dataset()
        with self.assertRaises(ValueError):read_dataset(dataset,10)
        output=self.root/'existing';output.mkdir();(output/'keep').write_text('keep')
        with self.assertRaises(ValueError):train_baseline(dataset,self.finding,output)
        self.assertEqual((output/'keep').read_text(),'keep')
        manifest=json.loads((dataset/'manifest.json').read_text());manifest['structurally_ready']=False
        (dataset/'manifest.json').write_text(json.dumps(manifest))
        with self.assertRaises(ValueError):train_baseline(dataset,self.finding,self.root/'new')
        self.assertFalse((self.root/'new').exists())


class MetricTests(unittest.TestCase):
    def test_metrics_match_known_binary_examples(self):
        rows=[{'y':0,'group':'one'},{'y':1,'group':'one'}]
        result=metrics(rows,[.25,.75])
        self.assertAlmostEqual(result['log_loss'],.2876820724517809)
        self.assertAlmostEqual(result['brier'],.0625)
        self.assertEqual(result['events'],1)
        self.assertEqual(result['rows'],2)


