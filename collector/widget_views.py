"""Collect the public filter states observed in the user's StatsHub capture.

Only click scoped, aria-confirmed source controls and same-match links.
Source JSON is captured independently by WidgetCapture before navigation.
"""

import asyncio
import json
import time
from urllib.parse import urljoin, urlsplit

from .store import now
from .widget_capture import DATA_HOSTS, PROVIDERS


CARD_SCRIPT = r'''({cardId, groups=[], labels=[], action='read'}) => {
    const cards=[...document.querySelectorAll('[data-testid]')].filter(e=>e.dataset.testid===cardId && e.getClientRects().length);
    if(cards.length!==1) return {ok:false, reason:cards.length ? 'ambiguous_card' : 'card_absent'};
    const card=cards[0], text=e=>(e.innerText || '').replace(/[\u200b-\u200f\u202a-\u202e\u2066-\u2069]/g,'').trim();
    const buttons=[...card.querySelectorAll('button')].filter(e=>e.getClientRects().length);
    const selected=[];
    for(let i=0;i<groups.length;i++) {
        const matches=groups[i].map(label=>buttons.filter(b=>text(b)===label));
        if(matches.some(v=>v.length!==1)) return {ok:false,reason:'filter_absent_or_ambiguous'};
        const group=matches.map(v=>v[0]);
        if(group.some(b=>!['true','false'].includes(b.getAttribute('aria-pressed'))) ||
           group.filter(b=>b.getAttribute('aria-pressed')==='true').length!==1)
            return {ok:false,reason:'filter_state_unconfirmed'};
        selected.push(text(group.find(b=>b.getAttribute('aria-pressed')==='true')));
        if(labels.length) {
            const target=group.find(b=>text(b)===labels[i]);
            if(!target || target.disabled) return {ok:false,reason:'filter_disabled'};
            if(action==='click' && target.getAttribute('aria-pressed')!=='true') {
                target.click(); return {ok:true,clicked:true};
            }
        }
    }
    if(labels.length && labels.some((label,i)=>selected[i]!==label)) return {ok:false,reason:'filter_not_selected'};
    // Read from the rendered source, while removing private form text.
    let content=text(card);
    for(const f of card.querySelectorAll('form')) if(text(f)) content=content.split(text(f)).join('');
    const rows=[], percentages=[];
    if(cardId==='card__seasonStatistics') {
        for(const row of card.querySelectorAll('div')) {
            if(row.children.length!==3 || [...row.children].some(e=>e.tagName!=='SPAN')) continue;
            const [home,label,away]=[...row.children].map(text);
            if(!label || !home || !away) continue;
            const number=v=>/^-?\d+(?:[.,]\d+)?$/.test(v) ? Number(v.replace(',','.')) : null;
            rows.push({label,home_text:home,away_text:away,home:number(home),away:number(away)});
        }
    }
    if(cardId==='card__form') {
        for(const p of card.querySelectorAll('p')) {
            if(!/^\d+(?:[.,]\d+)?%$/.test(text(p))) continue;
            for(let parent=p.parentElement;parent && parent!==card;parent=parent.parentElement) {
                const names=parent.querySelectorAll('thead th');
                if(names.length===1) {
                    percentages.push({team_name:text(names[0]),percent:Number(text(p).replace('%','').replace(',','.'))});
                    break;
                }
            }
        }
    }
    return {ok:true,selected,rows,percentages,text:content.slice(0,100000),truncated:content.length>100000,
            loading:!!card.querySelector('[aria-busy="true"],[role="progressbar"]')};
}'''

SEASON_GROUPS = [["Итоги", "За матч", "Серия"], ["Общее", "Хозяева / Гости"]]
TABLE_GROUPS = [["Всего", "Хозяева", "Гости", "Завершен"], ["Результат", "1-й тайм", "2-й тайм"]]
FORM_GROUPS = [["Общее", "Хозяева", "Гости"], ["5", "Сезон"]]


def matches_frame(frame, identifier):
    parsed = urlsplit(frame.url)
    return (parsed.scheme == "https" and parsed.hostname in PROVIDERS
            and parsed.path.rstrip("/") in [f"/winline/ru/match/{identifier}{suffix}"
                                            for suffix in ("", "/report", "/statistics")])


async def selected_snapshot(frame, card_id, groups, labels, deadline, network=None):
    args = {"cardId": card_id, "groups": groups, "labels": list(labels), "action": "click"}
    for _ in range(len(groups) + 1):
        state = await frame.evaluate(CARD_SCRIPT, args)
        if not state.get("ok"):
            return None, state.get("reason", "unavailable")
        if not state.get("clicked"):
            break
        await asyncio.sleep(.15)
    else:
        return None, "selection_failed"
    args["action"] = "read"
    # Equal figures in two different filters are legitimate. Confirm both the
    # selected source state and stable data, rather than requiring a change.
    await asyncio.sleep(.4)
    previous, stable = None, 0
    while time.monotonic() < deadline:
        state = await frame.evaluate(CARD_SCRIPT, args)
        if not state.get("ok"):
            return None, state.get("reason", "unavailable")
        signature = json.dumps([state.get("selected"), state.get("text"), state.get("rows")], ensure_ascii=False)
        settled = not network or (not network["pending"] and time.monotonic()-network["last_activity"] >= .3)
        stable = stable + 1 if previous == signature and not state.get("loading") and settled else 0
        previous = signature
        if stable >= 2:
            return {"observed_at": now(), "card_id": card_id, "selected": state["selected"],
                    "rows": state["rows"], "percentages": state["percentages"],
                    "text": state["text"], "truncated": state["truncated"]}, None
        await asyncio.sleep(.2)
    return None, "stability_timeout"


