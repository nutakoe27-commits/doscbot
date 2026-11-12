"""Collect the public filter states observed in the user's StatsHub capture.

Only click scoped, aria-confirmed source controls and same-match links.
Source JSON is captured independently by WidgetCapture before navigation.
"""

import asyncio
import json
import time
from itertools import islice, product
from math import prod
from urllib.parse import urljoin, urlsplit

from .store import now
from .widget_capture import DATA_HOSTS, PROVIDERS


CARD_SCRIPT = r'''({cardId, groups=[], labels=[], action='read'}) => {
    const visible=e=>e.getClientRects().length>0 && getComputedStyle(e).visibility!=='hidden' &&
        getComputedStyle(e).display!=='none' && !e.closest('[hidden],[aria-hidden="true"]');
    let cards=[...document.querySelectorAll('[data-testid]')].filter(e=>e.dataset.testid===cardId && visible(e));
    if(cardId.startsWith('page:')) cards=[document.body];
    if(cardId.startsWith('heading:')) {
        cards=[...document.querySelectorAll('h3')].filter(e=>(e.innerText||'').trim()===cardId.slice(8) && visible(e)).map(h=>{
            let card=h.parentElement;
            for(let p=card.parentElement;p && p!==document.body;p=p.parentElement) {
                if(p.querySelectorAll('h3').length!==1 || (p.innerText||'').length>100000) break;
                card=p;
            }
            return card;
        });
    }
    if(['home','away'].includes(cardId)) cards=cards.filter(e=>e.querySelector('h3'));
    if(cards.length!==1) return {ok:false, reason:cards.length ? 'ambiguous_card' : 'card_absent'};
    const card=cards[0], text=e=>(e.innerText || '').replace(/[\u200b-\u200f\u202a-\u202e\u2066-\u2069]/g,'').trim();
    const label=e=>text(e).replace(/\s+/g,' ');
    const buttons=[...card.querySelectorAll('button')].filter(visible);
    const active=b=>b.getAttribute('aria-pressed') ?? b.getAttribute('aria-selected');
    const parents=[...new Set(buttons.map(b=>b.parentElement))];
    const sourceGroups=parents.map(node=>({node,buttons:[...node.children].filter(b=>b.tagName==='BUTTON'&&visible(b))})).filter(g=>g.buttons.length>=2);
    const selected=[];
    for(let i=0;i<groups.length;i++) {
        const wanted=groups[i].map(s=>s.replace(/\s+/g,' ').trim());
        const candidates=sourceGroups.filter(g=>g.buttons.length===wanted.length && g.buttons.every((b,j)=>label(b)===wanted[j]));
        const occurrence=groups.slice(0,i).filter(g=>JSON.stringify(g)===JSON.stringify(groups[i])).length;
        const expectedCount=groups.filter(g=>JSON.stringify(g)===JSON.stringify(groups[i])).length;
        if(candidates.length!==expectedCount) return {ok:false,reason:'filter_absent_or_ambiguous'};
        const group=candidates[occurrence].buttons;
        if(group.some(b=>!['true','false'].includes(active(b))) ||
           group.filter(b=>active(b)==='true').length!==1)
            return {ok:false,reason:'filter_state_unconfirmed'};
        selected.push(label(group.find(b=>active(b)==='true')));
        if(labels.length) {
            const target=group.find(b=>label(b)===labels[i]);
            if(!target || target.disabled) return {ok:false,reason:'filter_disabled'};
            if(action==='click' && active(target)!=='true') {
                target.click(); return {ok:true,clicked:true,group_index:i,target:labels[i]};
            }
        }
    }
    if(action==='check') return {ok:true,selected,
        loading:!!card.querySelector('[aria-busy="true"],[role="progressbar"]')};
    if(labels.length && labels.some((label,i)=>selected[i]!==label)) return {ok:false,reason:'filter_not_selected'};
    // Read from the rendered source, while removing private form text.
    let content=text(card);
    for(const f of card.querySelectorAll('form')) if(text(f)) content=content.split(text(f)).join('');
    const rows=[], percentages=[];
    {
        for(const row of card.querySelectorAll('div')) {
            if(row.children.length!==3 || [...row.children].some(e=>e.tagName!=='SPAN')) continue;
            const [home,label,away]=[...row.children].map(text);
            if(!label || !home || !away) continue;
            const number=v=>/^-?\d+(?:[.,]\d+)?$/.test(v) ? Number(v.replace(',','.')) : null;
            rows.push({label,home_text:home,away_text:away,home:number(home),away:number(away)});
        }
    }
    const tables=[...card.querySelectorAll('table')].slice(0,40).map(t=>({
        caption:t.querySelector('caption')?.innerText || '',
        headers:[...t.querySelectorAll('thead tr')].slice(0,10).map(r=>[...r.cells].slice(0,40).map(e=>text(e).slice(0,300))),
        rows:[...t.querySelectorAll('tbody tr')].slice(0,300).map(r=>[...r.cells].slice(0,40).map(e=>text(e).slice(0,300))),
        truncated:t.querySelectorAll('tbody tr').length>300 || [...t.querySelectorAll('tr')].some(r=>r.cells.length>40) || [...t.querySelectorAll('th,td')].some(e=>text(e).length>300)
    }));
    const discovered=[];
    for(const button of buttons) {
        const siblings=[...button.parentElement.children].filter(e=>e.tagName==='BUTTON' && visible(e));
        if(siblings.length<2 || discovered.some(g=>g.node===button.parentElement)) continue;
        const supported=siblings.every(b=>['true','false'].includes(active(b))) &&
            siblings.filter(b=>active(b)==='true').length===1;
        if(supported) discovered.push({node:button.parentElement,labels:siblings.map(label)});
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
    return {ok:true,selected:groups.length?selected:discovered.map(g=>label([...g.node.children].find(b=>visible(b)&&active(b)==='true'))),
            groups:discovered.map(g=>g.labels),title:cardId.startsWith('page:')?'Содержимое вкладки '+cardId.slice(5):text(card.querySelector('h3')||card).slice(0,300),
            rows,tables,percentages,text:content.slice(0,100000),truncated:content.length>100000 || tables.some(t=>t.truncated),
            loading:!!card.querySelector('[aria-busy="true"],[role="progressbar"]')};
}'''

