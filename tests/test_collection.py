"""Temporary synthetic inputs exercise native finding parsers and persistence."""

from contextlib import closing
from datetime import datetime, timedelta, timezone
import json
import asyncio
from html import escape
import os
from pathlib import Path
import shutil
import sqlite3
import tempfile
import unittest
from unittest.mock import AsyncMock, patch

from collector.source import ROOT, candidates, load_finding, sync_archive
from collector.store import Store, sample_blocker

FINDING, REVISION = load_finding(ROOT.parent / "finding")
from winline.storage import connect, save_results, save_snapshot
from winline.team_context import build_context
from matchlab.statistics import validate_statistics


class CollectionTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.folder = Path(self.temp.name)
        self.store = Store(self.folder / "dataset.sqlite3", REVISION)
        self.source = self.folder / "source.sqlite3"
        self.db = connect(self.source)
        self.addCleanup(self.db.close)
        self.at = datetime.now(timezone.utc).replace(microsecond=0)
        self.stamp = self.at.isoformat()
        self.capture = self.db.execute("INSERT INTO captures(started_at,url) VALUES (?,?)",
                                       (self.stamp, "https://winline.ru/")).lastrowid
        self.db.commit()
        self.data = {
            "sports": [{"id": 1, "name": "Футбол"}], "countries": [], "championships": [],
            "events": [{"id": 100, "idSport": 1, "idRadar": 700, "isLive": False,
                        "members": ["Команда А", "Команда Б"],
                        "date": (self.at + timedelta(days=1)).isoformat()}],
            "lines": [{"id": 10, "idEvent": 100, "isLive": False, "V": [2.0, 3.0, 4.0],
                       "idTipMarket": 1, "tipLine": {"R": ["П1", "X", "П2"], "freeTextR": "Исход"}}],
        }
        save_snapshot(self.db, self.capture, self.data, self.stamp)

    def test_incremental_native_history_and_repeat_delivery(self):
        first = sync_archive(self.store, self.source)
        self.assertEqual((first["event"], first["market"]), (1, 1))
        self.assertEqual(sum(sync_archive(self.store, self.source).values()), 0)
        self.data["lines"][0]["V"][0] = 2.1
        save_snapshot(self.db, self.capture, self.data, (self.at + timedelta(seconds=1)).isoformat())
        changed = sync_archive(self.store, self.source)
        self.assertEqual((changed["event"], changed["market"]), (0, 1))
        with closing(self.store.connect()) as db:
            values = [json.loads(r[0])["V"][0] for r in db.execute(
                "SELECT payload FROM observations WHERE kind='market' ORDER BY id")]
        self.assertEqual(values, [2.0, 2.1])

    def test_native_results_keep_sport_and_are_not_features(self):
        result = {"idRadar": 600, "member1": "Команда А", "member2": "Команда В",
                  "date": (self.at - timedelta(days=1)).isoformat(), "score": "2:1"}
        save_results(self.db, self.capture, 1, 0, [result])
        sync_archive(self.store, self.source)
        with closing(self.store.connect()) as db:
            row = db.execute("SELECT payload FROM observations WHERE kind='result'").fetchone()
        self.assertEqual(json.loads(row[0]), {"sport_id": 1, "record": result})
        self.assertEqual(self.store.status()["samples"], 0)

    def test_corrupt_batch_rolls_back_observations_and_checkpoint(self):
        self.db.execute("UPDATE line_history SET raw_json='not-json'")
        self.db.commit()
        with self.assertRaises(json.JSONDecodeError):
            sync_archive(self.store, self.source)
        with closing(self.store.connect()) as db:
            self.assertIsNone(db.execute("SELECT * FROM cursors WHERE table_name='line_history'").fetchone())
            self.assertEqual(db.execute("SELECT count(*) FROM observations WHERE kind='market'").fetchone()[0], 0)

    def test_source_replacement_is_detected(self):
        sync_archive(self.store, self.source)
        self.db.execute("UPDATE event_history SET raw_json='{}'")
        self.db.commit()
        with self.assertRaisesRegex(ValueError, "replaced"):
            sync_archive(self.store, self.source)

    def test_source_is_read_only_and_native_context_filters_future_versions(self):
        context = build_context(100, self.source, as_of=self.stamp)
        self.assertEqual(context["event"]["id"], 100)
        self.data["lines"][0]["V"][0] = 9.0
        save_snapshot(self.db, self.capture, self.data, (self.at + timedelta(hours=1)).isoformat())
        context = build_context(100, self.source, as_of=self.stamp)
        self.assertEqual(context["markets"][0]["values"][0], 2.0)
        from collector.source import source_connection
        with closing(source_connection(self.source)) as db:
            with self.assertRaises(sqlite3.OperationalError):
                db.execute("DELETE FROM events")

    def sample_inputs(self):
        event = {"source_event_id": "100", "phase": "prematch", "sport": "football",
                 "kickoff_at": (self.at + timedelta(days=1)).isoformat(), "observed_at": self.stamp,
                 "final_result": {"score": "5:0"}, "markets": []}
        statistics = {"source_event_id": "100", "state": "ok", "match_phase": "prematch",
                      "observed_at": self.stamp, "attempted_at": self.stamp, "sections": []}
        return event, statistics

    def test_samples_exclude_target_results_and_deduplicate(self):
        event, statistics = self.sample_inputs()
        self.assertTrue(self.store.save_sample(event, statistics, {}, self.stamp))
        self.assertFalse(self.store.save_sample(event, statistics, {}, self.stamp))
        export = self.folder / "samples.jsonl"
        self.assertEqual(self.store.export(export), 1)
        self.assertNotIn("final_result", json.loads(export.read_text())["payload"]["event"])
        with self.assertRaises(ValueError):
            self.store.export(export)

    def bound_unknown(self):
        event, statistics = self.sample_inputs()
        event.update(url="https://winline.ru/stavki/sport/futbol/100",
                     home="Команда А", away="Команда Б",
                     statistics_url="https://winline.ru/stats/match/72334410",
                     statistics_link_status="found")
        statistics.update(match_phase="unknown", binding="observed_event_link", statistics_id="72334410",
                          statistics_url=event["statistics_url"], event_url=event["url"])
        return event, statistics

    def test_unknown_widget_phase_requires_binding_and_remains_unknown(self):
        event, statistics = self.bound_unknown()
        for bad in [{**statistics, "binding": None}, {**statistics, "event_url": "https://winline.ru/stavki/sport/futbol/999"},
                    {**statistics, "statistics_url": "https://winline.ru/stats/match/999"}]:
            self.assertEqual(sample_blocker(event, bad, self.stamp), "statistics_phase_unconfirmed_binding")
            self.assertFalse(self.store.save_sample(event, bad, {}, self.stamp))
        self.assertTrue(self.store.save_sample(event, statistics, {}, self.stamp))
        with closing(self.store.connect()) as db:
            payload = json.loads(db.execute("SELECT payload FROM samples").fetchone()[0])
        self.assertEqual(payload["team_statistics"]["match_phase"], "unknown")
        self.assertEqual(payload["eligibility"]["basis"], "observed_event_link_before_kickoff")

    def test_provider_explicit_started_status_overrides_unknown_header(self):
        event, statistics = self.bound_unknown()
        for state in ["finished", "live", {"_id": 100}]:
            value = {**statistics, "structured_feed": {"match": {"status": state}}}
            self.assertEqual(sample_blocker(event, value, self.stamp), "statistics_source_not_prematch")
            self.assertFalse(self.store.save_sample(event, value, {}, self.stamp))

    def test_materialize_uses_first_receipt_and_old_versions_without_network(self):
        from collector.materialize import materialize
        event, statistics = self.bound_unknown()
        with closing(self.store.connect()) as db, db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)", (str(self.source),))
        self.store.observation("market_document", "100", self.stamp, event)
        self.store.observation("team_statistics", "100", self.stamp, statistics)
        self.data["lines"][0]["V"][0] = 9.0
        save_snapshot(self.db, self.capture, self.data, (self.at + timedelta(hours=1)).isoformat())
        dry = materialize(self.store, self.source, "100", dry_run=True)
        self.assertEqual(dry["reasons"], {"eligible": 1})
        self.assertEqual(self.store.status()["samples"], 0)
        self.assertEqual(materialize(self.store, self.source, "100")["saved"], 1)
        self.assertEqual(materialize(self.store, self.source, "100")["reasons"], {"duplicate": 1})
        with closing(self.store.connect()) as db:
            payload = json.loads(db.execute("SELECT payload FROM samples").fetchone()[0])
            received = db.execute("SELECT received_at FROM observations WHERE kind='team_statistics'").fetchone()[0]
        self.assertEqual(payload["as_of"], received)
        self.assertEqual(payload["archive_history"]["markets"][0]["values"][0], 2.0)

    def test_later_market_document_cannot_be_backdated_during_materialization(self):
        from collector.materialize import materialize
        event, statistics = self.bound_unknown()
        with closing(self.store.connect()) as db, db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)", (str(self.source),))
        self.store.observation("team_statistics", "100", self.stamp, statistics)
        self.store.observation("market_document", "100", self.stamp, event)
        result = materialize(self.store, self.source, "100")
        self.assertEqual(result["reasons"], {"market_document_missing": 1})
        self.assertEqual(self.store.status()["samples"], 0)

    def test_no_samples_for_started_missing_finished_stale_or_wrong_team_data(self):
        event, statistics = self.sample_inputs()
        for variant in [{**statistics, "match_phase": "finished"}, {**statistics, "state": "empty"}, None,
                        {**statistics, "capture_kind": "fast"},
                        {**statistics, "observed_at": (self.at - timedelta(hours=2)).isoformat()}]:
            self.assertFalse(self.store.save_sample(event, variant, {}, self.stamp))
        self.assertFalse(self.store.save_sample({**event, "kickoff_at": self.stamp}, statistics, {}, self.stamp))
        with self.assertRaises(ValueError):
            self.store.save_sample(event, {**statistics, "source_event_id": "101"}, {}, self.stamp)
        with self.assertRaises(ValueError):
            self.store.save_sample(event, {**statistics, "observed_at": (self.at + timedelta(seconds=1)).isoformat()}, {}, self.stamp)

    def test_statistics_versions_are_append_only(self):
        self.store.observation("team_statistics", "100", self.stamp, {"state": "ok", "value": 1})
        self.store.observation("team_statistics", "100", self.stamp, {"state": "ok", "value": 2})
        self.assertEqual(self.store.status()["observations"]["team_statistics"], 2)

    def test_existing_database_is_refused_without_modification(self):
        old = self.folder / "old.sqlite3"
        with closing(sqlite3.connect(old)) as db, db:
            db.execute("CREATE TABLE old_data (id INTEGER)")
            db.execute("INSERT INTO old_data VALUES (123)")
        before = old.read_bytes()
        with self.assertRaisesRegex(ValueError, "Refusing"):
            Store(old, REVISION)
        self.assertEqual(old.read_bytes(), before)

    def test_refuse_preexisting_archive_before_calling_parser(self):
        from collector.live import cycle
        state = self.folder / "fresh"
        (state / "source").mkdir(parents=True)
        (state / "source" / "winline.sqlite3").write_text("OLD DATA")
        with patch("collector.live.run_source") as run:
            with self.assertRaisesRegex(ValueError, "pre-existing"):
                cycle(self.store, state, FINDING, None, "football", 1, 0)
            run.assert_not_called()
        self.assertEqual((state / "source" / "winline.sqlite3").read_text(), "OLD DATA")

    def test_scheduler_rotates_previously_attempted_events(self):
        self.data["events"].append({**self.data["events"][0], "id": 101})
        save_snapshot(self.db, self.capture, self.data, self.stamp)
        picked = candidates(self.source, "football", 1, self.stamp, {"100": self.stamp})
        self.assertEqual(picked[0]["source_event_id"], "101")

    def test_partial_source_failure_still_collects_fresh_details(self):
        from collector.live import cycle
        state = self.folder / "partial"
        def partial_source(*args):
            with closing(connect(state / "source/winline.sqlite3")) as db:
                capture = db.execute("INSERT INTO captures(started_at,url) VALUES (?,?)",
                                     (self.stamp, "https://winline.ru/")).lastrowid
                db.commit()
                save_snapshot(db, capture, self.data, self.stamp)
            return 1
        detail = {"selected": 1, "samples": 1, "errors": []}
        with patch("collector.live.run_source", side_effect=partial_source), \
             patch("collector.live.collect_details", new_callable=AsyncMock, return_value=detail) as collect:
            self.assertFalse(cycle(self.store, state, FINDING, None, "football", 1, 0, headed=True))
            collect.assert_awaited_once()
            self.assertTrue(collect.await_args.kwargs["headed"])
        self.assertEqual(self.store.status()["last_run"]["state"], "partial")
        self.assertEqual(self.store.status()["observations"]["event"], 1)

    def test_failed_run_cannot_reuse_a_previous_snapshot(self):
        from collector.live import cycle
        state = self.folder / "stale"
        archive = state / "source/winline.sqlite3"
        with closing(connect(archive)) as db:
            capture = db.execute("INSERT INTO captures(started_at,url) VALUES (?,?)",
                                 (self.stamp, "https://winline.ru/")).lastrowid
            db.commit()
            save_snapshot(db, capture, self.data, self.stamp)
        with closing(self.store.connect()) as db, db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)", (str(archive),))
        with patch("collector.live.run_source", return_value=1), \
             patch("collector.live.collect_details", new_callable=AsyncMock) as collect:
            self.assertFalse(cycle(self.store, state, FINDING, None, "football", 1, 0))
            collect.assert_not_awaited()
        last = self.store.status()["last_run"]
        self.assertEqual(last["state"], "source_error")
        self.assertEqual(json.loads(last["details"])["reason"], "no_new_source_snapshot")

    def test_recorded_finished_statistics_never_become_prematch_samples(self):
        fixture = json.loads((FINDING / "tests/fixtures/team_statistics_projection.json").read_text())
        event, _ = self.sample_inputs()
        event.update(url="https://winline.ru/stavki/sport/futbol/100",
                     statistics_url="https://winline.ru/stats/match/72334410", statistics_link_status="found")
        raw = {"format": "matchlab.event-statistics.v1", "source_event_id": "100", "state": "ok",
               "event_url": event["url"], "statistics_url": event["statistics_url"],
               "observed_at": self.stamp, "attempted_at": self.stamp, **fixture}
        validated = validate_statistics(raw, event)
        self.assertEqual(validated["match_phase"], "finished")
        self.assertFalse(self.store.save_sample(event, validated, {}, self.stamp))

    def test_chromium_executes_native_statistics_extractor_offline(self):
        from playwright.async_api import async_playwright
        from matchlab.statistics import STATS_SCRIPT
        fixture = json.loads((FINDING / "tests/fixtures/team_statistics_projection.json").read_text())
        html = '<div data-testid="match-header">' + escape(fixture["header"]).replace("\n", "<br>") + '</div>'
        for section in fixture["sections"]:
            html += '<section><h3>' + escape(section["title"]) + '</h3><div>'
            html += escape(section["text"]).replace("\n", "<br>")
            html += '<form>private-test-marker</form></div></section>'
        async def extract():
            from collector.live import browser_options
            async with async_playwright() as p:
                executable = os.environ.get("CHROMIUM_EXECUTABLE") or shutil.which("chromium") or shutil.which("chromium-browser")
                options = browser_options(executable, headed=os.environ.get("DOSCBOT_TEST_HEADED") == "1")
                browser = await p.chromium.launch(**options)
                try:
                    page = await browser.new_page()
                    await page.route("**/*", lambda route: route.abort())
                    await page.set_content(html)
                    return await page.evaluate(STATS_SCRIPT)
                finally:
                    await browser.close()
        parsed = asyncio.run(extract())
        self.assertIn("Закончен", parsed["header"])
        self.assertEqual(len(parsed["sections"]), len(fixture["sections"]))
        self.assertNotIn("private-test-marker", json.dumps(parsed["sections"]))


if __name__ == "__main__":
    unittest.main()
