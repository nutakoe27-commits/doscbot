"""Read-only evidence of current collection coverage by requested sport."""

from collections import Counter
from contextlib import closing
from datetime import timedelta
import json
from pathlib import Path
from urllib.parse import urlsplit

from .monitor_data import readonly,locked
from .profiles import DEFAULT_SPORTS, PROFILES, selection
from .store import instant, now


def audit(state, sports=DEFAULT_SPORTS, *, hours=24):
    path=Path(state).resolve()/'dataset.sqlite3'
    selected=selection(sports) or tuple(PROFILES)
    report={'format':'doscbot.collection-audit.v2','checked_at':now(),'database':str(path),
            'hours':hours,'sports':{},'ready_to_reset':False,'scope':'evidence of rich collection; not training/model validation'}
    for key in selected:
        report['sports'][key]={'label':PROFILES.get(key,{}).get('label',key),'market_documents':0,'statistics_states':{},'link_statuses':{},
                               'current_session':{'market_documents':0,'link_statuses':{}},'latest_attempt':None,
                               'captures':0,'qualified':0,'v2_samples':0,'reasons':{},'latest':None}
    if not path.exists():
        report['reason']='database_missing'
        return report
    cutoff=(instant(report['checked_at'])-timedelta(hours=hours)).isoformat()
    with closing(readonly(path)) as db:
        owner=db.execute("SELECT value FROM metadata WHERE key='owner'").fetchone()
        if not owner or owner[0]!='doscbot.fresh-collector.v1':
            raise ValueError('Audit requires the new collector database')
        runtime_row=db.execute("SELECT value FROM metadata WHERE key='runtime'").fetchone()
        runtime=json.loads(runtime_row[0]) if runtime_row else {}
        report['collector']={k:runtime.get(k) for k in ('started_at','heartbeat_at','phase','current_event','current_match','completed','total','next_run_at')}
        report['collector']['writer_locked']=locked(Path(state).resolve())
        session=runtime.get('started_at') or report['checked_at']
        run=db.execute('SELECT id,started_at,finished_at,state,details FROM runs ORDER BY id DESC LIMIT 1').fetchone()
        report['last_run']={k:run[k] for k in ('id','started_at','finished_at','state')} if run else None
        if run:
            report['last_run']['errors']=json.loads(run['details']).get('errors',[])
        for row in db.execute("SELECT json_extract(payload,'$.sport') sport,count(*) n FROM observations "
                              "WHERE kind='market_document' AND julianday(received_at)>=julianday(?) GROUP BY sport",(cutoff,)):
            if row['sport'] in report['sports']:
                report['sports'][row['sport']]['market_documents']=row['n']
        for row in db.execute("SELECT json_extract(payload,'$.sport') sport,json_extract(payload,'$.statistics_link_status') status,"
                              "count(*) n,sum(julianday(received_at)>=julianday(?)) session_n FROM observations "
                              "WHERE kind='market_document' AND julianday(received_at)>=julianday(?) GROUP BY sport,status",(session,cutoff)):
            if row['sport'] in report['sports']:
                item=report['sports'][row['sport']]
                status=row['status'] or 'unknown'
                item['link_statuses'][status]=row['n']
                item['current_session']['market_documents']+=row['session_n'] or 0
                if row['session_n']:
                    item['current_session']['link_statuses'][status]=row['session_n']
        seen=set()
        for row in db.execute("SELECT entity_id,observed_at,received_at,json_extract(payload,'$.sport') sport,"
                              "json_extract(payload,'$.home') home,json_extract(payload,'$.away') away,"
                              "json_extract(payload,'$.competition') competition,json_extract(payload,'$.statistics_link_status') link_status,"
                              "json_extract(payload,'$.statistics_url') statistics_url FROM observations "
                              "WHERE kind='market_document' AND julianday(received_at)>=julianday(?) ORDER BY id DESC",(cutoff,)):
            if row['sport'] not in report['sports'] or row['sport'] in seen:
                continue
            seen.add(row['sport'])
            item=report['sports'][row['sport']]
            item['latest_attempt']=attempt_state(db,dict(row),runtime,report['collector']['writer_locked'])
            if len(seen)==len(report['sports']):
                break
        attempted=set()
        for row in db.execute("SELECT entity_id,received_at,payload,json_extract(payload,'$.sport') sport FROM observations "
                              "WHERE kind='event_attempt' AND julianday(received_at)>=julianday(?) ORDER BY id DESC",(cutoff,)):
            if row['sport'] not in report['sports'] or row['sport'] in attempted:
                continue
            attempted.add(row['sport'])
            item=report['sports'][row['sport']]
            if item['latest_attempt'] and instant(item['latest_attempt']['started_at'])>=instant(row['received_at']):
                continue
            data=json.loads(row['payload'])
            market=db.execute("SELECT observed_at,received_at,json_extract(payload,'$.home') home,json_extract(payload,'$.away') away,"
                              "json_extract(payload,'$.competition') competition,json_extract(payload,'$.statistics_link_status') link_status,"
                              "json_extract(payload,'$.statistics_url') statistics_url FROM observations WHERE kind='market_document' "
                              "AND entity_id=? AND julianday(received_at)>=julianday(?) ORDER BY id DESC LIMIT 1",(row['entity_id'],row['received_at'])).fetchone()
            value=dict(market) if market else {k:data.get(k) for k in ('home','away','competition','link_status','statistics_url')}
            value.update(entity_id=row['entity_id'])
            value.setdefault('received_at',row['received_at']);value.setdefault('observed_at',row['received_at'])
            item['latest_attempt']=attempt_state(db,value,runtime,report['collector']['writer_locked'])
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
            views=value.get('views') or {}
            item['latest']['view_state']=views.get('state')
            item['latest']['view_failures']=dict(Counter(m.get('reason','unknown') for m in views.get('missing',[])))
            item['latest']['incomplete_filter_groups']=[{'card':card,**group} for card,group in views.get('groups',{}).items()
                                                       if group.get('captured',0)<group.get('expected',0)]
        for row in db.execute("SELECT json_extract(payload,'$.event.sport') sport,count(*) n FROM samples "
                              "WHERE json_extract(payload,'$.format')='doscbot.source-input.v2' AND julianday(as_of)>=julianday(?) GROUP BY sport",(cutoff,)):
            if row['sport'] in report['sports']:
                report['sports'][row['sport']]['v2_samples']=row['n']
    report['ready_to_reset']=all(item['qualified'] and item['v2_samples'] for item in report['sports'].values())
    return report


