from contextlib import closing
from datetime import timedelta
import copy
import hashlib
import importlib.util
import json
import sqlite3
import unittest
from unittest.mock import patch

from collector.model_evaluation import make_forecasts,owned_database,score_forecasts
from collector.store import digest,encoded,instant
from collector.training import prepare_training
from collector.train_all import train_all
from collector.train_baseline import train_baseline
if __package__:
    from . import test_training_server as fixtures
else:
    import test_training_server as fixtures


class StateGuardTests(fixtures.TrainingFixture):
    def test_foreign_missing_redirected_and_finding_states_are_not_opened(self):
        missing=self.root/'missing'
        with self.assertRaises(ValueError),owned_database(missing,self.finding):pass
        self.assertFalse(missing.exists())
        self.finding.mkdir();(self.finding/'dataset.sqlite3').write_bytes(b'private old data')
        with patch('collector.model_evaluation.readonly',side_effect=AssertionError('must not open finding')):
            with self.assertRaises(ValueError),owned_database(self.finding,self.finding):pass
        foreign=self.root/'foreign';foreign.mkdir()
        with closing(sqlite3.connect(foreign/'dataset.sqlite3')) as db:db.execute('CREATE TABLE unrelated(value TEXT)')
        with self.assertRaises(ValueError),owned_database(foreign,self.finding):pass
        redirected=self.root/'redirected';redirected.mkdir();(redirected/'dataset.sqlite3').symlink_to(self.state/'dataset.sqlite3')
        with self.assertRaises(ValueError),owned_database(redirected,self.finding):pass


