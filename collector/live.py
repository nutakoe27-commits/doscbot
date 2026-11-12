"""Bounded collection using the exact public parsers pinned from finding."""

import asyncio
from contextlib import closing
import os
from pathlib import Path
import signal
import subprocess
import sys
import time

from .source import candidates, source_connection, sync_archive
from .store import instant, now, sample_blocker


def browser_options(chromium, headed=False):
    options = {"headless": not headed, "args": ["--disable-gpu"]}
    if chromium:
        options["executable_path"] = chromium
    proxy = os.environ.get("HTTPS_PROXY") or os.environ.get("https_proxy")
    if proxy:
        options["proxy"] = {"server": proxy}
    # Trust comes from the managed OS certificate store; verification remains enabled.
    return options


async def collect_details(store, archive, chromium, sport, limit, *, headed=False, event_id=None):
    from playwright.async_api import async_playwright
    from matchlab.statistics import collect_statistics, validate_statistics
    from matchlab.winline_markets import (ensure_event_page, read_marketbook,
                                        discover_statistics_link, market_document)
    from matchlab.worker_protocol import market_feed
    from winline.team_context import build_context
    from .widget_capture import WidgetCapture
    from .widget_data import normalize_widget
    from .widget_views import collect_widget_views

    with closing(store.connect()) as db:
        attempts = {r["entity_id"]: r["at"] for r in db.execute(
            "SELECT entity_id,max(observed_at) at FROM observations WHERE kind='event_attempt' GROUP BY entity_id")}
    events = candidates(archive, sport, limit, now(), attempts, event_id=event_id)
    result = {"selected": len(events), "market_documents": 0, "statistics": 0,
              "samples": 0, "errors": [], "statistics_states": {}, "sample_outcomes": {},
              "widget_json": 0, "widget_capture_states": {}, "widget_data": 0,
              "widget_coverage": {}}
    if not events:
        if event_id is not None:
            result["errors"].append({"event_id": event_id, "type": "requested_event_not_in_fresh_prematch_catalog"})
        return result
    async with async_playwright() as playwright:
        browser = await playwright.chromium.launch(**browser_options(chromium, headed))
        try:
            context = await browser.new_context(locale="ru-RU", timezone_id="Europe/Moscow")
            async def resources(route):
                if route.request.resource_type in ("image", "media", "font"):
                    await route.abort()
                else:
                    await route.continue_()
            await context.route("**/*", resources)
            for event in events:
                store.observation("event_attempt", event["source_event_id"], now(), {"url": event["url"]})
                page = await context.new_page()
                sockets = []
                def opened(socket):
                    if market_feed("winline", socket.url):
                        state = {"last_at": None, "connected": True}
                        sockets.append(state)
                        socket.on("framereceived", lambda data: state.update(last_at=now()))
                        socket.on("close", lambda *args: state.update(connected=False))
                page.on("websocket", opened)
                try:
                    response = await page.goto(event["url"], wait_until="domcontentloaded", timeout=45000)
                    if response and response.status in (401, 403, 429):
                        result["errors"].append({"event_id": event["source_event_id"], "type": "source_blocked"})
                        break
                    await ensure_event_page(page, event["url"], event)
                    raw = await read_marketbook(page)
                    if not any(s["connected"] and s["last_at"] and
                               (instant(now())-instant(s["last_at"])).total_seconds() <= 180 for s in sockets):
                        raise ValueError("No current source market feed")
                    raw["statistics_links"] = await discover_statistics_link(page, raw)
                    document = market_document(raw, page.url, now(), event)
                    store.observation("market_document", event["source_event_id"], document["observed_at"], document)
                    result["market_documents"] += 1
                    if document.get("statistics_link_status") != "found":
                        result["statistics_states"][event["source_event_id"]] = "missing_link"
                        continue
                    job = {**document, "url": document["url"]}
                    observer = WidgetCapture(page, document["statistics_url"].rstrip("/").rsplit("/", 1)[-1])
                    observer.start()
                    views = None
                    try:
                        statistics = await collect_statistics(page, job, enable_feed=True)
                        if statistics.get("state") == "ok":
                            views = await collect_widget_views(page, observer.statistics_id)
                    finally:
                        records, summary = await observer.finish()
                        result["widget_capture_states"][event["source_event_id"]] = summary
                        for record in records:
                            store.observation("widget_json", event["source_event_id"], record["observed_at"], record)
                        result["widget_json"] += len(records)
                        try:
                            rich = normalize_widget(records, observer.statistics_id,
                                event_id=event["source_event_id"], binding="observed_event_link", views=views)
                        except (ValueError, TypeError, KeyError, AttributeError):
                            # An unfamiliar rich schema must not discard the
                            # independently validated legacy input or raw JSON.
                            result["widget_coverage"][event["source_event_id"]] = {
                                "complete": False, "state": "normalization_error"}
                        else:
                            if summary["state"] != "captured":
                                rich["coverage"]["complete"] = False
                            rich["capture"] = summary
                            rich["observed_at"] = now()
                            store.observation("widget_data", event["source_event_id"], rich["observed_at"], rich)
                            result["widget_data"] += 1
                            result["widget_coverage"][event["source_event_id"]] = rich["coverage"]
                    statistics = validate_statistics(statistics, document)
                    store.observation("team_statistics", event["source_event_id"], statistics["attempted_at"], statistics)
                    result["statistics_states"][event["source_event_id"]] = statistics["state"]
                    if statistics["state"] == "blocked":
                        result["errors"].append({"event_id": event["source_event_id"], "type": "statistics_blocked"})
                        break
                    if statistics["state"] == "ok":
                        result["statistics"] += 1
                    cutoff = now()
                    reason = sample_blocker(document, statistics, cutoff)
                    outcome = {"statistics_phase": statistics.get("match_phase"),
                               "capture_kind": statistics.get("capture_kind", "detailed"),
                               "reason": reason}
                    result["sample_outcomes"][event["source_event_id"]] = outcome
                    if reason:
                        continue
                    history = build_context(int(event["source_event_id"]), archive, as_of=cutoff)
                    saved = store.save_sample(document, statistics, history, cutoff)
                    result["samples"] += int(saved)
                    outcome["reason"] = "saved" if saved else "duplicate"
                except Exception as error:
                    # Fixed class only: exception messages can contain URLs or page content.
                    result["errors"].append({"event_id": event["source_event_id"], "type": type(error).__name__})
                finally:
                    await page.close()
                await asyncio.sleep(1)
        finally:
            await browser.close()
    return result


