import copy
import asyncio
import json
import os
from pathlib import Path
import shutil
import tempfile
import time
import unittest

from collector.widget_capture import sanitize
from collector.widget_data import inspect_probe, normalize_widget
from collector.widget_views import CARD_SCRIPT, FORM_GROUPS, SEASON_GROUPS, collect_widget_views, selected_snapshot


FIXTURE = Path(__file__).parent / "fixtures/widget-68932518.json"


class WidgetDataTests(unittest.TestCase):
    def setUp(self):
        self.fixture = json.loads(FIXTURE.read_text())

    def test_real_season_values_scopes_windows_and_team_identity(self):
        value = normalize_widget(self.fixture["records"], "68932518")
        self.assertEqual(value["match"]["home"]["name"], "Албания")
        self.assertEqual(value["match"]["home"]["id"], "4690")
        self.assertEqual(value["match"]["home"]["record_id"], "10508147")
        season = [s for s in value["season_statistics"] if s["team"]["id"] == "4690"]
        all_games = next(s for s in season if s["window"]["kind"] == "season")
        last_three = next(s for s in season if s["window"]["source_value"] == "3")
        self.assertEqual(all_games["scopes"]["total"]["wins"], 3)
        self.assertEqual(all_games["scopes"]["total"]["goals_for"], 8)
        self.assertEqual(all_games["scopes"]["total"]["goals_against"], 3)
        self.assertEqual(all_games["scopes"]["total"]["both_teams_scored"], 2)
        self.assertEqual(all_games["scopes"]["total"]["failed_to_score"], 0)
        self.assertEqual(all_games["scopes"]["home"]["goals_against_per_match"], .5)
        self.assertEqual(all_games["scopes"]["away"]["goals_against_per_match"], 1)
        self.assertEqual(last_three["scopes"]["total"]["matches"], 3)
        self.assertEqual(last_three["scopes"]["total"]["wins"], 2)
        self.assertEqual(last_three["window"]["kind"], "last_matches")
        self.assertTrue(all_games["source"]["observed_at"])

    def test_sliced_table_is_incomplete_and_period_is_not_guessed(self):
        value = normalize_widget(self.fixture["records"], "68932518")
        sliced = next(t for t in value["standings"] if len(t["rows"]) == 2)
        self.assertFalse(sliced["rows_complete"])
        self.assertIsNone(sliced["period_id"])
        table = next(t for t in value["standings"] if t["period_id"] == "1")
        self.assertTrue(table["rows_complete"])
        albania = next(r for r in table["rows"] if r["team"]["id"] == "4690")
        self.assertEqual(table["group"], "C1")
        self.assertEqual(albania["scopes"]["total"]["position"], 1)
        self.assertEqual(albania["scopes"]["total"]["draws"], 0)
        self.assertEqual(albania["scopes"]["away"]["position"], 2)
        self.assertEqual(value["coverage"]["missing_table_period_ids"], ["2", "3"])
        self.assertFalse(value["coverage"]["complete"])
        # The provider's form-table rank is across all groups, not group C1.
        form = next(r for r in value["form"] if r["team"]["id"] == "4690")
        self.assertEqual(form["form_table_position"]["total"], 9)

    def test_duplicate_history_by_view_is_not_counted_twice_and_ids_are_checked(self):
        records = copy.deepcopy(self.fixture["records"])
        original = normalize_widget(records, "68932518")
        records.extend(copy.deepcopy(records))
        value = normalize_widget(records, "68932518")
        self.assertEqual(len(original["recent_matches"]), len(value["recent_matches"]))
        for record in records:
            if "stats_season_teamscoringconceding" in record["endpoint"]["path"]:
                record["data"]["doc"][0]["data"]["team"]["_id"] = 999
        wrong = normalize_widget(records, "123")
        self.assertIsNone(wrong["match"])
        self.assertEqual(wrong["season_statistics"], [])
        self.assertTrue(wrong["ignored"])

    def test_probe_inspection_never_uses_a_user_supplied_event_binding(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / "probe.json"
            path.write_text(json.dumps({"format": "doscbot.browser-widget-probe.v1", "statistics_id": "68932518",
                "event_id": "17139995", "binding": "observed_event_link", "requests": self.fixture["records"]}))
            value = inspect_probe(path)
        self.assertIsNone(value["event_id"])
        self.assertEqual(value["binding"], "uploaded_statistics_probe")
        self.assertFalse(value["coverage"]["complete"])

    def test_private_sessions_removed_without_dropping_ball_possession(self):
        data, _ = sanitize({"possession": 54, "ball_possession": 46, "sessionId": "secret",
                            "session_token": "secret", "browser_session": "secret"})
        self.assertEqual(data, {"possession": 54, "ball_possession": 46})


class WidgetViewsTests(unittest.IsolatedAsyncioTestCase):
    async def asyncSetUp(self):
        from playwright.async_api import async_playwright
        self.playwright = await async_playwright().start()
        executable = os.environ.get("CHROMIUM_EXECUTABLE") or shutil.which("chromium") or shutil.which("chromium-browser")
        self.browser = await self.playwright.chromium.launch(headless=True,
            **({"executable_path": executable} if executable else {}))
        self.page = await self.browser.new_page()

    async def asyncTearDown(self):
        await self.browser.close()
        await self.playwright.stop()

    async def test_real_season_card_has_numeric_rows_and_source_selected_filters(self):
        value = json.loads(FIXTURE.read_text())
        await self.page.set_content(value["season_html"])
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("Итоги", "Общее"), time.monotonic()+3)
        self.assertIsNone(reason)
        goals = next(r for r in snapshot["rows"] if r["label"] == "Забитые мячи")
        self.assertEqual((goals["home"], goals["away"]), (8, 9))
        failed = next(r for r in snapshot["rows"] if r["label"] == "Нулевая результативность")
        self.assertEqual(failed["home"], 0)
        # A click with no app handler cannot be mislabeled as the requested view.
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("За матч", "Общее"), time.monotonic()+3)
        self.assertIsNone(snapshot)
        self.assertEqual(reason, "selection_failed")

    async def test_identical_numbers_in_confirmed_filters_are_valid(self):
        value = json.loads(FIXTURE.read_text())
        await self.page.set_content(value["season_html"])
        await self.page.evaluate('''() => {
            for(const b of document.querySelectorAll('button')) b.addEventListener('click',()=>{
                const siblings=[...b.parentElement.children].filter(x=>x.tagName==='BUTTON');
                for(const s of siblings) s.setAttribute('aria-pressed',String(s===b));
            });
        }''')
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("За матч", "Хозяева / Гости"), time.monotonic()+4)
        self.assertIsNone(reason)
        self.assertEqual(snapshot["selected"], ["За матч", "Хозяева / Гости"])
        self.assertEqual(len(snapshot["rows"]), 6)

    async def test_other_match_frame_is_rejected(self):
        result = await collect_widget_views(self.page, "68932518", budget_seconds=1)
        self.assertEqual(result["state"], "partial")
        self.assertEqual(result["snapshots"], [])

    async def test_real_form_card_percentages_and_scope_are_preserved(self):
        value = json.loads(FIXTURE.read_text())
        await self.page.set_content(value["form_html"])
        result = await self.page.evaluate(CARD_SCRIPT, {"cardId": "card__form", "groups": FORM_GROUPS})
        self.assertEqual(result["selected"], ["Общее", "5"])
        self.assertEqual(result["percentages"], [{"team_name": "Албания", "percent": 75},
                                                 {"team_name": "Финляндия", "percent": 63}])

    async def test_pending_source_reply_prevents_a_stale_filter_snapshot(self):
        value = json.loads(FIXTURE.read_text())
        await self.page.set_content(value["season_html"])
        network = {"pending": {"reply"}, "last_activity": time.monotonic()}
        async def source_reply():
            await asyncio.sleep(1.2)
            await self.page.evaluate('''() => {
                const label=[...document.querySelectorAll('span')].find(e=>e.innerText==='Забитые мячи');
                label.parentElement.children[0].innerText='10';
            }''')
            network["pending"].clear()
            network["last_activity"] = time.monotonic()
        task = asyncio.create_task(source_reply())
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("Итоги", "Общее"), time.monotonic()+4, network)
        await task
        self.assertIsNone(reason)
        self.assertEqual(next(r for r in snapshot["rows"] if r["label"] == "Забитые мячи")["home"], 10)

    async def test_all_configured_states_and_same_match_navigation(self):
        value = json.loads(FIXTURE.read_text())
        def buttons(labels):
            return '<div>' + ''.join(f'<button aria-pressed="{str(i==0).lower()}">{s}</button>'
                                     for i,s in enumerate(labels)) + '</div>'
        async def fixture(route):
            statistics = route.request.url.endswith('/statistics')
            table = '<div data-testid="card__leagueTable">' + buttons(['Всего','Хозяева','Гости','Завершен'])
            if not statistics:
                table += buttons(['Результат','1-й тайм','2-й тайм'])
            table += '<p>Одинаковые значения допустимы: 0</p></div>'
            body = ('<a href="/winline/ru/match/68932518">Обзор</a>'
                    '<a href="/winline/ru/match/68932518/statistics">Статистика</a>')
            body += table + (value['form_html'] if statistics else value['season_html'])
            body += '''<script>
                for(const b of document.querySelectorAll('button')) b.addEventListener('click',()=>{
                    for(const s of b.parentElement.children) if(s.tagName==='BUTTON')
                        s.setAttribute('aria-pressed',String(s===b));
                });
            </script>'''
            await route.fulfill(content_type='text/html; charset=utf-8', body=body)
        await self.page.route('**/*', fixture)
        await self.page.goto('https://statshub.yunai.tech/winline/ru/match/68932518/statistics')
        result = await collect_widget_views(self.page, '68932518', budget_seconds=40)
        self.assertEqual(result['missing'], [])
        self.assertEqual(result['state'], 'captured')
        self.assertEqual(len(result['snapshots']), 28)
        self.assertEqual(sorted(g['captured'] for g in result['groups'].values()), [4, 6, 6, 12])