@unittest.skipUnless(importlib.util.find_spec('catboost'),'Optional: real frozen-model evaluation in .venv-training')
class EvaluationTests(fixtures.TrainingFixture):
    dataset=fixtures.BaselineTests.dataset

    def model(self):
        temporal=self.dataset();pilot=self.root/'pilot'
        train_baseline(temporal,self.finding,pilot,threads=1,iterations=20,depth=3)
        dataset=self.root/'all-data';prepare_training(self.state,self.finding,output=dataset)
        model=self.root/'all-model';report=train_all(dataset,self.finding,pilot,model,threads=1)
        self.ready=instant(report['finished_at'])
        return model

    def new_event(self,identifier,sport='football',*,kickoff=3,lead=120,result=False):
        self.base=self.ready+timedelta(hours=kickoff)
        event,stats,widget,outcome=self.sample(identifier,sport,lead=lead,with_result=result)
        native={'id':int(identifier),'idSport':self.native[sport],'idRadar':int(widget['statistics_id']),
                'members':[event['home'],event['away']],'date':event['kickoff_at'],'isLive':False}
        with patch('collector.store.now',return_value=event['observed_at']):
            self.store.observation('event',identifier,event['observed_at'],native)
        return event,stats,widget,outcome

    def later_outcome(self,event,score='1:2 (0:0 1:2)'):
        outcome={'format':'doscbot.event-outcome.v1','event_id':event['source_event_id'],
                 'sport_id':self.native[event['sport']],'home':event['home'],'away':event['away'],
                 'kickoff_at':event['kickoff_at'],'score_text':score,'method':'sport_radar_names_time'}
        self.outcome(event['source_event_id'],outcome,instant(event['kickoff_at'])+timedelta(hours=3))

    def test_historical_holdout_excludes_training_aliases_and_pre_model_samples(self):
        model=self.model()
        self.new_event('900',kickoff=2,lead=60,result=True)
        self.new_event('901',kickoff=2,lead=60,result=True)
        with closing(self.store.connect()) as db,db:
            row=db.execute("SELECT id,payload FROM samples WHERE event_id='901'").fetchone()
            payload=json.loads(row['payload']);payload['widget_data']['statistics_id']='1000';payload['team_statistics']['statistics_id']='1000'
            db.execute('UPDATE samples SET payload=?,sha256=? WHERE id=?',(encoded(payload),digest(payload),row['id']))
        self.new_event('0',kickoff=2,lead=60,result=True)
        self.new_event('902',kickoff=-1,lead=60,result=True)
        self.new_event('903',kickoff=2,lead=60,result=True)
        with closing(self.store.connect()) as db,db:
            row=db.execute("SELECT id,payload FROM samples WHERE event_id='903'").fetchone()
            payload=json.loads(row['payload']);payload['widget_data']['statistics_id']='100900';payload['team_statistics']['statistics_id']='100900'
            db.execute('UPDATE samples SET payload=?,sha256=? WHERE id=?',(encoded(payload),digest(payload),row['id']))
        before=(self.state/'dataset.sqlite3').read_bytes();model_bytes=(model/'model.cbm').read_bytes()
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=7)).isoformat()):
            report=make_forecasts(self.state,self.finding,model,self.root/'history',retrospective=True,threads=1)
        self.assertEqual(report['scored_events'],1)
        self.assertEqual(report['scored_rows'],2)
        self.assertEqual(report['mode'],'retrospective_temporal_holdout')
        self.assertEqual(report['skipped']['trained_event'],1)
        self.assertEqual(report['skipped']['trained_statistics_id'],1)
        self.assertEqual(report['skipped']['duplicate_statistics_id'],1)
        self.assertEqual(report['evaluation']['overall']['events'],1)
        self.assertEqual((self.state/'dataset.sqlite3').read_bytes(),before)
        self.assertEqual((model/'model.cbm').read_bytes(),model_bytes)

    def test_forecasts_are_frozen_before_kickoff_and_scored_from_original_features(self):
        model=self.model();event,_,_,_=self.new_event('910')
        forecast=self.root/'forward';time=(self.ready+timedelta(hours=1,minutes=1)).isoformat()
        with patch('collector.model_evaluation.now',return_value=time),patch('catboost.CatBoostClassifier.fit',side_effect=AssertionError('no retraining')):
            report=make_forecasts(self.state,self.finding,model,forecast,threads=1)
        self.assertEqual(report['mode'],'prospective');self.assertEqual(report['prediction_events'],1)
        frozen=(forecast/'predictions.jsonl').read_bytes()
        with patch('collector.model_evaluation.now',return_value=time):
            waiting=score_forecasts(self.state,self.finding,forecast,self.root/'waiting')
        self.assertEqual(waiting['scored_events'],0)
        self.assertIsNone(waiting['evaluation'])
        self.later_outcome(event)
        # Later source captures do not replace the original recorded inputs.
        self.new_event('910',kickoff=3,lead=30,result=False)
        with patch('collector.model_evaluation.now',return_value=time):
            make_forecasts(self.state,self.finding,model,self.root/'same-time',threads=1)
        self.assertEqual((self.root/'same-time'/'predictions.jsonl').read_bytes(),frozen)
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=7)).isoformat()):
            scored=score_forecasts(self.state,self.finding,forecast,self.root/'scored')
        self.assertEqual(scored['scored_events'],1)
        self.assertEqual(scored['mode'],'prospective')
        self.assertFalse(scored['validated_for_betting'])
        self.assertEqual((forecast/'predictions.jsonl').read_bytes(),frozen)

    def test_live_matches_and_revised_earlier_start_are_not_valid_prospective_scores(self):
        model=self.model();event,_,widget,_=self.new_event('940')
        live,_,live_widget,_=self.new_event('941')
        native={'id':941,'idSport':1,'idRadar':int(live_widget['statistics_id']),
                'members':[live['home'],live['away']],'date':live['kickoff_at'],'isLive':True}
        native_at=self.ready+timedelta(hours=1,minutes=30)
        with patch('collector.store.now',return_value=native_at.isoformat()):
            self.store.observation('event','941',native_at.isoformat(),native)
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=1,minutes=59)).isoformat()):
            predicted=make_forecasts(self.state,self.finding,model,self.root/'forward',threads=1)
        self.assertEqual(predicted['prediction_events'],1)
        self.assertEqual(predicted['skipped']['current_schedule_or_identity_changed'],1)
        event={**event,'kickoff_at':(self.ready+timedelta(hours=2,minutes=50)).isoformat()}
        self.later_outcome(event)
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=7)).isoformat()):
            score=score_forecasts(self.state,self.finding,self.root/'forward',self.root/'score')
        self.assertEqual(score['scored_events'],0)
        self.assertEqual(score['unscored_reasons'],{'revised_start_violates_prediction_horizon':1})

    def test_changed_forecasts_late_timestamps_and_overwrites_are_rejected(self):
        model=self.model();self.new_event('920');forecast=self.root/'forward'
        time=(self.ready+timedelta(hours=1,minutes=1)).isoformat()
        with patch('collector.model_evaluation.now',return_value=time):
            make_forecasts(self.state,self.finding,model,forecast,threads=1)
            with self.assertRaises(ValueError):make_forecasts(self.state,self.finding,model,forecast,threads=1)
        path=forecast/'predictions.jsonl';packets=[json.loads(line) for line in path.read_text().splitlines()]
        packets[0]['rows'][0]['probability']=.999
        path.write_text(encoded(packets[0])+'\n')
        with self.assertRaises(ValueError):score_forecasts(self.state,self.finding,forecast,self.root/'bad')
        packets[0]['issued_at']=(self.ready+timedelta(hours=4)).isoformat();path.write_text(encoded(packets[0])+'\n')
        report=json.loads((forecast/'report.json').read_text());report['predictions_sha256']=hashlib.sha256(path.read_bytes()).hexdigest()
        (forecast/'report.json').write_text(json.dumps(report))
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=7)).isoformat()):
            with self.assertRaises(ValueError):score_forecasts(self.state,self.finding,forecast,self.root/'late')
        early=(self.ready+timedelta(hours=1,minutes=59,seconds=59)).isoformat()
        late=(self.ready+timedelta(hours=2,seconds=1)).isoformat()
        with patch('collector.model_evaluation.now',side_effect=[early,early,late]):
            with self.assertRaises(ValueError):make_forecasts(self.state,self.finding,model,self.root/'late-save',threads=1)
        self.assertFalse((self.root/'late-save').exists())

    def test_no_new_matches_and_missing_training_source_do_not_produce_fake_quality(self):
        model=self.model()
        with patch('collector.model_evaluation.now',return_value=(self.ready+timedelta(hours=1)).isoformat()):
            report=make_forecasts(self.state,self.finding,model,self.root/'empty',retrospective=True,threads=1)
        self.assertEqual(report['scored_events'],0);self.assertIsNone(report['evaluation'])
        with closing(self.store.connect()) as db,db:db.execute("DELETE FROM samples WHERE event_id='0'")
        with self.assertRaises(ValueError):make_forecasts(self.state,self.finding,model,self.root/'missing',threads=1)

    def test_model_corruption_and_prediction_inside_source_are_rejected(self):
        model=self.model();self.new_event('930')
        with self.assertRaises(ValueError):make_forecasts(self.state,self.finding,model,self.state/'bad',threads=1)
        with (model/'model.cbm').open('ab') as f:f.write(b'corruption')
        with self.assertRaises(ValueError):make_forecasts(self.state,self.finding,model,self.root/'changed',threads=1)
