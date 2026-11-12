"""Result recovery, session isolation, scheduling and truthful completion states."""

import asyncio
from contextlib import closing
from datetime import datetime, timedelta, timezone
import json
import os
from pathlib import Path
import shutil
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import AsyncMock, patch

from collector.source import ROOT, load_finding
from collector.store import Store

_, REVISION = load_finding(Path(os.environ.get("FINDING_SOURCE_DIR", ROOT.parent / "finding")))
from collector import results
from winline.storage import connect


class ResultTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        folder = Path(self.temp.name)
        self.store = Store(folder / "dataset.sqlite3", REVISION)
        self.archive = folder / "source/winline.sqlite3"
        connect(self.archive).close()
        with closing(self.store.connect()) as db, db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)", (str(self.archive),))
        self.at = datetime.now(timezone.utc).replace(microsecond=0)
        self.day = int(self.at.timestamp() * 1000)
        self.catalog = {"sports": {"1": {"name": "Футбол", "dates": [self.day, self.day - 86400000]},
                                   "2": {"name": "Хоккей", "dates": [self.day]}}}
        self.requests = []
        self.contexts = []
        self.replies = {}

    def run_collection(self, **kwargs):
        test = self
        class Context:
            closed = False
            async def close(self):
                self.closed = True
        class Page:
            async def evaluate(self, script, arguments):
                pair = (arguments["sport"], arguments["day"])
                test.requests.append(pair)
                reply = test.replies.get(pair, [])
                if isinstance(reply, Exception):
                    raise reply
                return reply
        async def opened(browser):
            if test.contexts:
                test.assertTrue(test.contexts[-1].closed, "Previous result context must close before next request")
            context = Context()
            test.contexts.append(context)
            return context, Page()
        browser = SimpleNamespace(close=AsyncMock())
        manager = SimpleNamespace(
            __aenter__=AsyncMock(return_value=SimpleNamespace(chromium=SimpleNamespace(launch=AsyncMock(return_value=browser)))),
            __aexit__=AsyncMock(return_value=False))
        class Playwright:
            async def __aenter__(self): return await manager.__aenter__()
            async def __aexit__(self, *args): return await manager.__aexit__(*args)
        with patch.object(results, "async_playwright", return_value=Playwright()), \
             patch.object(results, "catalog", new_callable=AsyncMock, return_value=self.catalog), \
             patch.object(results, "open_session", side_effect=opened), \
             patch.object(results, "now", return_value=self.at.isoformat()), \
             patch.object(results.asyncio, "sleep", new_callable=AsyncMock):
            value = asyncio.run(results.collect_results(self.store, self.archive, sport="football", days=2,
                                                        options={}, **kwargs))
        browser.close.assert_awaited_once()
        return value

    def test_timeout_closes_session_and_next_day_still_saves_correct_result(self):
        self.replies[(1, self.day)] = results.Error("Result request timed out")
        row = {"idRadar": 500, "member1": "Команда А", "member2": "Команда Б", "score": "2:1",
               "date": (self.at - timedelta(days=1)).isoformat()}
        self.replies[(1, self.day - 86400000)] = [row]
        report = self.run_collection()
        self.assertEqual((report["attempted"], report["successful"], report["failed"]), (2, 1, 1))
        self.assertEqual(report["state"], "partial")
        self.assertEqual(report["new_versions"], 1)
        self.assertTrue(all(c.closed for c in self.contexts))
        with closing(connect(self.archive)) as db:
            saved = db.execute("SELECT sport_id,source_day_ms,error FROM result_requests ORDER BY source_day_ms").fetchall()
            self.assertEqual(tuple(saved[0]), (1, self.day - 86400000, None))
            self.assertEqual(tuple(saved[1]), (1, self.day, "result_timeout"))
            self.assertEqual(json.loads(db.execute("SELECT raw_json FROM results").fetchone()[0]), row)

    def test_default_sport_does_not_request_unrelated_sports_and_success_is_cached(self):
        first = self.run_collection()
        self.assertEqual(first["available_pairs"], 2)
        self.assertEqual(first["successful"], 2)
        self.assertTrue(all(sport == 1 for sport, _ in self.requests))
        self.requests.clear()
        cached = self.run_collection()
        self.assertEqual(cached["planned"], 0)
        self.assertEqual(self.requests, [])

    def test_failed_front_job_does_not_starve_next_day_on_next_cycle(self):
        self.replies[(1, self.day)] = results.Error("Result request timed out")
        first = self.run_collection(limit=1)
        self.assertEqual(first["failed"], 1)
        self.assertEqual(first["deferred"], 1)
        self.requests.clear()
        second = self.run_collection(limit=1)
        self.assertEqual(self.requests, [(1, self.day - 86400000)])
        self.assertEqual(second["successful"], 1)

    def test_confirmed_access_denial_stops_remaining_requests(self):
        self.replies[(1, self.day)] = results.SourceDenied("source_http_403")
        report = self.run_collection()
        self.assertEqual(report["attempted"], 1)
        self.assertEqual(report["failed"], 1)
        self.assertEqual(report["requests"][0]["error"], "source_http_403")
        self.assertEqual(len(self.contexts), 1)

    def test_invalid_reply_is_not_stored_as_results(self):
        self.replies[(1, self.day)] = {"unexpected": "object"}
        report = self.run_collection()
        self.assertEqual(report["failed"], 1)
        self.assertEqual(report["new_versions"], 0)

    def test_exhausted_cycle_budget_is_not_reported_as_completed_work(self):
        report = self.run_collection(budget_seconds=0)
        self.assertEqual(report["planned"], 2)
        self.assertEqual(report["attempted"], 0)
        self.assertEqual(report["deferred"], 2)
        self.assertEqual(report["state"], "partial")
        self.assertEqual(self.requests, [])

    def test_unresolved_followed_match_requests_an_older_available_day(self):
        day = (self.day//86400000-4)*86400000
        kickoff = datetime.fromtimestamp(day/1000,timezone.utc)+timedelta(hours=12)
        self.catalog["sports"]["1"]["dates"].append(day)
        self.store.observation("market_document","older",self.at.isoformat(),
            {"sport":"football","kickoff_at":kickoff.isoformat()})
        report = self.run_collection()
        self.assertEqual(report["followup_pairs"],1)
        self.assertIn((1,day),self.requests)
        self.store.observation("event_outcome","older",self.at.isoformat(),{"score_text":"1:0"})
        self.requests.clear()
        report = self.run_collection()
        self.assertEqual(report["followup_pairs"],0)
        self.assertNotIn((1,day),self.requests)

    def test_followup_does_not_invent_unavailable_days_or_other_sports(self):
        day = (self.day//86400000-4)*86400000
        kickoff = datetime.fromtimestamp(day/1000,timezone.utc)+timedelta(hours=12)
        self.store.observation("market_document","not-offered",self.at.isoformat(),
            {"sport":"football","kickoff_at":kickoff.isoformat()})
        self.catalog["sports"]["2"]["dates"].append(day)
        self.store.observation("market_document","other-sport",self.at.isoformat(),
            {"sport":"hockey","kickoff_at":kickoff.isoformat()})
        report = self.run_collection()
        self.assertEqual(report["followup_pairs"],0)
        self.assertTrue(all(pair[0]==1 for pair in self.requests))

    def test_preexisting_unowned_database_is_not_opened(self):
        other = Path(self.temp.name) / "unowned.sqlite3"
        other.write_bytes(b"do not modify")
        with self.assertRaises(ValueError):
            asyncio.run(results.collect_results(self.store, other, sport="football", days=2, options={}))
        self.assertEqual(other.read_bytes(), b"do not modify")

    def test_archive_timeout_does_not_slow_successful_primary_collection(self):
        from collector.__main__ import needs_backoff
        primary = {"source_exit_code": 0, "errors": [],
                   "results": {"state": "partial", "requests": [{"error": "result_timeout"}]}}
        status = {"last_run": {"state": "partial", "details": json.dumps(primary)}}
        self.assertFalse(needs_backoff(status))
        primary["source_exit_code"] = 1
        self.assertTrue(needs_backoff({"last_run": {"details": json.dumps(primary)}}))
        primary["source_exit_code"] = 0
        primary["results"]["requests"][0]["error"] = "source_http_403"
        self.assertTrue(needs_backoff({"last_run": {"details": json.dumps(primary)}}))

    def test_late_browser_reply_cannot_reach_the_next_request_context(self):
        from playwright.async_api import async_playwright
        from winline.browser import INSTALL
        contexts = []
        async def run():
            async with async_playwright() as p:
                executable = os.environ.get("CHROMIUM_EXECUTABLE") or shutil.which("chromium") or shutil.which("chromium-browser")
                browser = await p.chromium.launch(headless=True, **({"executable_path": executable} if executable else {}))
                async def opened(browser):
                    context = await browser.new_context()
                    contexts.append(context)
                    page = await context.new_page()
                    await page.set_content("<html><body>Offline result callback fixture</body></html>")
                    first = len(contexts) == 1
                    await page.evaluate("""({delay,label}) => {
                        const handlers={};
                        window.apiWlb={API_PARAMETS:{CLIENT_EVENTS:{GET_RESULT_PREDATA:'pre',GET_RESULT:'result',GET_NEW_PLUSES:'pluses'}},
                          client:{addEventListener:(fn,name)=>{handlers[name]=fn},websocket:{addEventListener:()=>{}}},
                          getService:()=>({setPreData:()=>setTimeout(()=>handlers.result([{label}]),delay)})};
                    }""", {"delay": 250 if first else 60, "label": "late-first" if first else "correct-second"})
                    await page.evaluate(INSTALL)
                    return context, page
                try:
                    with patch.object(results, "open_session", side_effect=opened):
                        with self.assertRaises(results.Error):
                            await results.request_pair(browser, (1, self.day, "Футбол"), 0.02)
                        reply = await results.request_pair(browser, (2, self.day, "Хоккей"), 1)
                        self.assertEqual(reply, [{"label": "correct-second"}])
                        self.assertTrue(all(not c.pages for c in contexts))
                finally:
                    await browser.close()
        asyncio.run(run())


if __name__ == "__main__":
    unittest.main()