def attempt_state(db,market,runtime,writer):
    identifier=market['entity_id']
    output={k:market[k] for k in ('home','away','competition','link_status','statistics_url')}
    output.update(event_id=identifier,market_observed_at=market['observed_at'],state='unknown',reason=None)
    started=db.execute("SELECT payload,received_at FROM observations WHERE kind='event_attempt' AND entity_id=? ORDER BY id DESC LIMIT 1",(identifier,)).fetchone()
    since=started['received_at'] if started else market['received_at']
    output['started_at']=since
    if started:
        data=json.loads(started['payload'])
        output['native_radar_id']=data.get('native_radar_id')
    else:data={}
    parsed=urlsplit(data.get('url') or '')
    output['event_url']=f'https://{parsed.hostname}{parsed.path}' if parsed.scheme=='https' and parsed.hostname in ('winline.ru','www.winline.ru') and not parsed.username and not parsed.password else None
    diagnostic=db.execute("SELECT payload FROM observations WHERE kind='event_diagnostic' AND entity_id=? "
                          "AND julianday(received_at)>=julianday(?) ORDER BY id DESC LIMIT 1",(identifier,since)).fetchone()
    if diagnostic:
        result=json.loads(diagnostic['payload'])
        if not data.get('attempt_id') or result.get('attempt_id')==data['attempt_id']:
            output.update({k:v for k,v in result.items() if k not in ('url','attempt_id')})
            return output
    active=writer and str(runtime.get('current_event'))==identifier and runtime.get('phase') in ('markets','statistics','filters')
    if active:
        output.update(state='in_progress',stage=runtime['phase'],reason='collection_in_progress')
        return output
    if instant(market['received_at'])>=instant(since) and market['link_status'] in ('missing','ambiguous'):
        output.update(state='skipped',stage='statistics_link',reason='statistics_link_'+market['link_status'])
        return output
    for run in db.execute("SELECT details FROM runs WHERE julianday(finished_at)>=julianday(?) ORDER BY id DESC LIMIT 5",(since,)):
        details=json.loads(run[0])
        error=next((e for e in details.get('errors',[]) if str(e.get('event_id'))==identifier),None)
        if error:
            output.update(state='error',reason='recorded_collection_error',error_type=error.get('type'),stage=error.get('stage'),error_code=error.get('code'))
            return output
        outcome=details.get('sample_outcomes',{}).get(identifier)
        if outcome:
            output.update(state='input_saved' if outcome.get('reason')=='saved' else 'partial',reason=outcome.get('reason'))
            return output
    output.update(state='not_confirmed',reason='no_completed_attempt_diagnostic')
    return output
