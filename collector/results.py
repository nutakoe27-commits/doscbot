"""Bounded result jobs with one isolated SDK session per sport/day request."""

import asyncio
from contextlib import closing
from datetime import timedelta
from pathlib import Path
import time

from playwright.async_api import Error, async_playwright

from .store import encoded, instant, now
from .runtime import update_runtime
from .profiles import accepts

URL = "https://winline.ru/"
SCHEMA = """
CREATE TABLE IF NOT EXISTS doscbot_result_jobs (
 sport_id INTEGER NOT NULL, day_ms INTEGER NOT NULL, sport_label TEXT NOT NULL,
 last_attempt_at TEXT, last_success_at TEXT, retry_after TEXT,
 failures INTEGER NOT NULL DEFAULT 0, last_error TEXT,
 PRIMARY KEY(sport_id,day_ms));
"""


class SourceDenied(Exception):
    pass


def pairs_from_catalog(catalog, sport, days):
    from matchlab.archive import sport_key

    pairs = []
    for identifier, value in catalog["sports"].items():
        sport_id = int(identifier)
        name = value.get("name") or f"Спорт {sport_id}"
        if not accepts(sport, sport_key(name, sport_id)):
            continue
        dates = sorted({int(day) for day in value.get("dates", [])}, reverse=True)
        for day in dates:
            if day >= dates[0] - (days - 1) * 86400000:
                pairs.append((sport_id, day, name))
    return pairs


def followed_pairs(store, catalog_value, sport, pairs):
    """Retry days of unresolved detailed matches, if still offered by source."""
    from matchlab.archive import sport_key
    available = {}
    for identifier, item in catalog_value["sports"].items():
        key = sport_key(item.get("name", ""), int(identifier))
        if accepts(sport, key):
            for day in item.get("dates", []):
                available[(key, int(day))] = (int(identifier), int(day), item.get("name", key))
    current = instant(now())
    wanted = set()
    with closing(store.connect()) as db:
        rows = db.execute("SELECT json_extract(o.payload,'$.sport') sport, "
            "json_extract(o.payload,'$.kickoff_at') kickoff FROM observations o "
            "WHERE o.kind='market_document' AND o.id IN "
            "(SELECT max(id) FROM observations WHERE kind='market_document' GROUP BY entity_id) "
            "AND NOT EXISTS (SELECT 1 FROM observations r WHERE r.kind='event_outcome' AND r.entity_id=o.entity_id)")
        for row in rows:
            try:
                at = instant(row["kickoff"])
                if current-timedelta(days=7) <= at <= current:
                    day = int(at.timestamp()) // 86400 * 86400000
                    candidate = available.get((row["sport"], day))
                    if candidate:
                        wanted.add(candidate)
            except (ValueError, TypeError):
                continue
    existing = {(p[0], p[1]) for p in pairs}
    return pairs + sorted((p for p in wanted if (p[0], p[1]) not in existing), key=lambda p: -p[1])


def plan_jobs(db, pairs, limit):
    current = instant(now())
    due = []
    with db:
        for sport, day, name in pairs:
            db.execute("INSERT INTO doscbot_result_jobs(sport_id,day_ms,sport_label) VALUES (?,?,?) "
                       "ON CONFLICT(sport_id,day_ms) DO UPDATE SET sport_label=excluded.sport_label",
                       (sport, day, name))
            row = db.execute("SELECT * FROM doscbot_result_jobs WHERE sport_id=? AND day_ms=?",
                             (sport, day)).fetchone()
            if not row["retry_after"] or instant(row["retry_after"]) <= current:
                due.append((row["last_attempt_at"] or "", -day, sport, name))
    due.sort()
    return [(sport, -negative_day, name) for _, negative_day, sport, name in due[:limit]], len(due)


def finish_job(db, pair, *, success, latest_day, error=None):
    sport, day, _ = pair
    at = now()
    old = db.execute("SELECT failures FROM doscbot_result_jobs WHERE sport_id=? AND day_ms=?",
                     (sport, day)).fetchone()
    failures = 0 if success else old["failures"] + 1
    delay = (300 if day == latest_day else 3600) if success else min(3600, 120 * 2 ** min(failures - 1, 5))
    retry = (instant(at) + timedelta(seconds=delay)).isoformat()
    with db:
        db.execute("UPDATE doscbot_result_jobs SET last_attempt_at=?,last_success_at="
                   "CASE WHEN ? THEN ? ELSE last_success_at END,retry_after=?,failures=?,last_error=? "
                   "WHERE sport_id=? AND day_ms=?",
                   (at, success, at, retry, failures, error, sport, day))


async def open_session(browser):
    """A new context owns its WebSocket and pending-result callback exclusively."""
    from winline.browser import INSTALL

    context = await browser.new_context(locale="ru-RU", timezone_id="Europe/Moscow")
    try:
        async def resources(route):
            if route.request.resource_type in ("image", "media", "font"):
                await route.abort()
            else:
                await route.continue_()
        await context.route("**/*", resources)
        page = await context.new_page()
        response = await page.goto(URL, wait_until="domcontentloaded", timeout=45000)
        if response and response.status in (401, 403, 429):
            raise SourceDenied(f"source_http_{response.status}")
        await page.wait_for_function("window.apiWlb?.getEvents().toArray().length > 0 && "
                                     "window.apiWlb.client.websocket.readyState === 1", timeout=45000)
        await page.evaluate(INSTALL)
        await page.evaluate("window.apiWlb.getService('ResultService').getPreData()")
        await page.wait_for_function("window.__winlineReader.pre !== null", timeout=20000)
        return context, page
    except BaseException:
        await context.close()
        raise


