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

from .source import ROOT, load_finding
from .store import Store


def main(argv=None):
    parser = argparse.ArgumentParser(description="Collect fresh source facts, without calling an LLM.")
    parser.add_argument("--state-dir", type=Path, default=Path(os.environ.get("DOSCBOT_DATA_DIR", ROOT / ".local")))
    parser.add_argument("--finding", type=Path, default=Path(os.environ.get("FINDING_SOURCE_DIR", ROOT.parent / "finding")))
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("status")
    export = commands.add_parser("export")
    export.add_argument("--kind", choices=["samples", "observations"], default="samples")
    export.add_argument("--output", required=True, type=Path)
    collect = commands.add_parser("collect")
    collect.add_argument("--once", action="store_true")
    collect.add_argument("--interval", type=int, default=300, help="Delay after each completed cycle")
    collect.add_argument("--sport", default="football")
    collect.add_argument("--event-limit", type=int, default=5)
    collect.add_argument("--history-days", type=int, default=2)
    collect.add_argument("--chromium", default=os.environ.get("CHROMIUM_EXECUTABLE") or
                        shutil.which("chromium") or shutil.which("chromium-browser"))
    args = parser.parse_args(argv)
    if args.command == "collect" and (args.interval < 60 or not 1 <= args.event_limit <= 100 or not 0 <= args.history_days <= 30):
        parser.error("interval >= 60, event-limit 1..100, history-days 0..30 required")
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
        failures = 0
        while running:
            ok = cycle(store, state, finding, args.chromium, args.sport, args.event_limit, args.history_days,
                       stopped=lambda: not running)
            print(json.dumps(store.status(), ensure_ascii=False), flush=True)
            if args.once:
                return 0 if ok else 2
            failures = 0 if ok else failures + 1
            delay = max(args.interval, min(3600, 60 * 2 ** min(failures, 6)))
            deadline = time.monotonic() + delay
            while running and time.monotonic() < deadline:
                time.sleep(min(1, deadline - time.monotonic()))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (ValueError, OSError) as error:
        print(f"Collector stopped: {type(error).__name__}: {error}", file=sys.stderr)
        raise SystemExit(1)
