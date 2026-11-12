"""A bounded legacy projection of independently captured rich widget views."""

from .store import instant, now


def from_views(original,document,widget):
    if original.get('state') in ('ok','blocked') or not widget or not widget.get('profile',{}).get('identity_verified'):
        return original
    views=widget.get('views') or {}
    attempted=now()
    sections=[]
    remaining=62000
    for section in views.get('snapshots',[]):
        if not section.get('text') or not section.get('observed_at') or (instant(attempted)-instant(section['observed_at'])).total_seconds()>290:
            continue
        content=section['text'][:min(7000,remaining)]
        if len(sections)>=20 or len(content)<5:
            break
        sections.append({'title':section.get('title') or section['card_id'],'text':content,
                         'view':section['view'],'observed_at':section['observed_at'],
                         'truncated':section.get('truncated',False) or len(content)<len(section['text'])})
        remaining-=len(content)
    if not sections or not views.get('provider_url') or not views.get('header'):
        return original
    return {'format':'matchlab.event-statistics.v1','source_event_id':document['source_event_id'],
            'event_url':document['url'],'statistics_url':document['statistics_url'],
            'state':'ok','attempted_at':attempted,'observed_at':sections[0]['observed_at'],
            'provider_url':views['provider_url'],'header':views['header'],
            'match_phase':'unknown','capture_kind':'detailed','sections':sections,
            'sections_truncated':True,'observed_views':list(dict.fromkeys(s['view'] for s in sections))}
