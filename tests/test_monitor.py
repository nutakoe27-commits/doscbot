from contextlib import closing
from datetime import datetime, timedelta, timezone
import fcntl
import io
import json
from pathlib import Path
import sqlite3
import tempfile
import unittest
from zoneinfo import ZoneInfo

from rich.console import Console

from collector.monitor import Dashboard, clean, collector_health
from collector.monitor_data import MonitorReader, readonly
from collector.runtime import Runtime, update_runtime
from collector.store import Store


class MonitorTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.state = Path(self.temp.name)
        self.at = datetime.now(timezone.utc).isoformat()

    def populate(self):
        store = Store(self.state/"dataset.sqlite3","fixture-revision")
        self.store = store
        for i in range(3):
            store.observation("market_document",str(100+i),self.at,{"home":f"Команда {i}","away":"Гости [red]",
                "sport":"football","competition":"Лига","phase":"prematch","kickoff_at":self.at})
        for score in ("1:0","2:0"):
            store.observation("event_outcome","100",self.at,{"score_text":score,"result_key":"result-100","method":"sport_radar_names_time"})
            store.observation("result","result-100",self.at,{"sport_id":1,"record":{"member1":"Команда 0","member2":"Гости","date":self.at,"score":score}})
        store.observation("widget_data","100",self.at,{"statistics_id":"700","coverage":{"complete":False,"table_period_ids":["1"]},
            "views":{"groups":{"season":{"expected":6,"captured":4}},"missing":[{"card_id":"card__form","reason":"card_absent"}]}})
        run = store.begin_run()
        store.finish_run(run,"partial",{"source_exit_code":0,"errors":[],"results":{"requests":[{"error":"result_timeout"}]}})
        return store

    def test_missing_database_is_not_created(self):
        folder = self.state/"does-not-exist"
        self.assertEqual(MonitorReader(folder).read()["availability"],"missing")
        self.assertFalse(folder.exists())

    def test_reader_sees_live_updates_deduplicates_and_never_writes(self):
        store = self.populate()
        reader = MonitorReader(self.state)
        data = reader.read(size=2)
        self.assertEqual(data["counts"]["event_outcome"],2)
        self.assertEqual(data["totals"]["linked"],1)
        self.assertEqual(data["results"][0]["score"],"2:0")
        self.assertEqual(data["results"][0]["event_id"],"100")
        with closing(readonly(store.path)) as db:
            with self.assertRaises(sqlite3.OperationalError):
                db.execute("DELETE FROM observations")
        store.observation("market_document","101",self.at,{"home":"Обновлено","away":"Гости","sport":"football","kickoff_at":self.at})
        fresh = reader.read(size=2)
        self.assertEqual(fresh["counts"]["market_document"],4)
        self.assertEqual(fresh["matches"][0]["home"],"Обновлено")
        second = reader.read(page=1,size=2)
        self.assertEqual([r["entity_id"] for r in second["matches"]],["100"])
        self.assertEqual(second["counts"],fresh["counts"])

    def test_unowned_database_and_redirecting_symlink_are_refused(self):
        path = self.state/"dataset.sqlite3"
        with closing(sqlite3.connect(path)) as db:
            db.execute("CREATE TABLE metadata(key TEXT,value TEXT)")
            db.execute("INSERT INTO metadata VALUES ('owner','different-project')")
            db.commit()
        before = path.read_bytes()
        with self.assertRaises(ValueError):
            MonitorReader(self.state).read()
        self.assertEqual(path.read_bytes(),before)
        folder = self.state/"monitor";folder.mkdir()
        (folder/"dataset.sqlite3").symlink_to(path)
        with self.assertRaises(ValueError):
            MonitorReader(folder).read()

    def test_runtime_and_lock_distinguish_live_writer_from_old_telemetry(self):
        store = self.populate()
        lock = (self.state/"collector.lock").open("w")
        self.addCleanup(lock.close)
        fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        with Runtime(store,interval=300,sport="football"):
            update_runtime(store,phase="filters",completed=2,total=5,current_match="Команда 0 — Гости")
            data = MonitorReader(self.state).read()
            self.assertTrue(data["writer"])
            self.assertEqual(data["runtime"]["phase"],"filters")
            self.assertEqual(collector_health(data)[0],"СБОРЩИК РАБОТАЕТ")
        fcntl.flock(lock,fcntl.LOCK_UN)
        data = MonitorReader(self.state).read()
        self.assertEqual(data["runtime"]["phase"],"stopped")
        self.assertEqual(collector_health(data)[0],"СБОРЩИК ОСТАНОВЛЕН")

    def test_all_views_resize_and_source_strings_cannot_inject_terminal_markup(self):
        self.populate()
        reader = MonitorReader(self.state)
        data = reader.read(event_id="100")
        for width,height in ((80,30),(140,40),(45,15)):
            for view in ("overview","matches","results","coverage","errors"):
                screen = Dashboard(ZoneInfo("Europe/Moscow"),view)
                output = io.StringIO()
                console = Console(file=output,width=width,height=height,no_color=True)
                console.print(screen.render(data,width,height))
                rendered = output.getvalue()
                self.assertIn("DOSCBOT" if view=="overview" or width<62 else "1",rendered)
                self.assertNotIn("\x1b",rendered)
                self.assertTrue(all(len(line)<=width for line in rendered.splitlines()))
        self.assertEqual(clean("\x1b[31mКоманда\x00\n[red]"),"[31mКоманда[red]")
        screen = Dashboard(ZoneInfo("UTC"),"results")
        self.assertTrue(screen.key("\n",data))
        self.assertEqual(screen.event_id,"100")
        screen.key("esc",data)
        self.assertIsNone(screen.event_id)
        screen.view = "matches"
        screen.event_id = "100"
        output = io.StringIO()
        Console(file=output,width=100,height=35,no_color=True).print(screen.render(data,100,35))
        self.assertIn("Результат источника: 2:0",output.getvalue())
