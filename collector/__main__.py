import argparse
from contextlib import closing
import fcntl
import json
import os
from pathlib import Path
import signal
import shutil
import sys
import time
from datetime import timedelta

from .source import ROOT, load_finding
from .store import Store
from .profiles import DEFAULT_SPORTS,selection


def needs_backoff(status):
    details = json.loads((status.get("last_run") or {}).get("details", "{}"))
    results = details.get("results", {})
    denied = str(results.get("error", "")).startswith("source_http_") or any(
        str(item.get("error", "")).startswith("source_http_") for item in results.get("requests", []))
    return details.get("source_exit_code") != 0 or bool(details.get("errors")) or denied


def main(argv=None):
    parser = argparse.ArgumentParser(description="Collect fresh source facts, without calling an LLM.")
    parser.add_argument("--state-dir", type=Path, default=Path(os.environ.get("DOSCBOT_DATA_DIR", ROOT / ".local")))
    parser.add_argument("--finding", type=Path, default=Path(os.environ.get("FINDING_SOURCE_DIR", ROOT.parent / "finding")))
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("status")
    check=commands.add_parser('audit',help='Read-only evidence of rich data for each requested sport')
    check.add_argument('--sport',default=DEFAULT_SPORTS)
    reset=commands.add_parser('reset-data',help='Audit-gated rotation of owned databases with a recoverable backup')
    reset.add_argument('--apply',action='store_true')
    reset.add_argument('--sport',default=DEFAULT_SPORTS)
    monitor = commands.add_parser("monitor", help="Read-only live terminal dashboard; runs alongside the collector")
    monitor.add_argument("--once", action="store_true", help="Print one snapshot without interactive screen")
    monitor.add_argument("--refresh", type=float, default=2, help="Refresh interval, seconds (0.5..60)")
    monitor.add_argument("--timezone", default="Europe/Moscow")
    monitor.add_argument("--no-color", action="store_true")
    monitor.add_argument("--view", choices=["overview", "matches", "results", "coverage", "errors"], default="overview")
    inspect = commands.add_parser("inspect-widget", help="Inspect a browser JSON probe without opening a database or network")
    inspect.add_argument("--input", required=True, type=Path)
    inspect.add_argument("--output", required=True, type=Path)
    export = commands.add_parser("export")
    export.add_argument("--kind", choices=["samples", "observations"], default="samples")
    export.add_argument("--output", required=True, type=Path)
    rebuild = commands.add_parser("materialize", help="Build samples from newly collected data without network")
    rebuild.add_argument("--event-id", required=True)
    rebuild.add_argument("--dry-run", action="store_true")
    collect = commands.add_parser("collect")
    collect.add_argument("--once", action="store_true")
    collect.add_argument("--interval", type=int, default=300, help="Delay after each completed cycle")
    collect.add_argument("--sport", default=DEFAULT_SPORTS,help="Comma-separated sport keys, or all")
    collect.add_argument("--event-limit", type=int, default=5)
    collect.add_argument("--event-id", help="Only this observed upcoming event; never substitute another")
    collect.add_argument('--widget-budget',type=int,default=120,help='Per-event rich widget budget, seconds (30..300)')
    collect.add_argument("--history-days", type=int, default=2)
    collect.add_argument("--result-timeout", type=int, default=45, help="Result reply timeout, seconds")
    collect.add_argument("--result-limit", type=int, default=4, help="Maximum sport/day result jobs per cycle")
    collect.add_argument("--headed", action="store_true",
                         help="Use a normal browser for detailed statistics; requires DISPLAY (e.g. xvfb-run)")
    collect.add_argument("--chromium", default=os.environ.get("CHROMIUM_EXECUTABLE") or
                        shutil.which("chromium") or shutil.which("chromium-browser"))
    args = parser.parse_args(argv)
    if args.command in ('audit','reset-data'):
        state=args.state_dir.expanduser().resolve()
        if args.command=='audit':
            from .audit import audit
            result=audit(state,args.sport)
        else:
            from .reset import reset_data
            from .source import PIN
            result=reset_data(state,args.finding,PIN['commit'],apply=args.apply,sports=args.sport)
        print(json.dumps(result,ensure_ascii=False,indent=2))
        return 0
    if args.command=='collect':
        selection(args.sport)
        if not 30<=args.widget_budget<=300:
            parser.error('widget-budget must be 30..300 seconds')
    if args.command == "monitor":
        if not .5 <= args.refresh <= 60:
            parser.error("refresh must be 0.5..60 seconds")
        state = args.state_dir.expanduser().resolve()
        finding_root = args.finding.expanduser().resolve()
        if state == finding_root or finding_root in state.parents:
            parser.error("Monitor only the new collector's state directory outside finding")
        from .monitor import run_monitor
        return run_monitor(state, once=args.once, refresh=args.refresh, zone=args.timezone,
                           no_color=args.no_color, view=args.view)
    if args.command == "inspect-widget":
        from .widget_data import inspect_probe
        output = args.output.expanduser().resolve()
        finding_root = args.finding.expanduser().resolve()
        if output == finding_root or finding_root in output.parents:
            parser.error("Diagnostics must be written outside finding")
        report = inspect_probe(args.input.expanduser().resolve())
        output.parent.mkdir(parents=True, exist_ok=True)
        with output.open("x", encoding="utf-8") as handle:
            json.dump(report, handle, ensure_ascii=False, indent=2, allow_nan=False)
            handle.write("\n")
        print(json.dumps({"path": str(output), "statistics_id": report["statistics_id"],
                          "coverage": report["coverage"]}, ensure_ascii=False, indent=2))
        return 0
    if args.command == "collect" and (args.interval < 60 or not 1 <= args.event_limit <= 100 or not 0 <= args.history_days <= 30):
        parser.error("interval >= 60, event-limit 1..100, history-days 0..30 required")
    if args.command == "collect" and (not 15 <= args.result_timeout <= 90 or not 1 <= args.result_limit <= 20):
        parser.error("result-timeout 15..90 and result-limit 1..20 required")
    if args.command == "collect" and args.headed and not os.environ.get("DISPLAY"):
        parser.error("--headed requires DISPLAY; use xvfb-run -a")
    if args.command == "collect" and args.event_id and (not args.event_id.isdecimal() or len(args.event_id) > 20):
        parser.error("A numeric event-id is required")
    finding, revision = load_finding(args.finding)
    state = args.state_dir.expanduser().resolve()
    # Source code can be read; no data, configuration, or outputs may go into finding.
    if state == finding or finding in state.parents:
        parser.error("Use a separate fresh state directory outside finding")
    store = Store(state / "dataset.sqlite3", revision)
    if args.command == "status":
        print(json.dumps(store.status(), ensure_ascii=False, indent=2))
        return 0
    if args.command == "export":
        output = args.output.expanduser().resolve()
        if output == finding or finding in output.parents:
            parser.error("Exports must be outside finding")
        print(json.dumps({"exported": store.export(output, args.kind), "path": str(output)}))
        return 0
    if args.command == "materialize":
        from .materialize import materialize
        if not args.event_id.isdecimal() or len(args.event_id) > 20:
            parser.error("A numeric source event ID is required")
        print(json.dumps(materialize(store, state / "source/winline.sqlite3", args.event_id,
                                     dry_run=args.dry_run), ensure_ascii=False, indent=2))
        return 0
    from .live import cycle
    state.mkdir(parents=True, exist_ok=True)
    with (state / "collector.lock").open("a") as lock:
        try:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            parser.error("A collector is already running for this state directory")
        running = True
        def stop(*_):
            nonlocal running
            running = False
        signal.signal(signal.SIGTERM, stop)
        signal.signal(signal.SIGINT, stop)
        from .runtime import Runtime, update_runtime
        from .store import instant, now
        with Runtime(store, interval=args.interval, sport=args.sport, event_limit=args.event_limit,
                     history_days=args.history_days):
            failures = 0
            while running:
                ok = cycle(store, state, finding, args.chromium, args.sport, args.event_limit, args.history_days,
                           stopped=lambda: not running, headed=args.headed,
                           result_timeout=args.result_timeout, result_limit=args.result_limit, event_id=args.event_id,widget_budget=args.widget_budget)
                status = store.status()
                print(json.dumps(status, ensure_ascii=False), flush=True)
                if args.once:
                    return 0 if ok else 2
                # Result retries don't stretch healthy primary sampling to hourly intervals.
                failures = failures + 1 if needs_backoff(status) else 0
                delay = max(args.interval, min(3600, 60 * 2 ** min(failures, 6)))
                deadline = time.monotonic() + delay
                update_runtime(store, phase="waiting", current_match=None, completed=0, total=0,
                               next_run_at=(instant(now())+timedelta(seconds=delay)).isoformat())
                while running and time.monotonic() < deadline:
                    time.sleep(min(1, deadline - time.monotonic()))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, OSError) as error:
        print(f"Collector stopped: {type(error).__name__}: {error}", file=sys.stderr)
        raise SystemExit(1)