async def collect_widget_views(page, statistics_id, *, budget_seconds=45):
    identifier = str(statistics_id)
    result = {"format": "doscbot.widget-views.v1", "statistics_id": identifier,
              "started_at": now(), "state": "partial", "snapshots": [], "missing": [], "groups": {}}
    deadline = time.monotonic() + budget_seconds
    frames = [f for f in page.frames if matches_frame(f, identifier)]
    if len(frames) != 1:
        result["missing"].append({"reason": "provider_frame_absent_or_ambiguous"})
        result["finished_at"] = now()
        return result
    frame = frames[0]
    network = {"pending": set(), "last_activity": time.monotonic()}
    def requested(request):
        try:
            parsed = urlsplit(request.url)
            if (request.resource_type in ("xhr", "fetch") and parsed.hostname in DATA_HOSTS
                    and "/gismo/" in parsed.path and matches_frame(request.frame, identifier)):
                network["pending"].add(request)
                network["last_activity"] = time.monotonic()
        except Exception:
            pass
    def completed(request):
        if request in network["pending"]:
            network["pending"].discard(request)
            network["last_activity"] = time.monotonic()
    page.on("request", requested)
    page.on("requestfinished", completed)
    page.on("requestfailed", completed)
    async def visit(view):
        suffix = "" if view == "overview" else "/" + view
        target_path = f"/winline/ru/match/{identifier}{suffix}"
        if urlsplit(frame.url).path.rstrip("/") == target_path:
            return True
        # Use only a same-match destination actually present in the source.
        links = frame.locator(f'a[href$="{target_path}"]')
        if await links.count() < 1:
            result["missing"].append({"view": view, "reason": "view_link_absent"})
            return False
        target = urljoin(frame.url, await links.first.get_attribute("href"))
        parsed, current = urlsplit(target), urlsplit(frame.url)
        if parsed.scheme != "https" or parsed.netloc != current.netloc or parsed.path != target_path:
            result["missing"].append({"view": view, "reason": "view_link_mismatch"})
            return False
        response = await frame.goto(target, wait_until="domcontentloaded",
                                    timeout=max(1, min(12000, int((deadline-time.monotonic())*1000))))
        if response and response.status >= 400:
            result["missing"].append({"view": view, "reason": f"http_{response.status}"})
            return False
        return matches_frame(frame, identifier)

    async def variants(view, card_id, groups, combinations):
        key = view + ":" + card_id
        result["groups"][key] = {"expected": len(combinations), "captured": 0}
        for labels in combinations:
            if time.monotonic() >= deadline:
                result["missing"].append({"view": view, "card_id": card_id, "reason": "time_budget"})
                return
            try:
                # Card hydration can happen after DOMContentLoaded.
                await frame.locator(f'[data-testid="{card_id}"]').wait_for(
                    state="attached", timeout=max(1, min(3500, int((deadline-time.monotonic())*1000))))
                snapshot, reason = await selected_snapshot(frame, card_id, groups, labels,
                                                           min(deadline, time.monotonic()+5), network)
            except Exception:
                snapshot, reason = None, "card_unavailable"
            if snapshot:
                result["snapshots"].append({**snapshot, "view": view})
                result["groups"][key]["captured"] += 1
                if snapshot["truncated"]:
                    result["missing"].append({"view": view, "card_id": card_id, "reason": "text_truncated"})
            else:
                result["missing"].append({"view": view, "card_id": card_id, "labels": list(labels), "reason": reason})
                if reason in ("card_absent", "card_unavailable", "filter_absent_or_ambiguous", "filter_state_unconfirmed"):
                    return
    try:
        if await visit("overview"):
            await variants("overview", "card__seasonStatistics", SEASON_GROUPS,
                           [(m, s) for m in SEASON_GROUPS[0] for s in SEASON_GROUPS[1]])
            # Tables contain all home/away columns in JSON, but periods request
            # separate source documents. Confirm every exposed UI state too.
            await variants("overview", "card__leagueTable", TABLE_GROUPS,
                           [(s, p) for p in TABLE_GROUPS[1] for s in TABLE_GROUPS[0]])
        if time.monotonic() < deadline and await visit("statistics"):
            await variants("statistics", "card__leagueTable", TABLE_GROUPS[:1], [(s,) for s in TABLE_GROUPS[0]])
            await variants("statistics", "card__form", FORM_GROUPS,
                           [(s, w) for w in FORM_GROUPS[1] for s in FORM_GROUPS[0]])
    except Exception:
        result["missing"].append({"reason": "view_unavailable"})
    finally:
        page.remove_listener("request", requested)
        page.remove_listener("requestfinished", completed)
        page.remove_listener("requestfailed", completed)
    if not result["missing"] and len(result["groups"]) == 4:
        result["state"] = "captured"
    result["finished_at"] = now()
    return result
