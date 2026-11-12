from contextlib import closing
from datetime import datetime, timedelta, timezone
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

from collector.outcomes import choose_result, sync_outcomes
from collector.source import ROOT, load_finding, sync_archive
from collector.store import Store

_, REVISION = load_finding(ROOT.parent / "finding")
from winline.storage import connect, save_results, save_snapshot


class OutcomeTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.state = Path(self.temp.name)
        self.store = Store(self.state/"dataset.sqlite3", REVISION)
        self.archive = self.state/"source/winline.sqlite3"
        self.db = connect(self.archive)
        self.addCleanup(self.db.close)
        self.at = datetime.now(timezone.utc).replace(microsecond=0)
        self.kickoff = (self.at-timedelta(hours=3)).isoformat()
        self.before = (self.at-timedelta(hours=4)).isoformat()
        with closing(self.store.connect()) as db, db:
            db.execute("INSERT INTO metadata VALUES ('source_archive',?)",(str(self.archive),))
        self.capture = self.db.execute("INSERT INTO captures(started_at,url) VALUES (?,?)",(self.before,"https://winline.ru/")).lastrowid
        self.db.commit()
        data = {"sports":[{"id":1,"name":"Футбол"}],"countries":[],"championships":[],"lines":[],
                "events":[{"id":100,"idSport":1,"idRadar":700,"members":["Команда А","Команда Б"],"date":self.kickoff,"isLive":False}]}
        save_snapshot(self.db,self.capture,data,self.before)
        self.result = {"idRadar":700,"member1":"Команда А","member2":"Команда Б","date":self.kickoff,"score":"2:1"}

    def save(self, row=None, sport=1, at=None):
        with patch("winline.storage.now",return_value=(at or self.at.isoformat())):
            save_results(self.db,self.capture,sport,1,[row or self.result])

    def test_result_links_and_corrections_append_without_changing_samples(self):
        event = {"source_event_id":"100","phase":"prematch","sport":"football","kickoff_at":self.kickoff,"observed_at":self.before,"markets":[]}
        stats = {"source_event_id":"100","state":"ok","match_phase":"prematch","observed_at":self.before,"attempted_at":self.before}
        self.assertTrue(self.store.save_sample(event,stats,{},self.before))
        with closing(self.store.connect()) as db:
            original = dict(db.execute("SELECT * FROM samples").fetchone())
        self.save()
        first = sync_outcomes(self.store,self.archive)
        self.assertEqual((first["linked"],first["new_versions"]),(1,1))
        self.assertEqual(sync_outcomes(self.store,self.archive)["new_versions"],0)
        self.save({**self.result,"score":"3:1 (1:0)"},at=(self.at+timedelta(seconds=1)).isoformat())
        self.assertEqual(sync_outcomes(self.store,self.archive)["new_versions"],1)
        with closing(self.store.connect()) as db:
            rows = db.execute("SELECT payload FROM observations WHERE kind='event_outcome' ORDER BY id").fetchall()
            self.assertEqual([json.loads(r[0])["score_text"] for r in rows],["2:1","3:1 (1:0)"])
            self.assertEqual(json.loads(rows[-1][0])["method"],"sport_radar_names_time")
            self.assertEqual(dict(db.execute("SELECT * FROM samples").fetchone()),original)

    def test_conflicting_sport_radar_orientation_and_time_are_not_guessed(self):
        event = dict(self.db.execute("SELECT * FROM events WHERE id=100").fetchone())
        base = {"sport_id":1,"radar_id":700,"member1":"Команда А","member2":"Команда Б","start_at":self.kickoff,"score":"2:1"}
        for changes in ({"sport_id":2},{"radar_id":999},{"member1":"Команда Б","member2":"Команда А"},
                        {"start_at":(self.at-timedelta(hours=2)).isoformat()},{"score":None}):
            result,reason = choose_result(event,[{**base,**changes}])
            self.assertIsNone(result)
        result,method = choose_result(event,[{**base,"radar_id":None}])
        self.assertIsNotNone(result)
        self.assertEqual(method,"sport_names_time_unique")
        self.assertIsNone(choose_result(event,[base,base])[0])

    def test_ambiguous_archive_records_are_visible_and_unlinked(self):
        self.save()
        self.save({**self.result,"championship":"Другой турнир"})
        report = sync_outcomes(self.store,self.archive)
        self.assertEqual(report["ambiguous"],1)
        self.assertEqual(report["linked"],0)
        self.assertNotIn("event_outcome",self.store.status()["observations"])

    def test_foreign_archive_is_refused_before_read(self):
        other = self.state/"old.sqlite3"
        other.write_bytes(b"must remain untouched")
        with self.assertRaises(ValueError):
            sync_outcomes(self.store,other)
        self.assertEqual(other.read_bytes(),b"must remain untouched")