def run_source(command, finding, environment, stopped):
    process = subprocess.Popen(command, cwd=finding, env=environment, start_new_session=True)
    deadline = time.monotonic() + 600
    try:
        while process.poll() is None:
            if stopped() or time.monotonic() >= deadline:
                os.killpg(process.pid, signal.SIGINT)
                try:
                    process.wait(timeout=20)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid, signal.SIGKILL)
                    process.wait()
                return 130 if stopped() else 124
            time.sleep(0.25)
        return process.returncode
    finally:
        if process.poll() is None:
            os.killpg(process.pid, signal.SIGKILL)
            process.wait()


def cycle(store, state_dir, finding, chromium, sport, limit, history_days, stopped=lambda: False,
          *, headed=False, result_timeout=45, result_limit=4, event_id=None):
    archive = Path(state_dir) / "source" / "winline.sqlite3"
    # Never adopt a pre-existing source archive, including an old finding database.
    with closing(store.connect()) as db, db:
        owner = db.execute("SELECT value FROM metadata WHERE key='source_archive'").fetchone()
        if archive.exists() and (not owner or owner[0] != str(archive.resolve())):
            raise ValueError("Refusing a pre-existing source archive; choose a new empty state directory")
        db.execute("INSERT OR REPLACE INTO metadata VALUES ('source_archive',?)", (str(archive.resolve()),))
    archive.parent.mkdir(parents=True, exist_ok=True)
    command = [sys.executable, "-m", "winline.collect", "--database", str(archive),
               "--seconds", "5", "--history-days", "0",
               "--no-expand-markets", "--no-snapshot-files", "--light-snapshots"]
    if chromium:
        command += ["--chromium", chromium]
    # Only pinned code is used; finding's configuration is not loaded.
    environment = dict(os.environ)
    environment["PYTHONPATH"] = str(finding)
    run = store.begin_run()
    try:
        before = 0
        if archive.exists():
            with closing(source_connection(archive)) as db:
                before = db.execute("SELECT coalesce(max(id),0) FROM snapshots").fetchone()[0]
        code = run_source(command, finding, environment, stopped)
        counts = sync_archive(store, archive) if archive.exists() else {}
        if stopped():
            store.finish_run(run, "stopped", {"imported_new_observations": counts})
            return False
        fresh = False
        if archive.exists():
            with closing(source_connection(archive)) as db:
                fresh = db.execute("SELECT coalesce(max(id),0) FROM snapshots").fetchone()[0] > before
        if not fresh:
            details = {"source_exit_code": code, "imported_new_observations": counts,
                       "reason": "no_new_source_snapshot"}
            store.finish_run(run, "source_error", details)
            return False
        details = asyncio.run(collect_details(store, archive, chromium, sport, limit, headed=headed, event_id=event_id))
        if history_days and not stopped():
            from .results import collect_results
            try:
                details["results"] = asyncio.run(collect_results(
                    store, archive, sport=sport, days=history_days,
                    options=browser_options(chromium, headed), timeout_seconds=result_timeout,
                    limit=result_limit, stopped=stopped))
            except Exception as error:
                details["results"] = {"state": "error", "error": type(error).__name__}
            for kind, count in sync_archive(store, archive).items():
                counts[kind] = counts.get(kind, 0) + count
        details["details_browser"] = "headed" if headed else "headless"
        details["source_exit_code"] = code
        details["imported_new_observations"] = counts
        state = "ok" if details["samples"] else "partial"
        if details["errors"] or code or details.get("results", {}).get("state", "ok") != "ok":
            state = "partial"
        store.finish_run(run, state, details)
        return state == "ok"
    except Exception as error:
        store.finish_run(run, "error", {"type": type(error).__name__})
        return False
