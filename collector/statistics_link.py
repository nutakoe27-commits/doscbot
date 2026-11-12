"""Wait for an observed event-scoped statistics link/control to hydrate."""

import asyncio
import time

SCRIPT=r'''() => {
    const roots=[...document.querySelectorAll('.ww-markets,.ww-markets__stickybar,.marketbook-header--prematch,.marketbook-header--live,.special__btns-wrapper')];
    const nodes=[...new Set(roots.flatMap(root=>[...root.querySelectorAll('a,button,[role="button"]')]))];
    const visible=nodes.filter(e=>e.getClientRects().length && !e.closest('form'));
    const links=[...new Set(visible.filter(e=>e.tagName==='A' && e.href.includes('/stats/match/')).map(e=>e.href))];
    const controls=visible.filter(e=>{
        if(e.matches('.market__row-btn,.market-header')) return false;
        const marker=[e.className,e.title,e.getAttribute('aria-label'),e.getAttribute('data-testid'),(e.innerText||'').slice(0,120),
            ...[...e.querySelectorAll('svg,use,img')].map(i=>[i.getAttribute('class'),i.getAttribute('alt'),i.getAttribute('href'),i.getAttribute('xlink:href')].join(' '))].join(' ');
        return /statistic|статист|stats(?:[._\-#]|$)/i.test(marker);
    }).length;
    return {links,controls};
}'''


async def resolve_statistics_link(page,snapshot,event_id,*,wait_seconds=6):
    from matchlab.winline_browser import event_id_from_url,event_statistics_link
    from matchlab.winline_markets import discover_statistics_link
    started=time.monotonic()
    links=list(snapshot.get('statistics_links') or [])
    _,status=event_statistics_link(links)
    info={'method':'snapshot','controls':0,'status':status,'waited_ms':0}
    if status!='missing':
        return links,info
    deadline=started+wait_seconds
    while True:
        if event_id_from_url(page.url)!=str(event_id):
            info.update(status='event_navigation_changed',method='refused')
            return [],info
        observed=await page.evaluate(SCRIPT)
        _,status=event_statistics_link(observed['links'])
        info['controls']=observed['controls']
        if status!='missing':
            links=observed['links'];info['method']='observed_dom'
            break
        if observed['controls'] or time.monotonic()>=deadline:
            # Keep the pinned explicit-control/popup resolver; never derive a
            # statistics URL from a bookmaker radar ID or an event number.
            links=await discover_statistics_link(page,snapshot)
            info['method']='observed_control' if observed['controls'] else 'no_observed_link_or_control'
            break
        await asyncio.sleep(.25)
    _,info['status']=event_statistics_link(links)
    info['waited_ms']=round((time.monotonic()-started)*1000)
    return links,info
