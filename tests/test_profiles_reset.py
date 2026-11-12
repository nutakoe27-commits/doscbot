import copy
from contextlib import closing
from datetime import datetime,timedelta,timezone
import fcntl
import io
import json
from pathlib import Path
import sqlite3
import tempfile
import unittest
from unittest.mock import patch
from zoneinfo import ZoneInfo

from rich.console import Console

from collector.audit import audit
from collector.monitor import Dashboard
from collector.monitor_data import MonitorReader
from collector.profiles import DEFAULT_SPORTS,PROFILES,balanced
from collector.reset import reset_data
from collector.store import Store,widget_blocker
from collector.widget_data import normalize_widget
from collector.widget_capture import WidgetCapture
from collector.widget_views import collect_widget_views
from collector.statistics_compat import from_views


class SportProfilesTests(unittest.TestCase):
    def test_rich_nonfootball_sections_survive_an_empty_legacy_projection(self):
        from collector.source import ROOT,load_finding
        load_finding(ROOT.parent/'finding')
        from matchlab.statistics import validate_statistics
        at=datetime.now(timezone.utc).isoformat()
        event={'source_event_id':'100','url':'https://winline.ru/stavki/sport/tennis/example/100',
               'statistics_url':'https://winline.ru/stats/match/700','statistics_link_status':'found'}
        widget={'profile':{'identity_verified':True},'views':{'provider_url':'https://statshub.yunai.tech/winline/ru/match/700/statistics',
            'header':'Player A — Player B','snapshots':[{'view':'statistics','card_id':'card__serve','title':'Подача и приём',
                                                      'text':'Эйсы 8 — 3; первая подача 75% — 68%', 'observed_at':at}]}}
        result=validate_statistics(from_views({'state':'empty'},event,widget),event)
        self.assertEqual(result['state'],'ok')
        self.assertEqual(result['statistics_id'],'700')
        self.assertIn('Подача и приём',[s['title'] for s in result['sections']])
        self.assertEqual(result['match_phase'],'unknown')

    def test_five_sports_keep_source_units_and_do_not_invent_football_features(self):
        for index,(sport,profile) in enumerate(PROFILES.items()):
            records=[{'endpoint':{'path':'/x/gismo/match_info_statshub/700'},'observed_at':'2026-11-12T00:00:00+00:00',
                'data':{'doc':[{'event':'match_info','data':{'match':{'_id':700,'_doctype':'generic','_sid':100+index,
                    'teams':{'home':{'_id':1,'name':'A'},'away':{'_id':2,'name':'B'}}},'sport':{'name':profile['label']},
                    'court':{'surface':'test_source_surface'}}}]}},
                {'endpoint':{'path':'/x/gismo/stats_team_stats/500/1'},'data':{'doc':[{'event':'stats_team_stats',
                    'data':{'stats':{'points':83,'shots':31,'aces':0}}}]}}]
            value=normalize_widget(records,'700',expected_sport=sport)
            self.assertTrue(value['profile']['identity_verified'])
            self.assertEqual(value['profile']['score_units'],profile['score_units'])
            self.assertEqual(value['provider_sport_id'],100+index)
            self.assertIn({'path':'court.surface','value':'test_source_surface'},value['documents'][0]['fields'])
            facts=value['documents'][1]['numeric']
            self.assertIn({'path':'stats.aces','value':0},facts)
            self.assertIn({'path':'stats.points','value':83},facts)
            self.assertEqual(value['season_statistics'],[])
            mismatch=normalize_widget(records,'700',expected_sport='tennis' if sport!='tennis' else 'hockey')
            self.assertFalse(mismatch['profile']['identity_verified'])

    def test_sports_receive_a_round_robin_share_despite_large_football_queue(self):
        events=[{'sport':'football','id':i} for i in range(100)]
        events.extend({'sport':sport,'id':i} for sport in PROFILES if sport!='football' for i in range(3))
        selected=balanced(events,10)
        self.assertEqual([e['sport'] for e in selected[:5]],list(PROFILES))
        self.assertTrue(all(sum(e['sport']==sport for e in selected)==2 for sport in PROFILES))


class ResetTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        self.base=Path(self.temp.name);self.state=self.base/'data';self.finding=self.base/'finding'
        self.store=Store(self.state/'dataset.sqlite3','fixture-revision')
        self.at=datetime.now(timezone.utc).isoformat()
        self.archive=self.state/'source/winline.sqlite3';self.archive.parent.mkdir()
        with closing(sqlite3.connect(self.archive)) as db:
            db.execute('CREATE TABLE original(value TEXT)');db.execute("INSERT INTO original VALUES ('source kept')");db.commit()
        with closing(self.store.connect()) as db,db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)",(str(self.archive),))

    def qualify(self):
        for index,sport in enumerate(PROFILES):
            identifier=str(index+1)
            widget={'event_id':identifier,'statistics_id':str(100+index),'sport':sport,'profile':{'identity_verified':True},
                'match':{'status':{'_id':0},'result':{'home':None,'away':None}},'observed_at':self.at,
                'coverage':{'reasons':[],'usable_rich_capture':True,'season_participants':['1','2'],'sections':8,'documents':10},
                'capture':{'state':'captured'},'views':{'format':'doscbot.widget-views.v2','state':'captured','snapshots':[]}}
            self.store.observation('widget_data',identifier,self.at,widget)
            event={'source_event_id':identifier,'sport':sport,'phase':'prematch','kickoff_at':(datetime.now(timezone.utc)+timedelta(hours=2)).isoformat(),'observed_at':self.at}
            stats={'source_event_id':identifier,'statistics_id':str(100+index),'state':'ok','match_phase':'prematch','observed_at':self.at,'attempted_at':self.at}
            self.assertTrue(self.store.save_sample(event,stats,{},self.at,widget=widget))

    def test_reset_is_gated_by_all_requested_sports_and_preserves_wal_bundle(self):
        before=self.store.status()
        self.assertFalse(reset_data(self.state,self.finding,'fixture-revision')['ready'])
        with self.assertRaises(ValueError):reset_data(self.state,self.finding,'fixture-revision',apply=True)
        self.assertEqual(self.store.status(),before)
        self.qualify()
        self.assertTrue(audit(self.state)['ready_to_reset'])
        # Keep an old read connection open so WAL/SHM sidecars are present.
        with closing(self.store.connect()) as reader:
            reader.execute('BEGIN');reader.execute('SELECT count(*) FROM observations').fetchone()
            result=reset_data(self.state,self.finding,'fixture-revision',apply=True)
            backup=Path(result['backup'])
            with closing(sqlite3.connect(backup/'dataset.sqlite3')) as old:
                self.assertEqual(old.execute('SELECT count(*) FROM samples').fetchone()[0],5)
            with closing(sqlite3.connect(backup/'source/winline.sqlite3')) as source:
                self.assertEqual(source.execute('SELECT value FROM original').fetchone()[0],'source kept')
        self.assertEqual(self.store.status()['observations'],{})
        self.assertEqual(self.store.status()['samples'],0)
        self.assertFalse(self.archive.exists())
        self.assertTrue((backup/'manifest.json').is_file())

    def test_active_collector_prevents_reset(self):
        self.qualify()
        with (self.state/'collector.lock').open('w') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            with self.assertRaises(ValueError):reset_data(self.state,self.finding,'fixture-revision',apply=True)
        self.assertEqual(self.store.status()['samples'],5)

    def test_failed_initialization_restores_original_files(self):
        self.qualify()
        with patch('collector.reset.Store',side_effect=RuntimeError('test initialization failure')):
            with self.assertRaises(RuntimeError):reset_data(self.state,self.finding,'fixture-revision',apply=True)
        self.assertEqual(self.store.status()['samples'],5)
        self.assertTrue(self.archive.exists())

    def test_finding_is_refused_without_opening_its_database(self):
        self.finding.mkdir();old=self.finding/'dataset.sqlite3';old.write_bytes(b'old data must stay')
        with self.assertRaises(ValueError):reset_data(self.finding,self.finding,'fixture-revision',apply=True)
        self.assertEqual(old.read_bytes(),b'old data must stay')

    def test_sport_mismatch_and_later_widget_cannot_enter_input(self):
        self.qualify()
        with closing(self.store.connect()) as db:
            payload=json.loads(db.execute('SELECT payload FROM samples LIMIT 1').fetchone()[0])
        event,stats,widget=payload['event'],payload['team_statistics'],payload['widget_data']
        bad=copy.deepcopy(widget);bad['sport']='tennis'
        self.assertEqual(widget_blocker(event,stats,bad),'widget_sport_unverified')
        bad=copy.deepcopy(widget);bad['observed_at']=(datetime.now(timezone.utc)+timedelta(hours=1)).isoformat()
        with self.assertRaises(ValueError):self.store.save_sample(event,stats,{},self.at,widget=bad)


