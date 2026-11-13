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

    def test_best_of_and_terminal_flags_are_preserved_without_guessing(self):
        records=copy.deepcopy(self.fixture['records'])
        for record in records:
            if 'match_info_statshub' in record.get('endpoint',{}).get('path',''):
                record['data']['doc'][0]['data']['match'].update(bestof=3,numberofperiods=3,retired=False,walkover=False,disqualified=False)
        value=normalize_widget(records,'68932518')
        self.assertEqual(value['match']['best_of'],3)
        self.assertEqual(value['match']['number_of_periods'],3)
        self.assertIs(value['match']['retired'],False)
        self.assertIs(value['match']['walkover'],False)
        self.assertIs(value['match']['disqualified'],False)


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

    async def test_controls_can_hydrate_after_the_card_attaches(self):
        await self.page.set_content('<div data-testid="card__seasonStatistics"><h3>Статистика сезона</h3></div>')
        await self.page.evaluate('''() => setTimeout(()=>{
            const card=document.querySelector('[data-testid]');
            card.insertAdjacentHTML('beforeend', '<div><button aria-pressed="true">Итоги</button><button aria-pressed="false">За матч</button><button aria-pressed="false">Серия</button></div><div><button aria-pressed="true">Общее</button><button aria-pressed="false">Хозяева / Гости</button></div>');
            for(const b of card.querySelectorAll('button')) b.onclick=()=>{
                for(const s of b.parentElement.children) s.setAttribute('aria-pressed',String(s===b));
            };
        }, 1000)''')
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("За матч", "Хозяева / Гости"), time.monotonic()+4)
        self.assertIsNone(reason)
        self.assertEqual(snapshot['selected'], ['За матч', 'Хозяева / Гости'])

    async def test_delayed_switches_are_acknowledged_once_per_group(self):
        groups = [["Забитые голы", "Пропущено"], ["Всего", "Хозяева", "Гости"], ["3", "6", "12", "Сезон"]]
        html = '<div data-testid="card__scoringConceding">'
        for group in groups:
            html += '<div>' + ''.join(f'<button aria-pressed="{str(i==0).lower()}">{label}</button>'
                                      for i, label in enumerate(group)) + '</div>'
        await self.page.set_content(html + '<p>Данные источника</p></div>')
        await self.page.evaluate('''() => {
            window.clicks=[]; window.busy=false;
            for(const b of document.querySelectorAll('button')) b.onclick=()=>{
                window.clicks.push(b.innerText);
                if(window.busy) return;
                window.busy=true;
                setTimeout(()=>{
                    for(const s of b.parentElement.children) s.setAttribute('aria-pressed',String(s===b));
                    window.busy=false;
                }, 700);
            };
        }''')
        snapshot, reason = await selected_snapshot(self.page, "card__scoringConceding", groups,
                                                   ("Пропущено", "Гости", "Сезон"), time.monotonic()+5)
        self.assertIsNone(reason)
        self.assertEqual(snapshot['selected'], ['Пропущено', 'Гости', 'Сезон'])
        self.assertEqual(await self.page.evaluate('window.clicks'), ['Пропущено', 'Гости', 'Сезон'])

    async def test_filter_groups_ignore_unrelated_labels_and_hidden_responsive_cards(self):
        value = json.loads(FIXTURE.read_text())
        await self.page.set_content(value['season_html'])
        await self.page.evaluate('''() => {
            const card=document.querySelector('[data-testid="card__seasonStatistics"]');
            const clone=card.cloneNode(true); clone.style.visibility='hidden'; card.after(clone);
            const unrelated=document.createElement('div'); unrelated.innerHTML='<button>За матч</button>';
            card.append(unrelated);
            for(const b of card.querySelectorAll('button[aria-pressed]')) b.onclick=()=>{
                for(const s of b.parentElement.children) s.setAttribute('aria-pressed',String(s===b));
            };
            [...card.querySelectorAll('button')].find(b=>b.innerText==='Хозяева / Гости').innerHTML='Хозяева /<br>Гости';
        }''')
        snapshot, reason = await selected_snapshot(self.page, "card__seasonStatistics", SEASON_GROUPS,
                                                   ("За матч", "Хозяева / Гости"), time.monotonic()+3)
        self.assertIsNone(reason)
        self.assertEqual(snapshot['selected'], ['За матч', 'Хозяева / Гости'])

    async def test_pointer_tabs_capture_all_24_scoring_and_conceding_states(self):
        groups = [["Забитые голы", "Пропущено"], ["Всего", "Хозяева", "Гости"], ["3", "6", "12", "Сезон"]]
        body = '<div style="height:1800px">Матч</div><div data-testid="card__scoringConceding">'
        for index, group in enumerate(groups):
            attribute = 'aria-selected' if index==0 else 'aria-pressed'
            body += '<div>' + ''.join(f'<button {attribute}="{str(i==0).lower()}">{label}</button>'
                                      for i,label in enumerate(group)) + '</div>'
        body += '''<div><span>3</span><span>Показатель</span><span>1</span></div></div><script>
            window.pointerSwitches=0;
            for(const b of document.querySelectorAll('button')) {
                const attribute=b.hasAttribute('aria-selected')?'aria-selected':'aria-pressed';
                const select=()=>{
                    for(const s of b.parentElement.children) s.setAttribute(attribute,String(s===b));
                };
                if(attribute==='aria-selected') b.onmousedown=event=>{
                    if(event.isTrusted && event.button===0) { window.pointerSwitches++; select(); }
                };
                else b.onclick=select;
            }
        </script>'''
        async def fixture(route):
            await route.fulfill(content_type='text/html; charset=utf-8',body=body)
        await self.page.route('**/*',fixture)
        await self.page.goto('https://statshub.yunai.tech/winline/ru/match/68932518/statistics')
        snapshot,reason=await selected_snapshot(self.page,'card__scoringConceding',groups,
            ['Пропущено','Всего','3'],time.monotonic()+3)
        self.assertIsNone(reason)
        self.assertEqual(snapshot['selected'][0],'Пропущено')
        result=await collect_widget_views(self.page,'68932518',budget_seconds=35)
        self.assertEqual(result['missing'],[])
        self.assertEqual(result['state'],'captured')
        self.assertEqual(result['groups']['statistics:card__scoringConceding'],{'captured':24,'expected':24})
        self.assertEqual(sum(s['selected'][0]=='Пропущено' for s in result['snapshots']),12)
        self.assertGreater(await self.page.evaluate('window.pointerSwitches'),0)
        self.assertGreater(await self.page.evaluate('window.scrollY'),0)

    async def test_pointer_click_does_not_force_through_a_covering_element(self):
        await self.page.set_content('''<div data-testid="card__scoringConceding">
            <div><button aria-selected="true">Забитые голы</button><button aria-selected="false">Пропущено</button></div>
            </div><div style="position:fixed;inset:0;background:white;z-index:5">Подождите</div>''')
        diagnostic={}
        snapshot,reason=await selected_snapshot(self.page,'card__scoringConceding',
            [['Забитые голы','Пропущено']],['Пропущено'],time.monotonic()+3,diagnostics=diagnostic)
        self.assertIsNone(snapshot)
        self.assertEqual(reason,'filter_click_failed')
        self.assertEqual(diagnostic['click_method'],'playwright_pointer')
        self.assertEqual(diagnostic['selected'],['Забитые голы'])

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
