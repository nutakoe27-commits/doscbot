from contextlib import closing
import copy
from datetime import datetime,timedelta,timezone
import hashlib
import json
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import patch

from collector.store import Store,digest,encoded
from collector.training import NATIVE_SPORTS, NUMERIC, prepare_training, score_winner


SCORES={'football':'1:2 (0:0 1:2)', 'basketball':'99:79 (15:25 27:15 30:23 27:16)',
        'hockey':'2:5 (0:2 2:1 0:2)', 'tennis':'0:2 (2:6 3:6)', 'volleyball':'0:3 (27:29 16:25 17:25)'}


class ScoreContractTests(unittest.TestCase):
    def test_best_of_fallback_is_bound_to_the_same_provider_match(self):
        widget={'statistics_id':'100','match':{},'documents':[{'name':'match_info_statshub',
            'fields':[{'path':'match._id','value':101},{'path':'match.bestof','value':3}]}]}
        self.assertEqual(score_winner('tennis',SCORES['tennis'],widget)[1],'best_of_unconfirmed')
        widget['documents'][0]['fields'][0]['value']=100
        self.assertEqual(score_winner('tennis',SCORES['tennis'],widget),('away',None))
        widget['documents'].append({'name':'match_info_statshub','fields':[{'path':'match._id','value':100},{'path':'match.bestof','value':5}]})
        self.assertEqual(score_winner('tennis',SCORES['tennis'],widget)[1],'best_of_unconfirmed')

    def test_five_real_source_examples_have_consistent_periods(self):
        for sport,score in SCORES.items():
            with self.subTest(sport=sport):
                result,reason=score_winner(sport,score,{'match':{'best_of':3 if sport=='tennis' else 5}})
                self.assertIsNone(reason)
                self.assertEqual(result,'home' if sport=='basketball' else 'away')

    def test_overtime_annotations_incomplete_sets_and_inconsistent_totals_are_not_guessed(self):
        cases=[('hockey','3:2 (0:1 1:0 1:1 1:0)'),('football','2:0 (0:0 1:0)'),
               ('basketball','99:79 (15:25 27:15 30:23 27:16 1:0)'),('football','1:0 отменён'),
               ('football','1:0'),('tennis','2:0 (6:2 3:1)'),('volleyball','3:0 (25:1 25:1 24:23)'),
               ('tennis','3:1 (6:1 6:1 6:1 1:6)')]
        for sport,score in cases:
            with self.subTest(score=score):
                result,reason=score_winner(sport,score,{'match':{'best_of':5 if sport in ('tennis','volleyball') else None}})
                self.assertIsNone(result)
                self.assertIsNotNone(reason)
        self.assertEqual(score_winner('tennis',SCORES['tennis'],{})[1],'best_of_unconfirmed')


class TrainingPreparationTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        self.root=Path(self.temp.name);self.state=self.root/'data';self.finding=self.root/'finding'
        self.store=Store(self.state/'dataset.sqlite3','fixture-revision')
        self.base=(datetime.now(timezone.utc)-timedelta(days=10)).replace(hour=12,minute=0,second=0,microsecond=0)
        self.native={sport:identifier for identifier,sport in NATIVE_SPORTS.items()}

    def sample(self,identifier='1',sport='football',day=0,lead=60,state='captured',score=None,with_result=True):
        kickoff=self.base+timedelta(days=day);at=(kickoff-timedelta(minutes=lead)).isoformat()
        event={'source_event_id':identifier,'sport':sport,'phase':'prematch','home':'Home '+identifier,'away':'Away '+identifier,
               'competition':'Source competition','kickoff_at':kickoff.isoformat(),'observed_at':at,
               'markets':[{'key':'winner','name':'Исход 1X2' if sport in ('football','hockey') else 'Победитель матча',
                   'period':'осн.время' if sport in ('football','hockey') else 'Матч','parameter':None,
                   'outcomes':[{'name':side,'odds':price,'state':'open'} for side,price in
                               ([('П1',2),('X',4),('П2',3)] if sport in ('football','hockey') else [('П1',2),('П2',3)])]}]}
        stats={'source_event_id':identifier,'statistics_id':'100'+identifier,'state':'ok','match_phase':'prematch',
               'observed_at':at,'attempted_at':at}
        widget={'event_id':identifier,'statistics_id':'100'+identifier,'sport':sport,'observed_at':at,
                'profile':{'identity_verified':True},'match':{'status':{'_id':0},'result':{'home':None,'away':None},
                    'home':{'id':'h'},'away':{'id':'a'},'season_id':'s','best_of':3 if sport=='tennis' else 5},
                'views':{'state':state,'groups':{'card':{'expected':2,'captured':2 if state=='captured' else 1}}},
                'coverage':{'sections':12,'documents':23},'documents':[],
                'form':[{'team':{'id':'h'},'form':{'total':[{'matchid':'old1','typeid':'W'},{'matchid':'old2','typeid':'L'}]},'source':{'observed_at':at}}],
                'season_statistics':[{'team':{'id':'h'},'season_id':'s','window':{'kind':'season'},'source':{'observed_at':at},
                    'scopes':{'total':{'matches':4,'wins':3,'goals_for':0,'goals_against':3}}}]}
        self.assertTrue(self.store.save_sample(event,stats,{},at,widget=widget))
        result=None
        if with_result:
            result={'format':'doscbot.event-outcome.v1','event_id':identifier,'sport_id':self.native[sport],
                    'home':event['home'],'away':event['away'],'kickoff_at':event['kickoff_at'],
                    'score_text':score or SCORES[sport],'method':'sport_radar_names_time',
                    'result_key':'result-'+identifier,'source_result_sha256':'a'*64}
            self.outcome(identifier,result,kickoff+timedelta(hours=3))
        return event,stats,widget,result

    def outcome(self,identifier,value,at):
        with patch('collector.store.now',return_value=at.isoformat()):
            self.store.observation('event_outcome',identifier,at.isoformat(),value)

    def read_rows(self,output):
        return [json.loads(line) for line in (output/'rows.jsonl').read_text().splitlines()]

    def test_five_sports_full_and_partial_inputs_export_without_modifying_database(self):
        for index,sport in enumerate(SCORES):self.sample(str(index),sport,state='partial' if sport=='tennis' else 'captured')
        before=(self.state/'dataset.sqlite3').read_bytes();output=self.root/'prepared'
        report=prepare_training(self.state,self.finding,output=output)
        self.assertEqual(report['eligible_events'],5)
        self.assertEqual(report['labelled_events'],5)
        self.assertEqual(sum(item['labelled_rows'] for item in report['sports'].values()),10)
        self.assertTrue(all(item['labelled_events']==1 and sum(item['labelled_hours_moscow'].values())==1
                            and item['labelled_kickoff_first_utc']==self.base.isoformat() for item in report['sports'].values()))
        self.assertEqual(report['coverage_timezone'],'Europe/Moscow')
        self.assertEqual(report['sports']['tennis']['partial'],1)
        self.assertFalse(report['structurally_ready'])
        self.assertTrue(report['split_required'])
        self.assertEqual((self.state/'dataset.sqlite3').read_bytes(),before)
        rows=self.read_rows(output)
        self.assertEqual(len(rows),10)
        self.assertEqual(report['export_rows'],10)
        self.assertEqual(report['rows_sha256'],hashlib.sha256((output/'rows.jsonl').read_bytes()).hexdigest())
        football=next(row for row in rows if row['features']['sport']=='football' and row['features']['selection_side']=='home')
        self.assertEqual(football['features']['selected_goals_for_per_match'],0)
        self.assertEqual(football['features']['selected_goals_for_per_match__missing'],0)
        self.assertIsNone(football['features']['opponent_goals_for_per_match'])
        self.assertEqual(football['features']['opponent_goals_for_per_match__missing'],1)
        tennis=next(row for row in rows if row['features']['sport']=='tennis')
        self.assertIsNone(tennis['features']['selected_goals_for_per_match'])
        self.assertEqual(tennis['features']['score_units'],'sets|games')
        for row in rows:
            self.assertNotIn('score_text',row['features'])
            self.assertNotIn('target',row['features'])
            self.assertTrue(all(key in row['features'] for key in NUMERIC))

    def test_latest_horizon_snapshot_is_chosen_once_and_shorter_lead_is_not_used(self):
        self.sample('1',lead=90,with_result=False)
        self.sample('1',lead=60,state='partial',with_result=False)
        self.sample('1',lead=5,with_result=False)
        output=self.root/'horizon';report=prepare_training(self.state,self.finding,output=output)
        rows=self.read_rows(output)
        self.assertEqual(report['sample_count'],3)
        self.assertEqual(report['eligible_events'],1)
        self.assertEqual(len(rows),2)
        self.assertEqual({r['features']['lead_minutes'] for r in rows},{60})
        self.assertEqual({r['features']['capture_state'] for r in rows},{'partial'})
        self.assertEqual(len({r['sample_id'] for r in rows}),1)

    def test_temporal_splits_keep_pairs_together_and_use_labels_known_at_fit_time(self):
        original=None
        for day in range(3):
            for index,sport in enumerate(SCORES):
                _,_,_,value=self.sample(str(day*10+index),sport,day)
                if day==0 and sport=='football':original=value
        self.outcome('0',{**original,'score_text':'2:1 (1:0 1:1)'},self.base+timedelta(days=4))
        valid=(self.base+timedelta(days=1)).replace(hour=0).isoformat()
        test=(self.base+timedelta(days=2)).replace(hour=0).isoformat()
        output=self.root/'folds';report=prepare_training(self.state,self.finding,output=output,valid_from=valid,test_from=test)
        self.assertTrue(report['structurally_ready'])
        rows=self.read_rows(output)
        for identifier in {row['event_group'] for row in rows}:
            pair=[row for row in rows if row['event_group']==identifier]
            self.assertEqual(len(pair),2)
            self.assertEqual(len({r['partition'] for r in pair}),1)
        train=next(row for row in rows if row['event_group']=='0' and row['features']['selection_side']=='home')
        self.assertEqual(train['target'],0)
        self.assertEqual(train['result']['score_text'],SCORES['football'])
        self.assertEqual(train['training_weight'],1)
        self.assertTrue(all(row['training_weight'] is None for row in rows if row['partition']!='train'))

    def test_late_result_and_wrong_identity_remain_unlabelled(self):
        _,_,_,result=self.sample('1',with_result=False)
        event,_,_,result=self.sample('2')
        self.outcome('2',{**result,'away':'Wrong opponent'},self.base+timedelta(hours=4))
        event,_,_,result=self.sample('3',with_result=True)
        with closing(self.store.connect()) as db,db:
            db.execute("UPDATE observations SET received_at=? WHERE entity_id='3'",((self.base+timedelta(days=4)).isoformat(),))
        valid=(self.base+timedelta(days=1)).replace(hour=0).isoformat();test=(self.base+timedelta(days=2)).replace(hour=0).isoformat()
        output=self.root/'pending';prepare_training(self.state,self.finding,output=output,valid_from=valid,test_from=test)
        rows=self.read_rows(output)
        self.assertTrue(all(row['target'] is None for row in rows))
        self.assertEqual({row['label_reason'] for row in rows},{'outcome_identity_unconfirmed','result_not_available_at_cutoff'})

    def test_draw_labels_both_participants_zero_and_is_not_settled_for_two_way_market(self):
        event,stats,widget,_=self.sample('1',score='1:1 (0:0 1:1)')
        output=self.root/'draw';prepare_training(self.state,self.finding,output=output)
        self.assertEqual([row['target'] for row in self.read_rows(output)],[0,0])
        event['source_event_id']='2';event['home']='Home 2';event['away']='Away 2'
        stats['source_event_id']='2';stats['statistics_id']='1002';widget['event_id']='2';widget['statistics_id']='1002'
        event['markets'][0]['name']='Победитель матча';event['markets'][0]['period']='Матч'
        event['markets'][0]['outcomes']=[o for o in event['markets'][0]['outcomes'] if o['name']!='X']
        self.assertTrue(self.store.save_sample(event,stats,{},event['observed_at'],widget=widget))
        self.outcome('2',{'format':'doscbot.event-outcome.v1','event_id':'2','sport_id':1,'home':'Home 2','away':'Away 2',
            'kickoff_at':event['kickoff_at'],'score_text':'1:1 (0:0 1:1)','method':'sport_radar_names_time'},self.base+timedelta(hours=3))
        output2=self.root/'two-way';prepare_training(self.state,self.finding,output=output2)
        self.assertTrue(all(row['label_reason']=='draw_in_two_way_market' for row in self.read_rows(output2) if row['event_group']=='2'))

    def test_scope_filter_and_missing_market_keep_data_without_creating_labels(self):
        event,stats,widget,_=self.sample('1',with_result=False)
        event['source_event_id']='2';stats['source_event_id']='2';widget['event_id']='2'
        event['markets']=[]
        self.assertTrue(self.store.save_sample(event,stats,{},event['observed_at'],widget=widget))
        self.sample('3','tennis')
        output=self.root/'subset';report=prepare_training(self.state,self.finding,output=output,sports='football')
        self.assertEqual(report['sample_events'],2)
        self.assertEqual(report['without_horizon_sample'],0)
        rows=self.read_rows(output)
        self.assertEqual(len(rows),4)
        self.assertTrue(all(row['features']['sport']=='football' for row in rows))
        self.assertTrue(all(row['features']['selected_odds'] is None for row in rows if row['event_group']=='2'))

    def test_target_payload_and_future_schedules_never_become_features(self):
        event,stats,widget,_=self.sample('1',with_result=False)
        first=self.root/'first';prepare_training(self.state,self.finding,output=first)
        widget['documents']=[{'name':'stats_match_get','numeric':[{'path':'result.home','value':999}],
                             'fields':[{'path':'nextopponent.date','value':'2099-01-01'}]}]
        self.assertTrue(self.store.save_sample(event,stats,{},event['observed_at'],widget=widget))
        second=self.root/'second';prepare_training(self.state,self.finding,output=second)
        self.assertEqual([row['features'] for row in self.read_rows(first)],[row['features'] for row in self.read_rows(second)])

    def test_missing_foreign_redirected_databases_and_existing_exports_are_protected(self):
        missing=self.root/'missing'
        self.assertEqual(prepare_training(missing,self.finding)['reason'],'database_missing')
        self.assertFalse(missing.exists())
        foreign=self.root/'foreign';foreign.mkdir()
        with closing(sqlite3.connect(foreign/'dataset.sqlite3')) as db,db:db.execute('CREATE TABLE private_data(value TEXT)')
        before_foreign=(foreign/'dataset.sqlite3').read_bytes()
        with self.assertRaises(ValueError):prepare_training(foreign,self.finding)
        self.assertEqual((foreign/'dataset.sqlite3').read_bytes(),before_foreign)
        self.finding.mkdir();(self.finding/'dataset.sqlite3').write_bytes(b'old finding data')
        with patch('collector.training.readonly',side_effect=AssertionError('must reject before opening finding')):
            with self.assertRaises(ValueError):prepare_training(self.finding,self.finding)
        self.assertEqual((self.finding/'dataset.sqlite3').read_bytes(),b'old finding data')
        redirected=self.root/'redirected';redirected.mkdir();(redirected/'dataset.sqlite3').symlink_to(self.finding/'dataset.sqlite3')
        with self.assertRaises(ValueError):prepare_training(redirected,self.finding)
        self.sample()
        output=self.root/'existing';prepare_training(self.state,self.finding,output=output)
        before=(output/'rows.jsonl').read_bytes()
        with self.assertRaises(ValueError):prepare_training(self.state,self.finding,output=output)
        with self.assertRaises(ValueError):prepare_training(self.state,self.finding,output=self.state/'export')
        self.assertEqual((output/'rows.jsonl').read_bytes(),before)

    def test_invalid_time_boundaries_are_rejected(self):
        for arguments in ({'valid_from':self.base.isoformat()}, {'valid_from':'2026-01-02','test_from':'2026-01-03'},
                          {'valid_from':self.base.isoformat(),'test_from':self.base.isoformat()}):
            with self.subTest(arguments=arguments),self.assertRaises(ValueError):prepare_training(self.state,self.finding,**arguments)

    def test_corrupt_linked_results_are_not_used_as_labels(self):
        self.sample()
        with closing(self.store.connect()) as db,db:
            row=db.execute("SELECT id,payload FROM observations WHERE kind='event_outcome'").fetchone()
            value=json.loads(row['payload']);value['score_text']='2:1 (1:0 1:1)'
            db.execute('UPDATE observations SET payload=? WHERE id=?',(encoded(value),row['id']))
        output=self.root/'corrupt';prepare_training(self.state,self.finding,output=output)
        self.assertTrue(all(row['target'] is None and row['label_reason']=='outcome_integrity_mismatch' for row in self.read_rows(output)))

    def test_balancing_uses_only_labelled_training_events(self):
        self.sample('1');self.sample('2');self.sample('3','basketball')
        for day in (1,2):
            for index in range(5):self.sample(str(day*10+index),'football',day)
            self.sample(str(day*10+9),'basketball',day)
        output=self.root/'weights'
        prepare_training(self.state,self.finding,output=output,sports='football,basketball',
            valid_from=(self.base+timedelta(days=1)).replace(hour=0).isoformat(),
            test_from=(self.base+timedelta(days=2)).replace(hour=0).isoformat())
        rows=self.read_rows(output)
        self.assertEqual({r['training_weight'] for r in rows if r['partition']=='train' and r['features']['sport']=='football'},{.75})
        self.assertEqual({r['training_weight'] for r in rows if r['partition']=='train' and r['features']['sport']=='basketball'},{1.5})

    def test_started_targets_and_future_observations_are_rejected_even_with_consistent_hashes(self):
        self.sample('1');self.sample('2')
        with closing(self.store.connect()) as db,db:
            for row in db.execute('SELECT id,payload FROM samples').fetchall():
                value=json.loads(row['payload'])
                if value['event']['source_event_id']=='1':value['widget_data']['match']['result']['home']=1
                else:value['widget_data']['observed_at']=(self.base-timedelta(minutes=59)).isoformat()
                db.execute('UPDATE samples SET payload=?,sha256=? WHERE id=?',(encoded(value),digest(value),row['id']))
        output=self.root/'invalid';report=prepare_training(self.state,self.finding,output=output)
        self.assertEqual(self.read_rows(output),[])
        self.assertEqual(report['eligible_events'],0)
        self.assertEqual(report['rejected'],{'widget_source_started':1,'invalid_sample':1})
