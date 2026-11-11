"""Build timed inputs from this collector's newly observed data, without network."""

from contextlib import closing
import json
from pathlib import Path

from .store import now, sample_blocker


def materialize(store, archive, event_id, *, dry_run=False):
    from winline.team_context import build_context

    archive = Path(archive).resolve()
    result = {"event_id": event_id, "dry_run": dry_run, "processed": 0,
              "saved": 0, "reasons": {}, "records": [], "checked_at": now()}
    with closing(store.connect()) as db:
        owned = db.execute("SELECT value FROM metadata WHERE key='source_archive'").fetchone()
        if not owned or owned[0] != str(archive):
            raise ValueError("Only this collector's own fresh source archive may be used")
        if not archive.is_file():
            raise ValueError("Collector source archive is missing")
        rows = db.execute("SELECT * FROM observations WHERE kind='team_statistics' "
                          "AND entity_id=? ORDER BY id", (event_id,))
        for row in rows:
            statistics = json.loads(row["payload"])
            # Actual first receipt is preserved. Reprocessing today does not
            # make later source facts available at an earlier prediction time.
            cutoff = row["received_at"]
            previous = db.execute(
                "SELECT * FROM observations WHERE kind='market_document' AND entity_id=? "
                "AND julianday(received_at)<=julianday(?) AND source_revision=? "
                "ORDER BY julianday(received_at) DESC,id DESC LIMIT 1",
                (event_id, cutoff, row["source_revision"]),
            ).fetchone()
            reason = "market_document_missing"
            event = None
            if previous:
                event = json.loads(previous["payload"])
                reason = ("parser_revision_mismatch" if row["source_revision"] != store.revision
                          else sample_blocker(event, statistics, cutoff))
            record = {"statistics_observation_id": row["id"], "as_of": cutoff,
                      "statistics_phase": statistics.get("match_phase"),
                      "capture_kind": statistics.get("capture_kind", "detailed"),
                      "kickoff_at": event.get("kickoff_at") if event else None,
                      "reason": reason}
            if reason is None:
                # The native context builder reads historical versions at cutoff;
                # current result corrections and later odds cannot leak in.
                history = build_context(int(event_id), archive, as_of=cutoff)
                if dry_run:
                    record["reason"] = "eligible"
                else:
                    saved = store.save_sample(event, statistics, history, cutoff)
                    result["saved"] += int(saved)
                    record["reason"] = "saved" if saved else "duplicate"
            result["processed"] += 1
            key = record["reason"]
            result["reasons"][key] = result["reasons"].get(key, 0) + 1
            if len(result["records"]) < 20:
                result["records"].append(record)
    return result