INVENTORY_SCRIPT = r'''() => {
    const visible=e=>e.getClientRects().length>0 && getComputedStyle(e).visibility!=='hidden' &&
        getComputedStyle(e).display!=='none' && !e.closest('[hidden],[aria-hidden="true"]');
    const ids=[...new Set([...document.querySelectorAll('[data-testid]')].filter(e=>visible(e) &&
        (e.dataset.testid.startsWith('card__') || ['home','away'].includes(e.dataset.testid)&&e.querySelector('h3')))
        .map(e=>e.dataset.testid).filter(s=>/^[A-Za-z0-9_:-]{1,100}$/.test(s)))];
    for(const h of document.querySelectorAll('h3')) {
        if(!visible(h) || ids.some(id=>[...document.querySelectorAll('[data-testid]')].some(e=>e.dataset.testid===id&&e.contains(h)))) continue;
        ids.push('heading:'+(h.innerText||'').trim());
    }
    const header=(document.querySelector('[data-testid="match-header"]')?.innerText||'').slice(0,7000);
    return {ids:ids.slice(0,60),truncated:ids.length>60,header,
            page_substantial:(document.body.innerText||'').length>header.length+100};
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
    args = {"cardId": card_id, "groups": groups, "labels": list(labels), "action": "check"}
    transient = {"card_absent", "filter_absent_or_ambiguous", "filter_state_unconfirmed", "filter_disabled"}
    pending = None
    reason = "selection_failed"
    while time.monotonic() < deadline:
        # Cards attach before their controls hydrate. Re-resolve the source
        # groups while waiting, including after React replaces a card.
        state = await frame.evaluate(CARD_SCRIPT, args)
        if not state.get("ok"):
            reason = state.get("reason", "unavailable")
            if reason not in transient:
                return None, reason
        else:
            selected = state["selected"]
            settled = not network or (not network["pending"] and time.monotonic()-network["last_activity"] >= .3)
            if pending is not None:
                # Send each click once and wait for its aria acknowledgement.
                # Repeated clicks can cancel a delayed source transition.
                index, target = pending
                if selected[index] == target and not state.get("loading") and settled:
                    pending = None
                else:
                    reason = "selection_failed"
                    await asyncio.sleep(.1)
                    continue
            if selected == list(labels) and not state.get("loading") and settled:
                break
            if not state.get("loading") and settled:
                clicked = await frame.evaluate(CARD_SCRIPT, {**args, "action": "click"})
                if clicked.get("clicked"):
                    pending = (clicked["group_index"], clicked["target"])
                    reason = "selection_failed"
                elif not clicked.get("ok"):
                    reason = clicked.get("reason", "unavailable")
                    if reason not in transient:
                        return None, reason
        await asyncio.sleep(.1)
    else:
        return None, reason
    args["action"] = "read"
    # Equal figures in two different filters are legitimate. Confirm both the
    # selected source state and stable data, rather than requiring a change.
    await asyncio.sleep(min(.4, max(0, deadline-time.monotonic())))
    previous, stable = None, 0
    while time.monotonic() < deadline:
        state = await frame.evaluate(CARD_SCRIPT, args)
        if not state.get("ok"):
            reason = state.get("reason", "unavailable")
            if reason not in transient:
                return None, reason
            previous, stable = None, 0
            await asyncio.sleep(.1)
            continue
        signature = json.dumps([state.get("selected"), state.get("text"), state.get("rows")], ensure_ascii=False)
        settled = not network or (not network["pending"] and time.monotonic()-network["last_activity"] >= .3)
        stable = stable + 1 if previous == signature and not state.get("loading") and settled else 0
        previous = signature
        if stable >= 2:
            return {"observed_at": now(), "card_id": card_id, "selected": state["selected"],
                    "rows": state["rows"], "percentages": state["percentages"],
                    "tables": state["tables"], "title": state["title"],
                    "text": state["text"], "truncated": state["truncated"]}, None
        await asyncio.sleep(.2)
    return None, "stability_timeout"


async def collect_widget_views(page, statistics_id, *, budget_seconds=120, stopped=lambda: False):
    identifier = str(statistics_id)
    result = {"format":"doscbot.widget-views.v2", "statistics_id":identifier, "started_at":now(),
              "state":"partial", "snapshots":[], "missing":[], "groups":{}, "observed_views":[],
              "inventory":{}, "header":None, "provider_url":None, "truncated":False}
    result['bytes']=0
    deadline = time.monotonic()+budget_seconds
    frames = [f for f in page.frames if matches_frame(f,identifier)]
    if len(frames)!=1:
        result["missing"].append({"reason":"provider_frame_absent_or_ambiguous"})
        result["finished_at"] = now()
        return result
    frame = frames[0]
    network = {"pending":set(), "last_activity":time.monotonic()}
    def requested(request):
        try:
            if (request.resource_type in ("xhr","fetch") and urlsplit(request.url).hostname in DATA_HOSTS
                    and "/gismo/" in urlsplit(request.url).path and matches_frame(request.frame,identifier)):
                network["pending"].add(request)
                network["last_activity"] = time.monotonic()
        except Exception:
            pass
    def completed(request):
        if request in network["pending"]:
            network["pending"].discard(request)
            network["last_activity"] = time.monotonic()
    for event,handler in (("request",requested),("requestfinished",completed),("requestfailed",completed)):
        page.on(event,handler)
    recorded, work = set(), []
    def alive():
        return not stopped() and time.monotonic()<deadline
    async def visit(view):
        suffix = "" if view=="overview" else "/"+view
        target_path = f"/winline/ru/match/{identifier}{suffix}"
        if urlsplit(frame.url).path.rstrip("/")==target_path:
            return True
        links = frame.locator(f'a[href$="{target_path}"]')
        if not await links.count():
            return False
        target = urljoin(frame.url,await links.first.get_attribute("href"))
        parsed,current = urlsplit(target),urlsplit(frame.url)
        if parsed.scheme!="https" or parsed.netloc!=current.netloc or parsed.path!=target_path:
            result["missing"].append({"view":view,"reason":"view_link_mismatch"})
            return False
        response = await frame.goto(target,wait_until="domcontentloaded",timeout=max(1,min(15000,int((deadline-time.monotonic())*1000))))
        if response and response.status>=400:
            result["missing"].append({"view":view,"reason":f"http_{response.status}"})
            return False
        return matches_frame(frame,identifier)
    def save(snapshot,view):
        key=(view,snapshot["card_id"],tuple(snapshot["selected"]))
        if key in recorded:
            return
        recorded.add(key)
        if len(result["snapshots"])>=250:
            result["truncated"] = True
            return
        size=len(json.dumps(snapshot,ensure_ascii=False).encode())
        if result['bytes']+size>4*1024*1024:
            result['truncated']=True
            return
        result['bytes']+=size
        result["snapshots"].append({**snapshot,"view":view})
        group=result["groups"].get(view+":"+snapshot["card_id"])
        if group:
            group["captured"] += 1
        if snapshot.get("truncated"):
            result["missing"].append({"view":view,"card_id":snapshot["card_id"],"reason":"text_truncated"})
    async def inventory(view):
        previous, stable = None,0
        cards = []
        until=min(deadline,time.monotonic()+12)
        while alive() and time.monotonic()<until:
            info=await frame.evaluate(INVENTORY_SCRIPT)
            if not info['ids'] and info['page_substantial']:
                info['ids']=['page:'+view]
            cards=[]
            for card_id in info["ids"]:
                card=await frame.evaluate(CARD_SCRIPT,{"cardId":card_id})
                if card.get("ok") and card.get("text"):
                    cards.append((card_id,card))
            signature=json.dumps([(key,item["text"]) for key,item in cards],ensure_ascii=False)
            settled=not network["pending"] and time.monotonic()-network["last_activity"]>=.3
            stable=stable+1 if (cards or view=='report' and info['header']) and signature==previous and settled else 0
            previous=signature
            if stable>=2:
                break
            await asyncio.sleep(.25)
        if not cards:
            if view=='report':
                result.setdefault('empty_views',[]).append(view)
            else:
                result["missing"].append({"view":view,"reason":"no_visible_statistics_cards"})
            return
        result["observed_views"].append(view)
        result["provider_url"] = frame.url
        result["header"] = result["header"] or info["header"]
        result["truncated"] |= info["truncated"]
        result["inventory"][view] = [{"card_id":key,"title":item["title"],"groups":item["groups"]} for key,item in cards]
        for card_id,item in cards:
            groups=item["groups"]
            expected=prod(len(g) for g in groups) if groups else 1
            combinations=list(islice(product(*groups),48)) if groups else [()]
            key=view+":"+card_id
            result["groups"][key]={"expected":expected,"captured":0}
            save({"observed_at":now(),"card_id":card_id,"title":item["title"],"selected":item["selected"],
                  "text":item["text"],"rows":item["rows"],"tables":item["tables"],
                  "percentages":item["percentages"],"truncated":item["truncated"]},view)
            if expected>48:
                result["missing"].append({"view":view,"card_id":card_id,"reason":"variant_limit","available":expected})
            if groups:
                work.append((view,card_id,groups,combinations))
        if stable<2:
            result["missing"].append({"view":view,"reason":"default_view_not_stable"})
    try:
        # Every default view comes first. A large overview must not consume the
        # whole budget before report/statistics responses have been observed.
        for view in ("overview","report","statistics"):
            if not alive():
                break
            try:
                if await visit(view):
                    # Trigger ordinary viewport-lazy source widgets without
                    # inventing endpoints or clicking participant navigation.
                    await frame.evaluate("() => window.scrollTo(0, document.body.scrollHeight)")
                    await asyncio.sleep(.2)
                    await frame.evaluate("() => window.scrollTo(0, 0)")
                    await inventory(view)
            except Exception:
                result["missing"].append({"view":view,"reason":"view_unavailable"})
        for view,card_id,groups,combinations in work:
            if not alive():
                break
            try:
                if not await visit(view):
                    result["missing"].append({"view":view,"card_id":card_id,"reason":"view_link_absent"})
                    continue
                if not card_id.startswith(("heading:",'page:')):
                    await frame.get_by_test_id(card_id).first.wait_for(state="attached",timeout=max(1,min(5000,int((deadline-time.monotonic())*1000))))
                for labels in combinations:
                    if (view,card_id,labels) in recorded:
                        continue
                    if not alive():
                        break
                    snapshot,reason=await selected_snapshot(frame,card_id,groups,labels,min(deadline,time.monotonic()+6),network)
                    if not matches_frame(frame,identifier):
                        snapshot,reason=None,'provider_identity_changed'
                    if snapshot:
                        save(snapshot,view)
                    else:
                        result["missing"].append({"view":view,"card_id":card_id,"labels":list(labels),"reason":reason})
            except Exception:
                result["missing"].append({"view":view,"card_id":card_id,"reason":"card_unavailable"})
    finally:
        for event,handler in (("request",requested),("requestfinished",completed),("requestfailed",completed)):
            page.remove_listener(event,handler)
    incomplete=[{"card":key,**value} for key,value in result["groups"].items() if value["captured"]<value["expected"]]
    if incomplete:
        result["missing"].append({"reason":"stopped" if stopped() else "incomplete_filter_states","groups":incomplete})
    if not alive() and not result["missing"]:
        result["missing"].append({"reason":"stopped" if stopped() else "time_budget"})
    if result["snapshots"] and not result["missing"] and not result["truncated"]:
        result["state"]="captured"
    result["finished_at"]=now()
    return result
