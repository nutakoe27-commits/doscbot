import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
from collector import statistics_contract as c
from collector.train_statistics import train_statistics


def features():
    result={k:'football' if k=='sport' else 'home' if k=='selection_side' else 'context' for k in c.CATEGORICAL}
    result.update({k:None for k in c.NUMERIC})
    for side in ('selected','opponent'):result.update({side+'_form_count':10,side+'_form_win_rate':.6})
    return c.project(result)

class StatisticsContractTests(unittest.TestCase):
    def test_prices_and_derived_fields_cannot_change_statistics_vector(self):
        f=features();changed={**f,'selected_odds':99,'opponent_odds':1.01,'draw_odds':42,'market_count':1000,'lead_minutes':999,
            'implied_selected':.01,'implied_opponent':.9,'implied_draw':.09,'overround':3,'selected_odds__missing':1,'target':0}
        self.assertEqual(c.project(changed),f)
        self.assertTrue(c.supported(f))
        with self.assertRaises(ValueError):c.vector(changed)
        for side in ('selected','opponent'):
            empty={**f,side+'_form_count':None}
            self.assertFalse(c.supported(c.project(empty)))

    def test_second_stage_changes_only_decision_not_preference_or_probability(self):
        high=c.decide({'home':.7,'away':.2},{'home':2,'away':6})
        low=c.decide({'home':.7,'away':.2},{'home':1.1,'away':100})
        self.assertEqual(high['statistics'],low['statistics'])
        self.assertEqual(high['statistics_price']['decision'],'bet')
        self.assertEqual(low['statistics_price']['decision'],'skip')
        self.assertEqual(low['statistics_price']['side'],'home')
        self.assertEqual(c.decide({'home':.4,'away':.4},{'home':10})['statistics']['side'],None)
        self.assertEqual(c.decide({'home':.51,'away':.2},{'home':10})['statistics_price']['decision'],'skip')
        self.assertEqual(c.decide({'home':.7,'away':.2},{})['statistics_price']['reason'],'missing_price')
        for price in (True,float('nan'),float('inf'),1):
            self.assertEqual(c.decide({'home':.7,'away':.2},{'home':price})['statistics_price']['decision'],'skip')

    def test_invalid_policy_or_probabilities_rejected(self):
        for kw in ({'min_probability':float('nan')},{'min_expected_return':-1},{'version':'unknown'}):
            with self.assertRaises(ValueError):c.decide({'home':.7,'away':.2},{'home':2},c.DEFAULT_POLICY|kw)
        for p in ({'home':True,'away':.2},{'home':float('nan'),'away':.2},{'home':.7}):
            with self.assertRaises(ValueError):c.decide(p,{'home':2})

    def test_existing_outputs_and_unsafe_locations_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp);existing=root/'existing';existing.mkdir();(existing/'keep').write_text('keep')
            with self.assertRaises(ValueError):train_statistics(root/'data',root/'finding',existing)
            with self.assertRaises(ValueError):train_statistics(root/'finding',root/'finding',root/'new')
            self.assertEqual((existing/'keep').read_text(),'keep')

@unittest.skipUnless(importlib.util.find_spec('catboost'),'Run in the isolated training environment')
class StatisticsFitTests(unittest.TestCase):
    def test_real_fit_price_blind_contract_exclusions_packaging_and_no_overwrite(self):
        from datetime import datetime,timedelta,timezone
        from catboost import CatBoostClassifier,Pool
        from collector.training import SCHEMA as old_schema
        from collector.store import digest,encoded
        from collector.train_statistics import package_statistics
        with tempfile.TemporaryDirectory() as folder:
            root=Path(folder);dataset=root/'dataset';dataset.mkdir();at=datetime.now(timezone.utc)-timedelta(days=10)
            rows=[]
            for i,sport in enumerate(('football','hockey','basketball','volleyball','tennis')):
                for event in range(2):
                    for side in ('home','away'):
                        f={k:None for k in old_schema['numeric']};f.update(features());f.update(sport=sport,selection_side=side)
                        f.update(selected_odds=1.1 if side=='home' else 12,opponent_odds=12 if side=='home' else 1.1,implied_selected=.9 if side=='home' else .1)
                        f['selected_form_win_rate']=.7 if side=='home' else .3
                        if sport=='tennis':
                            for k in c.NUMERIC:f[k]=None
                        f.update({k+'__missing':int(f[k] is None) for k in old_schema['numeric']})
                        rows.append({'format':'doscbot.participant-training-row.v1','event_group':str(i*2+event),'as_of':at.isoformat(),
                            'kickoff_at':(at+timedelta(hours=3)).isoformat(),'sample_sha256':str(i*2+event),'partition':'unassigned',
                            'target':int(side=='home'),'features':f,'result':{'received_at':(at+timedelta(hours=6)).isoformat()}})
            raw=''.join(encoded(r)+'\n' for r in rows).encode();(dataset/'rows.jsonl').write_bytes(raw)
            (dataset/'schema.json').write_text(encoded(old_schema))
            manifest={'schema_sha256':digest(old_schema),'checked_at':(at+timedelta(days=1)).isoformat(),'split_required':True,'lead_minutes':60,
                'rows_sha256':hashlib.sha256(raw).hexdigest(),'export_rows':len(rows),'requested_sports':['football','hockey','basketball','volleyball','tennis'],'preparation_code_sha256':'fixture'}
            (dataset/'manifest.json').write_text(encoded(manifest));output=root/'model'
            result=train_statistics(dataset,root/'finding',output,threads=1,iterations=10,depth=2)
            self.assertEqual(result['decision_mode'],'primary_llm.v1');self.assertIsNone(result['decision_policy'])
            self.assertEqual(result['training_events'],8);self.assertEqual(result['unsupported_sports'],['tennis'])
            self.assertIsNone(result['holdout_evaluation']);self.assertEqual(set(result['evaluation']),{'training'})
            model=CatBoostClassifier();model.load_model(str(output/'model.cbm'))
            self.assertEqual(model.feature_names_,c.NAMES)
            f=rows[0]['features'];changed={**f,'selected_odds':999,'implied_selected':.001,'overround':10}
            pool=Pool([c.vector(c.project(x)) for x in (f,changed)],feature_names=c.NAMES,cat_features=c.CATEGORICAL)
            p=model.predict_proba(pool)[:,1];self.assertEqual(p[0],p[1])
            original={x.name:x.read_bytes() for x in output.iterdir()}
            bundle=root/'bundle';report=package_statistics(output,bundle)
            self.assertEqual(report['training_events'],8)
            self.assertEqual((bundle/'dataset/rows.jsonl').read_bytes(),raw)
            self.assertEqual((bundle/'model/model.cbm').read_bytes(),(output/'model.cbm').read_bytes())
            with self.assertRaises(ValueError):package_statistics(output,bundle)
            with self.assertRaises(ValueError):train_statistics(dataset,root/'finding',output)
            self.assertEqual({x.name:x.read_bytes() for x in output.iterdir()},original)
            self.assertEqual((dataset/'rows.jsonl').read_bytes(),raw)