class DetailedMonitorTests(unittest.TestCase):
    def test_scrollable_card_exposes_all_saved_statistics_and_uses_nonfootball_profile(self):
        snapshots=[{'view':'statistics','card_id':'card__serve','title':'Подача','selected':['Всего'],
                    'text':'\n'.join('Точный показатель '+str(i) for i in range(100))}]
        detail={'event_id':'1','market_document':{'sport':'tennis','home':'Player A','away':'Player B'},
                'widget_data':{'sport':'tennis','views':{'snapshots':snapshots},'coverage':{}}}
        screen=Dashboard(ZoneInfo('UTC'));screen.event_id='1'
        data={'availability':'ready','writer':False,'detail':detail,'matches':[]}
        console=Console(width=100,height=35,file=io.StringIO(),no_color=True)
        console.print(screen.render(data,100,35))
        self.assertNotIn('Сухие матчи',console.file.getvalue())
        screen.key(']',data)
        screen.detail_scroll=85
        console.file=io.StringIO();console.print(screen.render(data,100,35))
        self.assertIn('Точный показатель 99',console.file.getvalue())
        self.assertNotIn('Точный показатель 0\n',console.file.getvalue())


class AdaptiveBrowserTests(unittest.IsolatedAsyncioTestCase):
    async def test_all_five_profiles_capture_dynamic_cards_tables_and_source_documents(self):
        from playwright.async_api import async_playwright
        import shutil
        from urllib.parse import urlsplit
        async with async_playwright() as p:
            browser=await p.chromium.launch(headless=True,executable_path=shutil.which('chromium'))
            try:
                for index,(sport,profile) in enumerate(PROFILES.items()):
                    page=await browser.new_page()
                    visited=[]
                    identifier=str(700+index)
                    async def respond(route):
                        path=urlsplit(route.request.url).path
                        if 'gismo/' in path:
                            if 'match_info' in path:
                                data={'match':{'_id':int(identifier),'_doctype':'generic','_sid':100+index,
                                              'teams':{'home':{'_id':1,'name':'A'},'away':{'_id':2,'name':'B'}}},
                                      'sport':{'name':profile['label']}}
                            else:data={'stats':{'aces':0,'shots':31,'points':83}}
                            await route.fulfill(content_type='application/json',headers={'Access-Control-Allow-Origin':'*'},body=json.dumps({'doc':[{'data':data}]}))
                            return
                        view='statistics' if path.endswith('/statistics') else 'report' if path.endswith('/report') else 'overview'
                        visited.append(view)
                        base=f'/winline/ru/match/{identifier}'
                        body=f'<div data-testid="match-header">A — B · {profile["label"]}</div>'
                        body+=''.join(f'<a href="{base}{suffix}">{label}</a>' for suffix,label in (('','Обзор'),('/report','Отчёт'),('/statistics','Статистика')))
                        if view!='report':
                            body+=f'<section data-testid="card__custom_{sport}"><h3>Показатели {profile["label"]}</h3><div>'
                            body+='<button aria-selected="true">Все</button><button aria-selected="false">Дома</button></div>'
                            body+='<div><span>0</span><span>Показатель источника</span><span>3</span></div>'
                            body+='<table><thead><tr><th>Команда</th><th>Значение</th></tr></thead><tbody><tr><td>A</td><td>83</td></tr></tbody></table></section>'
                        body+='<script>for(const b of document.querySelectorAll("button")) b.onclick=()=>{for(const s of b.parentElement.children) s.setAttribute("aria-selected",String(s===b));};'
                        body+=f'fetch("https://sh.fn.yunai.tech/x/gismo/match_info_statshub/{identifier}");fetch("https://sh.fn.yunai.tech/x/gismo/stats_team_stats/500/1");</script>'
                        await route.fulfill(content_type='text/html; charset=utf-8',body=body)
                    await page.route('**/*',respond)
                    observer=WidgetCapture(page,identifier);observer.start()
                    await page.goto(f'https://statshub.yunai.tech/winline/ru/match/{identifier}')
                    views=await collect_widget_views(page,identifier,budget_seconds=15)
                    records,capture=await observer.finish()
                    self.assertEqual(views['state'],'captured',views['missing'])
                    self.assertEqual(visited[:3],['overview','report','statistics'])
                    self.assertEqual(sum(g['captured'] for g in views['groups'].values()),4)
                    self.assertTrue(any(s['tables'] for s in views['snapshots']))
                    self.assertTrue(any(r['home']==0 for s in views['snapshots'] for r in s['rows']))
                    value=normalize_widget(records,identifier,expected_sport=sport,views=views)
                    self.assertTrue(value['profile']['identity_verified'])
                    self.assertTrue(value['coverage']['usable_rich_capture'])
                    self.assertEqual(capture['state'],'captured')
                    await page.close()
            finally:await browser.close()
