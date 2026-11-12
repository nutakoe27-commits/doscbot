"""A keyboard-driven Rich dashboard for the collector's own local databases."""

from contextlib import AbstractContextManager
from datetime import datetime, timezone
import os
import select
import sys
import termios
import time
import tty
import unicodedata
from zoneinfo import ZoneInfo, ZoneInfoNotFoundError

from rich import box
from rich.align import Align
from rich.console import Console, Group
from rich.layout import Layout
from rich.live import Live
from rich.panel import Panel
from rich.progress_bar import ProgressBar
from rich.table import Table
from rich.text import Text

from .monitor_data import MonitorReader
from .store import instant
from .monitor_sections import section_blocks, Scrolled
from .profiles import PROFILES

CYAN, VIOLET, GREEN, AMBER, RED, MUTED = "#67e8f9", "#c4b5fd", "#6ee7b7", "#fcd34d", "#fda4af", "#94a3b8"
BORDER, BACKGROUND = "#334155", "#0b1220"
VIEWS = {"overview":"Обзор", "matches":"Матчи", "results":"Результаты", "coverage":"Покрытие", "errors":"Ошибки"}
PHASES = {"starting":"Запуск", "catalog":"Снимок линии", "import":"Сохранение линии", "details":"Выбор матчей",
          "markets":"Коэффициенты", "statistics":"Статистика", "filters":"Фильтры виджета", "results":"Архив результатов",
          "link_results":"Привязка результатов", "waiting":"Ожидание цикла", "stopped":"Сборщик остановлен"}
STATES = {"ok":("Готово", GREEN), "partial":("Частично", AMBER), "running":("Сбор", CYAN),
          "source_error":("Ошибка источника", RED), "error":("Ошибка", RED), "stopped":("Остановлен", MUTED)}


def clean(value, limit=180):
    # Team/competition strings are source data, never terminal escapes/markup.
    return " ".join("".join(c for c in str(value if value is not None else "—")
                             if not unicodedata.category(c).startswith("C")).split())[:limit]


def text(value, style="", limit=180):
    return Text(clean(value, limit), style=style)


def age(value):
    try:
        return max(0, (datetime.now(timezone.utc)-instant(value)).total_seconds())
    except (ValueError, TypeError):
        return None


def duration(value):
    if value is None:
        return "—"
    seconds = max(0, int(value))
    if seconds < 60:
        return f"{seconds} с"
    if seconds < 3600:
        return f"{seconds//60} м {seconds%60:02d} с"
    return f"{seconds//3600} ч {(seconds%3600)//60:02d} м"


def number(value):
    return "—" if value is None else f"{value:,}".replace(",", " ")


def stamp(value, zone):
    try:
        return instant(value).astimezone(zone).strftime("%d.%m %H:%M")
    except (ValueError, TypeError):
        return "—"


def table(*columns):
    item = Table(expand=True, box=box.SIMPLE_HEAD, border_style=BORDER, header_style=f"bold {MUTED}",
                 padding=(0, 1), show_edge=False)
    for name in columns:
        item.add_column(name, overflow="ellipsis", no_wrap=True)
    return item


def panel(content, title, subtitle=None, color=BORDER):
    return Panel(content, title=text(title, f"bold {CYAN}"), subtitle=text(subtitle, MUTED) if subtitle else None,
                 border_style=color, box=box.ROUNDED, padding=(0, 1), style=f"on {BACKGROUND}")


def collector_health(data):
    runtime, writer = data.get("runtime", {}), data.get("writer")
    if writer is False:
        return "СБОРЩИК ОСТАНОВЛЕН", RED
    if writer is None:
        return "СОСТОЯНИЕ НЕИЗВЕСТНО", AMBER
    beat = age(runtime.get("heartbeat_at"))
    if beat is None:
        return "РАБОТАЕТ · НЕТ ТЕЛЕМЕТРИИ", AMBER
    if beat > 25:
        return "НЕТ СВЕЖЕГО СИГНАЛА", AMBER
    return "СБОРЩИК РАБОТАЕТ", GREEN