async def catalog(browser):
    context, page = await open_session(browser)
    try:
        return await page.evaluate("JSON.parse(JSON.stringify(window.__winlineReader.pre))")
    finally:
        await context.close()


async def request_pair(browser, pair, timeout_seconds):
    from winline.browser import RESULT_REQUEST

    context, page = await open_session(browser)
    try:
        rows = await page.evaluate(RESULT_REQUEST, {"sport": pair[0], "day": pair[1],
                                                  "timeout": timeout_seconds * 1000})
        if not isinstance(rows, list) or any(not isinstance(row, dict) for row in rows):
            raise ValueError("invalid_result_reply")
        return rows
    finally:
        # A timed-out GET_RESULT response has no reliable sport/day correlation.
        # Closing this context before any next request prevents reassignment.
        await context.close()


async def collect_results(store, archive, *, sport, days, options, timeout_seconds=45,
                          limit=4, budget_seconds=180, stopped=lambda: False):
    from winline.storage import connect, save_results

    archive = Path(archive).resolve()
    with closing(store.connect()) as target:
        owner = target.execute("SELECT value FROM metadata WHERE key='source_archive'").fetchone()
        if not owner or owner[0] != str(archive) or not archive.is_file():
            raise ValueError("Only this collector's own fresh source archive may be used")
    report = {"state": "running", "sport": sport, "timeout_seconds": timeout_seconds,
              "available_pairs": 0, "due_pairs": 0, "planned": 0, "attempted": 0,
              "successful": 0, "failed": 0, "deferred": 0, "rows": 0, "new_versions": 0,
              "requests": []}
    deadline = time.monotonic() + budget_seconds
    with closing(connect(archive)) as db:
        db.executescript(SCHEMA)
        before = db.execute("SELECT coalesce(max(id),0) FROM result_history").fetchone()[0]
        capture = db.execute("INSERT INTO captures(started_at,url) VALUES (?,?)", (now(), URL)).lastrowid
        db.execute("INSERT INTO collection_runs(capture_id,settings_json,status) VALUES (?,?,?)",
                   (capture, encoded({"format": "doscbot.result-jobs.v1", "days": days,
                                      "sport": sport, "timeout_seconds": timeout_seconds}), "running"))
        db.commit()
        report["capture_id"] = capture
        try:
            async with async_playwright() as playwright:
                browser = await playwright.chromium.launch(**options)
                try:
                    value = await asyncio.wait_for(catalog(browser), timeout=max(1, deadline - time.monotonic()))
                    initial_pairs = pairs_from_catalog(value, sport, days)
                    pairs = followed_pairs(store, value, sport, initial_pairs)
                    report["followup_pairs"] = len(pairs)-len(initial_pairs)
                    report["available_pairs"] = len(pairs)
                    jobs, due = plan_jobs(db, pairs, limit)
                    report.update(due_pairs=due, planned=len(jobs))
                    db.execute("UPDATE collection_runs SET planned_result_requests=? WHERE capture_id=?",
                               (len(jobs), capture))
                    db.commit()
                    latest = {}
                    for s, d, _ in pairs:
                        latest[s] = max(latest.get(s, d), d)
                    for pair in jobs:
                        if stopped() or time.monotonic() >= deadline:
                            break
                        item = {"sport_id": pair[0], "source_day_ms": pair[1]}
                        update_runtime(store, phase="results", completed=report["attempted"], total=len(jobs),
                                       current_match=None, result_sport=pair[0], result_day_ms=pair[1])
                        report["attempted"] += 1
                        denied = False
                        try:
                            rows = await asyncio.wait_for(request_pair(browser, pair, timeout_seconds),
                                                          timeout=max(1, deadline - time.monotonic()))
                            save_results(db, capture, pair[0], pair[1], rows)
                            finish_job(db, pair, success=True, latest_day=latest[pair[0]])
                            item.update(state="ok", rows=len(rows))
                            report["successful"] += 1
                            report["rows"] += len(rows)
                        except (Error, TimeoutError, ValueError, SourceDenied) as error:
                            denied = isinstance(error, SourceDenied)
                            kind = (str(error) if denied else "budget_exhausted" if isinstance(error, TimeoutError)
                                    else "result_timeout" if "Result request timed out" in str(error)
                                    else type(error).__name__)
                            save_results(db, capture, pair[0], pair[1], [], error=kind)
                            finish_job(db, pair, success=False, latest_day=latest[pair[0]], error=kind)
                            item.update(state="error", error=kind)
                            report["failed"] += 1
                        report["requests"].append(item)
                        update_runtime(store, completed=report["attempted"])
                        if denied:
                            break
                        await asyncio.sleep(0.5)
                    report["deferred"] = due - report["attempted"]
                    report["state"] = ("unavailable" if not pairs else "partial" if report["failed"] or
                                       report["attempted"] < len(jobs) else "ok")
                finally:
                    await browser.close()
        except Exception as error:
            report.update(state="error", error=str(error) if isinstance(error, SourceDenied) else type(error).__name__)
        finally:
            report["new_versions"] = db.execute("SELECT count(*) FROM result_history WHERE id>?", (before,)).fetchone()[0]
            with db:
                db.execute("UPDATE collection_runs SET status=? WHERE capture_id=?",
                           ("complete" if report["state"] == "ok" else "partial", capture))
                db.execute("UPDATE captures SET finished_at=?,error=? WHERE id=?",
                           (now(), None if report["state"] == "ok" else encoded(report), capture))
    return report
