"""Optional, bounded scheduling hint from the admin-only model worker."""
import json
import os
from pathlib import Path
from .store import instant
from .profiles import balanced


def read_priority(cutoff):
    path = Path(os.environ.get('DOSCBOT_MODEL_PRIORITY_FILE',
                               '/var/lib/prognozich-model-client/collection-priority.json'))
    try:
        if path.is_symlink() or path.stat().st_size > 1024 * 1024:
            return None
        value = json.loads(path.read_text())
        if (value['format'] != 'doscbot.model-collection-priority.v1' or
                not 0 <= (instant(cutoff)-instant(value['checked_at'])).total_seconds() <= 900 or
                not isinstance(value['sports'], list) or not all(isinstance(x,str) for x in value['sports']) or
                not isinstance(value['excluded_events'], list) or
                not all(isinstance(x,str) for x in value['excluded_events'])):
            return None
        return {'sports':set(value['sports']), 'excluded_events':set(value['excluded_events'])}
    except (OSError, ValueError, TypeError, KeyError):
        return None


def select(events, limit, cutoff, attempts, hint):
    """Reserve 20% for general coverage; never exclude an event globally."""
    if limit <= 0:
        return []
    if not hint:
        return balanced(events, limit)
    current = instant(cutoff)
    def preferred(event):
        at = attempts.get(event['source_event_id'])
        return (event['sport'] in hint['sports'] and event['source_event_id'] not in hint['excluded_events']
                and 7200 <= (instant(event['kickoff_at'])-current).total_seconds() <= 86400
                and (not at or (current-instant(at)).total_seconds() >= 1800))
    priority = [e for e in events if preferred(e)]
    other = [e for e in events if not preferred(e)]
    quota = max(1, limit * 4 // 5)
    picked = balanced(priority, quota)
    chosen = {e['source_event_id'] for e in picked}
    picked += balanced(other, limit-len(picked))
    chosen.update(e['source_event_id'] for e in picked)
    return picked + balanced([e for e in priority if e['source_event_id'] not in chosen], limit-len(picked))