class Dashboard:
    def __init__(self, zone, view="overview"):
        self.zone, self.view = zone, view
        self.selected, self.page, self.event_id = 0, 0, None
        self.paused = False
        self.refresh = 2
        self.detail_index,self.detail_scroll=0,0

    def page_size(self, width, height):
        return max(1, (height-9)//2-5 if self.view == "overview" and width >= 112 else height-15)

    def items(self, data):
        key = {"overview":"matches", "matches":"matches", "results":"results", "coverage":"coverage"}.get(self.view)
        return data.get(key, []) if key else []

    def key(self, key, data):
        """Return true if an immediate data refresh is needed."""
        if key in "12345" and len(key) == 1:
            self.view = list(VIEWS)[int(key)-1]
            self.selected, self.page, self.event_id = 0, 0, None
            return True
        if key == "\t":
            self.view = list(VIEWS)[(list(VIEWS).index(self.view)+1)%len(VIEWS)]
            self.selected, self.page, self.event_id = 0, 0, None
            return True
        if key == "esc":
            self.event_id = None
        elif self.event_id and key in ('[',']','left','right','up','down','j','k','next','prev','g'):
            if key in ('[','left',']','right'):
                self.detail_index=max(0,self.detail_index+(-1 if key in ('[','left') else 1))
                self.detail_scroll=0
            elif key=='g':
                self.detail_scroll=0
            else:
                self.detail_scroll=max(0,self.detail_scroll+(-12 if key=='prev' else 12 if key=='next' else -1 if key in ('up','k') else 1))
            return False
        elif key in ("down", "j"):
            self.selected = min(max(0, len(self.items(data))-1), self.selected+1)
        elif key in ("up", "k"):
            self.selected = max(0, self.selected-1)
        elif key in ("right", "next") and len(self.items(data)) >= data.get("page_size", 20):
            self.page += 1
            self.selected, self.event_id = 0, None
            return True
        elif key in ("left", "prev"):
            self.page = max(0, self.page-1)
            self.selected, self.event_id = 0, None
            return True
        elif key in ("\r", "\n") and self.items(data):
            item = self.items(data)[min(self.selected, len(self.items(data))-1)]
            self.event_id = item.get("event_id") if self.view == "results" else item.get("entity_id")
            self.detail_index,self.detail_scroll=0,0
            return True
        elif key == "p":
            self.paused = not self.paused
        return key == "r"

    def matches(self, data, rows, narrow=False):
        result = table("", "Матч", "Начало", "Результат" if narrow else "Счёт / ожидание", "Данные")
        for i,item in enumerate(data.get("matches", [])[:rows]):
            outcome = item.get("outcome") or {}
            pending = "предстоит" if (age(item.get("kickoff")) is not None and instant(item["kickoff"]) > datetime.now(timezone.utc)) else "ждём результат"
            status = outcome.get("score") or pending
            coverage = "полно" if item.get("complete") is True else "частично" if item.get("complete") is False else "рынки"
            result.add_row(text("›" if i == self.selected else "", CYAN),
                text(f"{item.get('home')} — {item.get('away')}", "bold" if i == self.selected else ""),
                text(stamp(item.get("kickoff"), self.zone)), text(status, GREEN if outcome else MUTED),
                text(coverage, GREEN if coverage == "полно" else AMBER if coverage == "частично" else MUTED))
        if not data.get("matches"):
            result.add_row("", text("Подробных матчей пока нет", MUTED), "—", "—", "—")
        return result

    def results(self, data, rows):
        result = table("", "Матч", "Счёт", "Дата", "Связь с событием")
        for i,item in enumerate(data.get("results", [])[:rows]):
            result.add_row(text("›" if i == self.selected and self.view == "results" else "", CYAN),
                text(f"{item.get('home')} — {item.get('away')}"), text(item.get("score"), f"bold {GREEN}"),
                text(stamp(item.get("start_at"), self.zone)), text(item.get("event_id") or "—", CYAN if item.get("event_id") else MUTED))
        if not data.get("results"):
            result.add_row("", text("Архив результатов пока пуст", MUTED), "—", "—", "—")
        return result

    def coverage(self, data, rows):
        result = table("", "Матч / ID", "Фильтры", "Таймы", "Покрытие", "Пропуски")
        for i,item in enumerate(data.get("coverage", [])[:rows]):
            coverage, groups = item.get("coverage", {}), item.get("groups", {})
            done, total = sum(g.get("captured",0) for g in groups.values()), sum(g.get("expected",0) for g in groups.values())
            complete = coverage.get("complete")
            result.add_row(text("›" if i == self.selected else "", CYAN),
                text(f"{item.get('home')} — {item.get('away')}" if item.get("home") else item["entity_id"]),
                text(f"{done}/{total}" if total else "—", GREEN if total and done == total else AMBER),
                text(" / ".join({"1":"Матч","2":"1Т","3":"2Т"}.get(p,p) for p in coverage.get("table_period_ids", [])) or "—"),
                text("Полное" if complete else "Частичное", GREEN if complete else AMBER), text(len(item.get("missing", []))))
        if not data.get("coverage"):
            result.add_row("", text("Снимков нового виджета пока нет", MUTED), "—", "—", "—", "—")
        return result

    def activity(self, data):
        runtime = data.get("runtime", {})
        phase = PHASES.get(runtime.get("phase"), "Нет телеметрии")
        lines = [text(phase, f"bold {VIOLET}")]
        if runtime.get("current_match"):
            lines.append(text(runtime["current_match"]))
        if runtime.get("phase") == "waiting":
            try:
                left = (instant(runtime["next_run_at"])-datetime.now(timezone.utc)).total_seconds()
            except (KeyError, TypeError, ValueError):
                left = None
            lines.append(text(f"Следующий цикл через {duration(left)}", CYAN))
        elif runtime.get("total"):
            progress = Table.grid(expand=True,padding=(0,1))
            progress.add_column(ratio=1);progress.add_column(width=7)
            progress.add_row(ProgressBar(total=runtime["total"], completed=runtime.get("completed",0), complete_style=CYAN, finished_style=GREEN),
                             text(f"{runtime.get('completed',0)} / {runtime['total']}",MUTED))
            lines.append(progress)
        source_age = age(data.get("source_at"))
        stale = source_age is not None and source_age > max(900, runtime.get("interval",300)*3)
        lines.append(text(f"Линия обновлена: {duration(source_age)} назад" if source_age is not None else "Линия ещё не получена", AMBER if stale else MUTED))
        if stale:
            lines.append(text("Линия устарела: проверьте ошибки",AMBER))
        if runtime.get("started_at"):
            lines.append(text(f"В работе: {duration(age(runtime['started_at']))}", MUTED))
        runs = data.get("runs", [])
        if runs:
            latest = runs[0] if runs[0]["state"] != "running" else next((r for r in runs if r["state"] != "running"), None)
            if latest:
                label,color = STATES.get(latest["state"], (latest["state"], MUTED))
                lines += [Text(""), text(f"Последний цикл #{latest['id']}", MUTED), text(label, color)]
                details = latest["details"]
                lines.append(text(f"Снимков: {details.get('samples',0)} · JSON: {details.get('widget_json',0)}"))
                archive = details.get("results", {})
                lines.append(text(f"Архив: {archive.get('successful',0)}/{archive.get('attempted',0)} запросов"))
        return Group(*lines)

    def errors(self, data, rows):
        result = table("Цикл", "Время", "Состояние", "Причина")
        count = 0
        for run in data.get("runs", []):
            details = run.get("details", {})
            messages = [str(e.get("type", "unknown"))+f" · матч {e['event_id']}" if e.get("event_id") else str(e.get("type", "unknown"))
                        for e in details.get("errors", [])]
            if details.get("reason") or details.get("type"):
                messages.append(str(details.get("reason") or details.get("type")))
            if details.get("source_exit_code"):
                messages.append("Код источника: "+str(details["source_exit_code"]))
            for request in details.get("results", {}).get("requests", []):
                if request.get("error"):
                    messages.append(str(request["error"]))
            if details.get("results", {}).get("error"):
                messages.append(str(details["results"]["error"]))
            if details.get("outcomes", {}).get("error"):
                messages.append("Привязка: "+str(details["outcomes"]["error"]))
            if not messages and run["state"] not in ("error", "source_error"):
                continue
            label,color = STATES.get(run["state"], (run["state"], MUTED))
            result.add_row(text(f"#{run['id']}"), text(stamp(run["started_at"], self.zone)), text(label,color), text("; ".join(messages) or "нет подробностей"))
            count += 1
            if count >= rows:
                break
        if not count:
            result.add_row("—", "—", text("Ошибок нет",GREEN), text("В последних 20 циклах",MUTED))
        return result

    def detail(self, data, rows):
        item = data.get("detail") or {}
        event, widget, outcome = item.get("market_document") or {}, item.get("widget_data") or {}, item.get("event_outcome") or {}
        title = f"{event.get('home','—')} — {event.get('away','—')}  ·  {item.get('event_id',self.event_id)}"
        blocks=section_blocks(item)
        self.detail_index=min(self.detail_index,len(blocks))
        if self.detail_index:
            name,content=blocks[self.detail_index-1]
            return panel(Scrolled(content,self.detail_scroll,max(1,rows-3)),title,
                         f"{self.detail_index}/{len(blocks)} · {name[:65]} · [ ] раздел · ↑↓ текст · Esc назад")
        info = text(f"{event.get('competition','—')}  ·  {stamp(event.get('kickoff_at'),self.zone)}")
        method = {"sport_radar_names_time":"проверены ID, команды и время", "sport_names_time_unique":"точное совпадение команд и времени"}.get(outcome.get("method"),"связь ещё не подтверждена")
        score = text(f"Результат источника: {outcome.get('score_text','ожидается')}  ·  {method}", GREEN if outcome else MUTED)
        sport=widget.get('sport') or event.get('sport','football')
        stats = table("Показатель сезона · всего", clean(event.get("home")), clean(event.get("away")))
        sides = []
        for side in ("home", "away"):
            team_id = (widget.get("match") or {}).get(side,{}).get("id")
            candidates = [s for s in widget.get("season_statistics", []) if s["team"].get("id") == team_id and s["window"]["kind"] == "season"]
            sides.append(candidates[-1]["scopes"]["total"] if candidates else {})
        metric_rows=(("wins","Победы"),("goals_for","Забито"),("goals_against","Пропущено"),
                     ("both_teams_scored","Обе забивали"),("failed_to_score","Не забивали"),("clean_sheets","Сухие матчи")) if sport=='football' else ()
        for key,label in metric_rows:
            stats.add_row(text(label), text(sides[0].get(key)), text(sides[1].get(key)))
        missing = (widget.get("views") or {}).get("missing", [])
        notes = [text("Покрытие: "+("полное" if widget.get("coverage",{}).get("complete") else "частичное / ещё не собрано"),
                      GREEN if widget.get("coverage",{}).get("complete") else AMBER)]
        diagnostic=item.get('event_diagnostic') or {}
        reason=diagnostic.get('reason') or ('statistics_link_'+event['statistics_link_status'] if event.get('statistics_link_status') in ('missing','ambiguous') else None)
        explanations={'statistics_link_missing':'Ссылка на статистику у этого события не обнаружена.',
                      'statistics_link_ambiguous':'Несколько ссылок: статистика не привязана однозначно.',
                      'statistics_blocked':'Провайдер ограничил доступ к статистике.',
                      'rich_normalization_error':'Исходные документы сохранены; обработка их структуры не завершена.',
                      'no_current_market_feed':'Свежая линия события не подтверждена.'}
        if reason:
            notes.append(text(explanations.get(reason,'Причина: '+reason),AMBER))
        if diagnostic.get('error_type'):
            notes.append(text(f"Этап: {diagnostic.get('stage','—')} · {diagnostic['error_type']}",RED))
        for entry in missing[:max(1,rows-15)]:
            card = {"card__form":"Форма", "card__seasonStatistics":"Статистика сезона", "card__leagueTable":"Таблица"}.get(entry.get("card_id"),"Виджет")
            reason = {"card_absent":"блок не появился", "card_unavailable":"блок не прочитан", "time_budget":"исчерпано время обхода",
                      "stability_timeout":"данные не стабилизировались", "filter_absent_or_ambiguous":"переключатель не найден однозначно",
                      "filter_state_unconfirmed":"состояние переключателя не подтверждено", "text_truncated":"текст обрезан"}.get(entry.get("reason"),"часть данных не подтверждена")
            notes.append(text(f"• {card}: {reason}", AMBER))
        index=[text(f"Вид спорта: {PROFILES.get(sport,{}).get('label',sport)} · разделов: {len(blocks)}",CYAN),
               text('Нажмите ] или →: вся статистика, таблицы, история и рынки. ↑↓ прокручивают текст.',MUTED)]
        index.extend(text(f"{n+1}. {name}",MUTED) for n,(name,_) in enumerate(blocks[:5]))
        summary=[info,score,Text('')]+([stats] if metric_rows else [])+index+notes
        return panel(Scrolled(Group(*summary),self.detail_scroll,max(1,rows-3)), title, "[ ] раздел · ↑↓ текст · Esc назад · 1–5 вкладки")

    def render(self, data, width, height):
        if width < 62 or height < 17:
            return panel(Group(text("DOSCBOT  /  LIVE DATA", f"bold {CYAN}"),
                text("Увеличьте терминал хотя бы до 62 × 17.",AMBER), self.activity(data),
                text("q — выход · --once — текстовый снимок",MUTED)), "Монитор")
        rows = max(1, height-16)
        self.selected = min(self.selected, max(0, len(self.items(data))-1))
        label,color = collector_health(data)
        heading = Table.grid(expand=True)
        heading.add_column(ratio=1); heading.add_column(justify="right")
        heading.add_row(Text.assemble(("DOSCBOT", f"bold {CYAN}"),("  /  LIVE DATA", f"bold {VIOLET}")), text(label,color))
        heading.add_row(text("Наблюдения · статистика · результаты", MUTED),
                        text(datetime.now(self.zone).strftime("%d.%m.%Y  %H:%M:%S"), MUTED))
        root = Layout()
        root.split_column(Layout(heading, size=2), Layout(name="metrics",size=4), Layout(name="tabs",size=1),
                          Layout(name="body",ratio=1), Layout(name="footer",size=2))
        totals, counts = data.get("totals",{}), data.get("counts",{})
        cards = []
        for title,value,caption in (("ЛИНИЯ",totals.get("catalog"),"событий в снимке"),
                                    ("СНИМКИ",totals.get("samples",0),f"матчей подробно: {totals.get('detailed',0)}"),
                                    ("РЕЗУЛЬТАТЫ",totals.get("results",0),f"связано с матчами: {totals.get('linked',0)}"),
                                    ("НАБЛЮДЕНИЯ",sum(counts.values()),"сохранённые версии")):
            cards.append(panel(Group(text(number(value),f"bold {CYAN}"),
                                     Text(clean(caption),style=MUTED,no_wrap=True,overflow="ellipsis")),title))
        metrics = Table.grid(expand=True,padding=(0,1))
        for _ in cards:
            metrics.add_column(ratio=1)
        metrics.add_row(*cards)
        root["metrics"].update(metrics)
        tabs = Text()
        for i,(key,name) in enumerate(VIEWS.items(),1):
            tabs.append(f" {i} {name} ", style=f"bold {CYAN} on #164e63" if self.view == key else MUTED)
        if self.paused:
            tabs.append("  ПАУЗА ЭКРАНА",style=AMBER)
        root["tabs"].update(tabs)
        if data.get("availability") != "ready":
            message = ("Новая база ещё не создана. Запустите doscbot-collector.service — монитор дождётся данных."
                       if data.get("availability") == "missing" else "База временно недоступна. Монитор повторит чтение автоматически.")
            root["body"].update(panel(Align.center(text(message,AMBER),vertical="middle"),"Ожидание данных"))
        elif self.event_id:
            root["body"].update(self.detail(data, height-10))
        elif self.view == "overview" and width >= 112:
            body = Layout()
            body.split_row(Layout(name="tables",ratio=3),Layout(panel(self.activity(data),"Сейчас"),ratio=1))
            body["tables"].split_column(Layout(panel(self.matches(data,self.page_size(width,height)),"Последние подробные матчи"),ratio=1),
                                       Layout(panel(self.results(data,self.page_size(width,height)),"Последние результаты"),ratio=1))
            root["body"].update(body)
        elif self.view in ("overview","matches"):
            root["body"].update(panel(self.matches(data,rows,width<90),"Подробно собранные матчи",f"Страница {self.page+1} · выбранный матч: Enter"))
        elif self.view == "results":
            root["body"].update(panel(self.results(data,rows),"Последние полученные результаты",f"Страница {self.page+1} · текст счёта из ResultService"))
        elif self.view == "coverage":
            root["body"].update(panel(self.coverage(data,rows),"Покрытие карточек и периодов",f"Страница {self.page+1} · полное покрытие проверяется отдельно от успеха цикла"))
        else:
            root["body"].update(panel(self.errors(data,rows),"Ошибки источника и сбора"))
        controls = ("[ ] разделы · ↑↓ текст · PgUp/PgDn · Esc назад · 1–5 вкладки · q выход" if self.event_id else
                    "1–5 вкладки   ↑↓ выбор   ←→ страницы   Enter карточка   r обновить   p пауза   q выход" if width >= 100
                    else "1–5 вкладки · ↑↓ выбор · ←→ стр. · Enter · r · q выход")
        footer = Text(controls, style=MUTED)
        phase = PHASES.get(data.get("runtime",{}).get("phase"),"нет телеметрии")
        next_at = data.get("runtime",{}).get("next_run_at")
        if next_at and data.get("runtime",{}).get("phase") == "waiting":
            try:
                phase += " · через "+duration((instant(next_at)-datetime.now(timezone.utc)).total_seconds())
            except (ValueError,TypeError):
                pass
        footer.append(f"\n{phase}  ·  экран: {self.refresh:g} с  ·  {self.zone.key}",style=MUTED)
        root["footer"].update(footer)
        return root


class Keyboard(AbstractContextManager):
    def __enter__(self):
        self.fd = sys.stdin.fileno()
        self.previous = termios.tcgetattr(self.fd)
        tty.setcbreak(self.fd)
        return self

    def read(self, timeout=.1):
        if not select.select([self.fd],[],[],timeout)[0]:
            return []
        data = os.read(self.fd,64)
        if not data:
            return ["q"]
        if data.startswith(b"\x1b") and select.select([self.fd],[],[],.02)[0]:
            data += os.read(self.fd,64)
        value = data.decode("ascii",errors="ignore")
        for code,key in (("\x1b[A","up"),("\x1b[B","down"),("\x1b[C","right"),("\x1b[D","left"),
                         ("\x1b[5~","prev"),("\x1b[6~","next")):
            value = value.replace(code,"|"+key+"|")
        result=[]
        for part in value.split("|"):
            result.extend([part] if part in ("up","down","left","right","prev","next") else
                          ["esc" if char == "\x1b" else char for char in part])
        return result

    def __exit__(self,*_):
        termios.tcsetattr(self.fd,termios.TCSADRAIN,self.previous)


def run_monitor(state, *, once=False, refresh=2, zone="Europe/Moscow", no_color=False, view="overview"):
    try:
        timezone_info = ZoneInfo(zone)
    except ZoneInfoNotFoundError:
        raise ValueError("Unknown timezone: "+clean(zone)) from None
    console = Console(no_color=True if no_color else None, highlight=False)
    reader, dashboard = MonitorReader(state), Dashboard(timezone_info,view)
    dashboard.refresh = refresh
    if once or not sys.stdin.isatty() or not console.is_terminal or console.is_dumb_terminal:
        data = reader.read(size=12)
        console.print(dashboard.render(data,console.width,max(30,console.height)))
        return 1 if data["availability"] == "error" else 0
    try:
        data = reader.read(size=dashboard.page_size(console.width,console.height))
        with Keyboard() as keyboard, Live(console=console,screen=True,auto_refresh=False,redirect_stdout=False) as live:
            deadline, paint_at = 0, 0
            previous_size = None
            while True:
                immediate, changed = False, False
                for key in keyboard.read():
                    if key in ("q","Q","\x03"):
                        return 0
                    immediate = dashboard.key(key,data) or immediate
                    changed = True
                if immediate or (not dashboard.paused and time.monotonic() >= deadline):
                    data = reader.read(page=dashboard.page,size=dashboard.page_size(console.width,console.height),event_id=dashboard.event_id)
                    deadline = time.monotonic()+refresh
                    changed = True
                if changed or previous_size != console.size or (not dashboard.paused and time.monotonic()>=paint_at):
                    live.update(dashboard.render(data,console.width,console.height),refresh=True)
                    previous_size = console.size
                    paint_at = time.monotonic()+1
    except KeyboardInterrupt:
        return 0
