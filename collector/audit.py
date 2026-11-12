"""Read-only evidence of current collection coverage by requested sport."""

from collections import Counter
from contextlib import closing
from datetime import timedelta
import json
from pathlib import Path

from .monitor_data import readonly
from .profiles import DEFAULT_SPORTS, PROFILES, selection
from .store import instant, now


def audit(state, sports=DEFAULT_SPORTS, *, hours=24):
    path=Path(state).resolve()/'dataset.sqlite3'
    selected=selection(sports) or tuple(PROFILES)
    report={'format':'doscbot.collection-audit.v1','checked_at':now(),'database':str(path),
            'hours':hours,'sports':{},'ready_to_reset':False,'scope':'evidence of rich collection; not training/model validation'}
    for key in selected:
        report['sports'][key]={'label':PROFILES.get(key,{}).get('label',key),'market_documents':0,'statistics_states':{},
                               'captures':0,'qualified':0,'v2_samples':0,'reasons':{},'latest':None}
    if not path.exists():
        report['reason']='database_missing'
        return report
    cutoff=(instant(report['checked_at'])-timedelta(hours=hours)).isoformat()
    with closing(readonly(path)) as db:
        owner=db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
        if not owner or owner[0]!='doscbot.fresh-collector.v1':
            raise ValueError('Audit requires the new collector database')
        for row in db.execute("SELECT json_extract(payload,'$.sport') sport,count(*) n FROM observations "
                              "WHERE kind='market_document' AND julianday(received_at)>=julianday(?) GROUP BY sport",(cutoff,)):
            if row['sport'] in report['sports']:
                report['sports'][row['sport']]['market_documents']=row['n']
        for row in db.execute("SELECT (SELECT json_extract(m.payload,'$.sport') FROM observations m WHERE "
                              "m.kind='market_document' AND m.entity_id=o.entity_id ORDER BY m.id DESC LIMIT 1) sport,"
                              "json_extract(o.payload,'$.state') state,count(*) n FROM observations o "
                              "WHERE o.kind='team_statistics' AND julianday(o.received_at)>=julianday(?) GROUP BY sport,state",(cutoff,)):
            if row['sport'] in report['sports']:
                report['sports'][row['sport']]['statistics_states'][row['state']]=row['n']
        for row in db.execute("SELECT entity_id,received_at,payload FROM observations WHERE kind='widget_data' "
                              "AND julianday(received_at)>=julianday(?) ORDER BY id",(cutoff,)):
            value=json.loads(row['payload'])
            sport=value.get('sport')
            if sport not in report['sports']:
                continue
            item=report['sports'][sport]
            item['captures']+=1
            reasons=list(value.get('coverage',{}).get('reasons',[]))
            if not value.get('profile',{}).get('identity_verified'):
                reasons.append('sport_identity_unconfirmed')
            if value.get('capture',{}).get('state')!='captured':
                reasons.append('raw_capture_incomplete')
            if not value.get('coverage',{}).get('usable_rich_capture'):
                reasons.append('rich_capture_not_verified')
            if (value.get('views') or {}).get('format')!='doscbot.widget-views.v2':
                reasons.append('old_fixed_capture_format')
            if sport=='football' and not value.get('coverage',{}).get('season_participants'):
                reasons.append('representative_season_statistics_missing')
            qualified=not set(reasons)-{'numeric_projection_truncated_raw_preserved'}
            item['qualified']+=int(qualified)
            item['reasons']=dict(Counter(item['reasons'])+Counter(set(reasons)))
            item['latest']={'event_id':row['entity_id'],'at':row['received_at'],
                            'statistics_id':value.get('statistics_id'),'qualified':qualified,
                            'sections':value.get('coverage',{}).get('sections',0),
                            'documents':value.get('coverage',{}).get('documents',0)}
        for row in db.execute("SELECT json_extract(payload,'$.event.sport') sport,count(*) n FROM samples "
                              "WHERE json_extract(payload,'$.format')='doscbot.source-input.v2' AND julianday(as_of)>=julianday(?) GROUP BY sport",(cutoff,)):
            if row['sport'] in report['sports']:
                report['sports'][row['sport']]['v2_samples']=row['n']
    report['ready_to_reset']=all(item['qualified'] and item['v2_samples'] for item in report['sports'].values())
    return report
