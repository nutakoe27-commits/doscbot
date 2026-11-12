import asyncio
import json
import os
from pathlib import Path
import shutil
from types import SimpleNamespace
import unittest
from unittest.mock import AsyncMock

from collector.widget_capture import WidgetCapture


def response(payload, *, frame="https://statshub.yunai.tech/winline/ru/match/75512542/statistics",
             url="https://sh.fn.yunai.tech/gismo/season_stats/75512542?token=fixture-secret", status=200):
    async def header(name):
        return "application/json" if name == "content-type" else None
    return SimpleNamespace(request=SimpleNamespace(frame=SimpleNamespace(url=frame), resource_type="xhr"),
                           url=url, status=status, header_value=header,
                           body=AsyncMock(return_value=json.dumps(payload).encode()))


class WidgetCaptureTests(unittest.IsolatedAsyncioTestCase):
    async def test_chunked_rich_documents_are_preserved_and_private_values_removed(self):
        page = SimpleNamespace(on=lambda *args: None, remove_listener=lambda *args: None)
        capture = WidgetCapture(page, "75512542")
        data = {"doc": [{"data": {"season": {"_id": 2026}, "teams": [
            {"_id": 10, "name": "Аруба", "stats": {"wins": 3, "goals": 8}},
            {"_id": 20, "name": "Мартиника", "stats": {"wins": 1, "goals": 5}}],
            "token": "fixture-secret", "ip": "fixture-private-ip",
            "url": "https://sh.fn.yunai.tech/data?key=fixture-secret"}}]}
        capture.on_response(response(data))
        records, summary = await capture.finish()
        self.assertEqual(summary["captured"], 1)
        self.assertEqual(records[0]["data"]["doc"][0]["data"]["teams"][0]["stats"]["goals"], 8)
        self.assertNotIn("fixture-secret", json.dumps(records))
        self.assertNotIn("fixture-private-ip", json.dumps(records))
        self.assertGreater(summary["redacted"], 0)
        self.assertEqual(summary["state"], "captured")

    async def test_other_match_and_non_widget_frames_are_not_captured(self):
        page = SimpleNamespace(on=lambda *args: None, remove_listener=lambda *args: None)
        capture = WidgetCapture(page, "75512542")
        wrong = response({}, frame="https://statshub.yunai.tech/winline/ru/match/999")
        top = response({}, frame="https://winline.ru/stavki/sport/futbol/17139995")
        capture.on_response(wrong)
        capture.on_response(top)
        records, summary = await capture.finish()
        self.assertEqual(records, [])
        self.assertEqual(summary["responses"], 0)
        wrong.body.assert_not_awaited()
        top.body.assert_not_awaited()

    async def test_capture_limits_are_visible_as_incomplete(self):
        page = SimpleNamespace(on=lambda *args: None, remove_listener=lambda *args: None)
        capture = WidgetCapture(page, "75512542", max_response=10)
        capture.on_response(response({"data": "larger than permitted"}))
        records, summary = await capture.finish()
        self.assertEqual(records, [])
        self.assertEqual(summary["state"], "partial")
        self.assertEqual(summary["skipped"]["response_size"], 1)


class BrowserProbeTests(unittest.IsolatedAsyncioTestCase):
    async def test_probe_preserves_app_responses_and_captures_public_json_and_dom(self):
        from playwright.async_api import async_playwright
        source = (Path(__file__).resolve().parents[1] / "scripts/widget-probe.js").read_text()
        async with async_playwright() as p:
            executable = os.environ.get("CHROMIUM_EXECUTABLE") or shutil.which("chromium") or shutil.which("chromium-browser")
            browser = await p.chromium.launch(headless=True, **({"executable_path": executable} if executable else {}))
            try:
                context = await browser.new_context()
                async def fixture(route):
                    if "/winline/ru/match/" in route.request.url:
                        await route.fulfill(content_type="text/html; charset=utf-8", body='''<body>
                            <div data-testid="match-header">Аруба — Мартиника</div>
                            <section><h3>Статистика сезона</h3><div class="row" onclick="alert(1)">8 Забитые мячи 5</div>
                            <form>private-form-fixture</form><button aria-pressed="true">Общее</button>
                            <p>viewer@example.invalid 192.0.2.12</p></section>
                            </body>''')
                    else:
                        await route.fulfill(content_type="application/json", headers={"Access-Control-Allow-Origin": "*"},
                                            body=json.dumps({"season": {"_id": 2026}, "teams": [{"name": "Аруба", "goals": 8}],
                                                             "token": "fixture-secret"}))
                await context.route("**/*", fixture)
                page = await context.new_page()
                await page.goto("https://statshub.yunai.tech/winline/ru/match/75512542")
                await page.evaluate("window.originalFetchForTest=window.fetch")
                await page.evaluate(source)
                original = await page.evaluate("""async () => {
                    const data=await (await fetch('https://sh.fn.yunai.tech/season_stats/75512542?token=fixture-secret')).json();
                    await new Promise((resolve,reject)=>{const x=new XMLHttpRequest(); x.open('GET','https://sh.fn.yunai.tech/table/75512542');
                      x.responseType='json'; x.onload=resolve; x.onerror=reject; x.send();});
                    return data;
                }""")
                self.assertEqual(original["token"], "fixture-secret")
                self.assertEqual(original["teams"][0]["goals"], 8)
                report = await page.evaluate("doscbotProbe.report()")
                self.assertEqual(len(report["requests"]), 2)
                text = json.dumps(report, ensure_ascii=False)
                self.assertNotIn("fixture-secret", text)
                self.assertNotIn("private-form-fixture", text)
                self.assertNotIn("viewer@example.invalid", text)
                self.assertNotIn("192.0.2.12", text)
                self.assertNotIn("onclick=", text)
                self.assertIn("8 Забитые мячи 5", text)
                self.assertEqual(report["statistics_id"], "75512542")
                await page.evaluate("doscbotProbe.stop()")
                self.assertTrue(await page.evaluate("window.fetch===window.originalFetchForTest"))
                await page.goto("https://statshub.yunai.tech/winline/ru/match/68932518")
                await page.evaluate(source)
                other = await page.evaluate("doscbotProbe.report()")
                self.assertEqual(other["statistics_id"], "68932518")
                self.assertIsNone(other["event_id"])
                self.assertIsNone(other["event_url"])
                self.assertEqual(other["binding"], "statistics_page_only")
                await page.goto("https://winline.ru/stats/match/68932518")
                with self.assertRaisesRegex(Exception, "iframe StatsHub"):
                    await page.evaluate(source)
            finally:
                await browser.close()
