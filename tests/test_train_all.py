import copy
from datetime import timedelta
import hashlib
import importlib.util
import json
import unittest
from unittest.mock import patch

from collector.training import prepare_training
from collector.train_baseline import read_dataset,train_baseline
from collector.train_all import train_all
if __package__:
    from . import test_training_server as fixtures
else:
    import test_training_server as fixtures


class AllDataTests(fixtures.TrainingFixture):
    dataset=fixtures.BaselineTests.dataset
    rewrite=fixtures.BaselineTests.rewrite

    def all_export(self):
        output=self.root/'all-data'
        prepare_training(self.state,self.finding,output=output)
        return output

    def test_all_data_mode_includes_all_dates_without_weakening_temporal_training(self):
        temporal=self.dataset();all_data=self.all_export()
        manifest,names,folds=read_dataset(all_data,1000,all_available=True)
        self.assertFalse(manifest['structurally_ready'])
        self.assertEqual(set(folds),{'all'})
        self.assertEqual(len(folds['all']),30)
        self.assertEqual(len({row['group'] for row in folds['all']}),15)
        self.assertEqual(len(names),54)
        with self.assertRaises(ValueError):read_dataset(all_data,1000)
        with self.assertRaises(ValueError):read_dataset(temporal,1000,all_available=True)

    def test_all_data_mode_keeps_group_time_checksum_and_sport_guards(self):
        self.dataset();dataset=self.all_export();records=self.read_rows(dataset)
        manifest=json.loads((dataset/'manifest.json').read_text())
        from collector.store import instant
        cases=[]
        value=copy.deepcopy(records);value[0]['result']['received_at']=(instant(manifest['checked_at'])+timedelta(hours=1)).isoformat();cases.append(value)
        value=copy.deepcopy(records);value[0]['partition']='train';cases.append(value)
        cases.append(records+[records[0]])
        cases.append([row for row in records if row['features']['sport']!='volleyball'])
        value=copy.deepcopy(records);value[0]['target']=None;cases.append(value)
        value=copy.deepcopy(records);value[0]['as_of']=value[0]['kickoff_at'];cases.append(value)
        value=copy.deepcopy(records);value[0]['features']['result_score']='2:0';cases.append(value)
        for index,value in enumerate(cases):
            with self.subTest(case=index):
                self.rewrite(dataset,value)
                with self.assertRaises(ValueError):read_dataset(dataset,1000,all_available=True)
        self.rewrite(dataset,records)
        with (dataset/'rows.jsonl').open('ab') as handle:handle.write(b'\n')
        with self.assertRaises(ValueError):read_dataset(dataset,1000,all_available=True)

    def test_existing_outputs_finding_and_invalid_resource_bounds_are_rejected_before_fit(self):
        self.dataset();dataset=self.all_export();pilot=self.root/'pilot';pilot.mkdir()
        existing=self.root/'existing';existing.mkdir();(existing/'keep').write_text('preserve')
        with self.assertRaises(ValueError):train_all(dataset,self.finding,pilot,existing)
        self.assertEqual((existing/'keep').read_text(),'preserve')
        with self.assertRaises(ValueError):train_all(dataset,self.finding,pilot,pilot/'new')
        with patch('collector.train_all.read_dataset',side_effect=AssertionError('must reject before finding read')):
            with self.assertRaises(ValueError):train_all(self.finding,self.finding,pilot,self.root/'no')
        for kwargs in ({'threads':0},{'threads':True},{'max_rows':9}):
            with self.subTest(kwargs=kwargs),self.assertRaises(ValueError):
                train_all(dataset,self.finding,pilot,self.root/'no',**kwargs)
        self.assertFalse((self.root/'no').exists())

    @unittest.skipUnless(importlib.util.find_spec('catboost'),'Optional: run in .venv-training for real CPU all-data fit')
    def test_real_all_data_fit_uses_old_folds_and_new_events_preserves_pilot_and_has_no_test_claim(self):
        from catboost import CatBoostClassifier
        temporal=self.dataset();pilot=self.root/'pilot'
        reference=train_baseline(temporal,self.finding,pilot,threads=1,iterations=30,depth=3)
        original={p.name:p.read_bytes() for p in pilot.iterdir()}
        self.sample('90','football',day=4,state='partial')
        self.sample('91','tennis',day=5,with_result=False)
        dataset=self.all_export();before=(dataset/'rows.jsonl').read_bytes();output=self.root/'all-model'
        report=train_all(dataset,self.finding,pilot,output,threads=1)
        self.assertEqual(report['training_events'],16)
        self.assertEqual(report['training_rows'],32)
        self.assertEqual(report['unlabelled_rows_skipped'],2)
        self.assertAlmostEqual(report['sport_weights']['football'],.8)
        self.assertAlmostEqual(report['sport_weights']['tennis'],32/30)
        self.assertEqual(report['parameters']['iterations'],reference['tree_count'])
        self.assertEqual(report['tree_count'],reference['tree_count'])
        self.assertEqual(report['parameters']['initialization'],'from_scratch')
        self.assertEqual(set(report['evaluation']),{'training'})
        self.assertIsNone(report['holdout_evaluation'])
        self.assertTrue(report['needs_future_evaluation'])
        self.assertFalse(report['validated_for_betting'])
        self.assertIn('partial',report['evaluation']['training']['quality'])
        self.assertEqual({p.name:p.read_bytes() for p in pilot.iterdir()},original)
        self.assertEqual((dataset/'rows.jsonl').read_bytes(),before)
        restored=CatBoostClassifier();restored.load_model(str(output/'model.cbm'))
        self.assertEqual(restored.tree_count_,reference['tree_count'])
        self.assertEqual(report['model_sha256'],hashlib.sha256((output/'model.cbm').read_bytes()).hexdigest())
        events=[json.loads(line) for line in (output/'training-events.jsonl').read_text().splitlines()]
        self.assertEqual(len(events),16)
        self.assertEqual(len({item['event_id'] for item in events}),16)
        self.assertIn('90',{item['event_id'] for item in events})
        self.assertNotIn('91',{item['event_id'] for item in events})
        with self.assertRaises(ValueError):train_all(dataset,self.finding,pilot,output,threads=1)

    @unittest.skipUnless(importlib.util.find_spec('catboost'),'Optional: verify real pilot model against metadata')
    def test_pilot_target_schema_tree_count_and_redirected_files_cannot_be_forged(self):
        temporal=self.dataset();pilot=self.root/'pilot'
        reference=train_baseline(temporal,self.finding,pilot,threads=1,iterations=30,depth=3)
        dataset=self.all_export()
        for index,change in enumerate(({'target':'other'},{'tree_count':reference['tree_count']+1,
                'best_iteration':reference['tree_count']},{'sports':{'football':10}})):
            value={**reference,**change};(pilot/'metrics.json').write_text(json.dumps(value))
            with self.subTest(change=change),self.assertRaises(ValueError):
                train_all(dataset,self.finding,pilot,self.root/('invalid-'+str(index)),threads=1)
            self.assertFalse((self.root/('invalid-'+str(index))).exists())
        (pilot/'metrics.json').write_text(json.dumps(reference))
        copy_model=self.root/'copy.cbm';copy_model.write_bytes((pilot/'model.cbm').read_bytes())
        (pilot/'model.cbm').unlink();(pilot/'model.cbm').symlink_to(copy_model)
        with self.assertRaises(ValueError):train_all(dataset,self.finding,pilot,self.root/'redirected',threads=1)
