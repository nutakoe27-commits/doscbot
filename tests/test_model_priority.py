"""Scheduling reserves new-model coverage without starving other sports."""
from datetime import datetime, timedelta, timezone
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from collector.model_priority import select, read_priority

class PriorityTests(unittest.TestCase):
    def setUp(self):
        self.at=datetime(2026,11,15,12,tzinfo=timezone.utc)
        self.cutoff=self.at.isoformat()
        self.hint={'sports':{'football','hockey'},'excluded_events':{'trained','recorded'}}

    def event(self, identifier, sport='football', hours=3):
        return {'source_event_id':identifier,'sport':sport,'kickoff_at':(self.at+timedelta(hours=hours)).isoformat()}

    def test_eighty_percent_priority_preserves_general_coverage(self):
        others=[self.event('tennis','tennis'),self.event('soon',hours=.5)]
        new=[self.event(str(i),'football' if i%2 else 'hockey') for i in range(12)]
        result=select(others+new,10,self.cutoff,{},self.hint)
        self.assertEqual(len(result),10)
        self.assertEqual({r['source_event_id'] for r in result[-2:]},{'tennis','soon'})
        self.assertEqual({r['sport'] for r in result[:8]},{'football','hockey'})
        self.assertEqual(len({r['source_event_id'] for r in result}),10)

    def test_trained_recorded_recently_attempted_and_outside_window_are_not_prioritized(self):
        ordinary=[self.event('trained'),self.event('recorded'),self.event('recent'),self.event('far',hours=25)]
        new=[self.event('new')]
        result=select(ordinary+new,2,self.cutoff,{'recent':self.cutoff},self.hint)
        self.assertEqual(result[0]['source_event_id'],'new')
        self.assertEqual(len(result),2)
        self.assertEqual(result[1]['source_event_id'],'trained')

    def test_unused_slots_fall_back_in_either_direction(self):
        events=[self.event(str(i)) for i in range(10)]
        self.assertEqual(len(select(events,10,self.cutoff,{},self.hint)),10)
        self.assertEqual(select(events,10,self.cutoff,{},None),events)
        self.assertEqual(select([],10,self.cutoff,{},self.hint),[])
        self.assertEqual(select(events,0,self.cutoff,{},self.hint),[])
        self.assertEqual(len(select(events[:2],10,self.cutoff,{},self.hint)),2)

    def test_stale_future_malformed_and_missing_hint_use_normal_scheduler(self):
        with tempfile.TemporaryDirectory() as d:
            p=Path(d)/'hint.json'
            with patch.dict('os.environ',{'DOSCBOT_MODEL_PRIORITY_FILE':str(p)}):
                self.assertIsNone(read_priority(self.cutoff))
                value={'format':'doscbot.model-collection-priority.v1','checked_at':self.cutoff,
                       'sports':['football'],'excluded_events':['trained']}
                p.write_text(json.dumps(value));self.assertEqual(read_priority(self.cutoff)['sports'],{'football'})
                for delta in (-901,1):
                    p.write_text(json.dumps({**value,'checked_at':(self.at+timedelta(seconds=delta)).isoformat()}))
                    self.assertIsNone(read_priority(self.cutoff))
                p.write_text('{');self.assertIsNone(read_priority(self.cutoff))
                p.write_text(json.dumps({**value,'excluded_events':[{}]}));self.assertIsNone(read_priority(self.cutoff))
