from contextlib import closing
from datetime import datetime,timedelta,timezone
import fcntl
from pathlib import Path
import shutil
import tempfile
import unittest

from collector.audit import audit
from collector.source import ROOT,load_finding
from collector.runtime import update_runtime
from collector.store import Store
from collector.statistics_link import resolve_statistics_link

load_finding(ROOT.parent/'finding')


class AuditDiagnosticsTests(unittest.TestCase):
    def setUp(self):
        self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup)
        self.state=Path(self.temp.name);self.store=Store(self.state/'dataset.sqlite3','fixture-revision')
        self.at=datetime.now(timezone.utc).isoformat()
        update_runtime(self.store,started_at=self.at,heartbeat_at=self.at,phase='filters',current_event='2')

    def market(self,identifier,sport,status):
        self.store.observation('event_attempt',identifier,self.at,{'sport':sport})
        self.store.observation('market_document',identifier,self.at,{'sport':sport,'home':'A','away':'B','competition':'Test',
            'statistics_link_status':status,'statistics_url':'https://winline.ru/stats/match/700' if status=='found' else None})

    def test_missing_links_and_inflight_event_are_not_conflated(self):
        self.market('1','hockey','missing');self.market('2','volleyball','found')
        with (self.state/'collector.lock').open('w') as lock:
            fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
            report=audit(self.state)
        hockey=report['sports']['hockey']
        self.assertEqual(hockey['latest_attempt']['state'],'skipped')
        self.assertEqual(hockey['latest_attempt']['reason'],'statistics_link_missing')
        self.assertEqual(hockey['link_statuses'],{'missing':1})
        self.assertEqual(report['sports']['volleyball']['latest_attempt']['state'],'in_progress')
        self.assertFalse(report['ready_to_reset'])

    def test_error_before_market_save_is_visible_and_historical_counts_are_separate(self):
        self.market('10','football','found')
        with closing(self.store.connect()) as db,db:
            db.execute("UPDATE observations SET received_at=? WHERE kind='market_document'",((datetime.now(timezone.utc)-timedelta(hours=1)).isoformat(),))
        self.market('11','football','found')
        self.store.observation('event_attempt','99',self.at,{'sport':'tennis','attempt_id':'attempt-99','home':'Player A','away':'Player B'})
        self.store.observation('event_diagnostic','99',self.at,{'sport':'tennis','attempt_id':'attempt-99','state':'error',
            'stage':'markets','error_type':'TimeoutError','reason':'exception_at_markets'})
        report=audit(self.state)
        self.assertEqual(report['sports']['football']['market_documents'],2)
        self.assertEqual(report['sports']['football']['current_session']['market_documents'],1)
        tennis=report['sports']['tennis']['latest_attempt']
        self.assertEqual(tennis['event_id'],'99')
        self.assertEqual(tennis['state'],'error')
        self.assertEqual(tennis['stage'],'markets')

    def test_legacy_completed_run_error_is_included(self):
        self.market('3','football','found')
        run=self.store.begin_run()
        self.store.finish_run(run,'partial',{'errors':[{'event_id':'3','type':'ValueError'}]})
        report=audit(self.state)
        latest=report['sports']['football']['latest_attempt']
        self.assertEqual(latest['state'],'error')
        self.assertEqual(latest['error_type'],'ValueError')
        self.assertEqual(report['last_run']['state'],'partial')


class StatisticsLinkTests(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        from playwright.async_api import async_playwright
        self.playwright=await async_playwright().start()
        self.browser=await self.playwright.chromium.launch(headless=True,executable_path=shutil.which('chromium'))
        self.page=await self.browser.new_page()
        async def fixture(route):
            await route.fulfill(content_type='text/html',body='<main class="ww-markets"><div class="marketbook-header--prematch">A — B</div></main>')
        await self.page.route('**/*',fixture)
        await self.page.goto('https://winline.ru/stavki/sport/futbol/example/100')

    async def asyncTearDown(self):
        await self.browser.close();await self.playwright.stop()

    async def test_delayed_dom_link_is_observed_without_guessing(self):
        await self.page.evaluate("""() => setTimeout(()=>{
            const a=document.createElement('a');a.href='/stats/match/700';a.innerText='Статистика';
            document.querySelector('.ww-markets').append(a);
        },150)""")
        links,info=await resolve_statistics_link(self.page,{},'100',wait_seconds=1)
        self.assertEqual(links,['https://winline.ru/stats/match/700'])
        self.assertEqual(info['status'],'found')
        self.assertGreaterEqual(info['waited_ms'],100)

    async def test_global_menu_link_and_radar_number_are_not_used_as_event_binding(self):
        await self.page.evaluate("""() => {
            window.apiWlb={event:{idRadar:777}};
            const a=document.createElement('a');a.href='/stats/match/999';a.innerText='Статистика';document.body.append(a);
        }""")
        links,info=await resolve_statistics_link(self.page,{},'100',wait_seconds=.1)
        self.assertEqual(links,[])
        self.assertEqual(info['status'],'missing')
        self.assertEqual(info['method'],'no_observed_link_or_control')

    async def test_ambiguity_is_preserved(self):
        original=['https://winline.ru/stats/match/700','https://winline.ru/stats/match/701']
        links,info=await resolve_statistics_link(self.page,{'statistics_links':original},'100')
        self.assertEqual(links,original)
        self.assertEqual(info['status'],'ambiguous')

    async def test_labelled_control_can_navigate_in_same_tab(self):
        await self.page.evaluate("""() => {
            const b=document.createElement('button');b.className='statistics-button';b.innerText='Статистика';
            b.onclick=()=>location.href='/stats/match/700';document.querySelector('.ww-markets').append(b);
        }""")
        original=self.page.url
        links,info=await resolve_statistics_link(self.page,{},'100')
        self.assertEqual(links,['https://winline.ru/stats/match/700'])
        self.assertEqual(info['method'],'observed_control')
        self.assertNotEqual(self.page.url,original)
