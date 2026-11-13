"""Typed projections of observed StatsHub documents, with source provenance.

Keep the complete JSON separately. These are observations, not a training
matrix: schedules, current-match data and provider aggregates need an as-of
join before they can be used for training.
"""

from collections import Counter
from datetime import datetime, timezone
import json
from pathlib import Path
import re
from .profiles import PROFILES, provider_sport


def team(value):
    if not isinstance(value, dict):
        return {}
    # A seasonal team record and its cross-season unique team have different IDs.
    return {"id": str(value.get("uid") or value.get("_id") or ""),
            "record_id": str(value.get("_id") or ""), "name": value.get("name")}


def timestamp(value):
    if not isinstance(value, dict) or not isinstance(value.get("uts"), (int, float)):
        return None
    try:
        return datetime.fromtimestamp(value["uts"], timezone.utc).isoformat()
    except (ValueError, OverflowError, OSError):
        return None


def match(value):
    sides = value.get("teams") or {}
    return {"statistics_id": str(value.get("_id") or ""),
            "kickoff_at": timestamp(value.get("_dt") or value.get("time")),
            "season_id": str(value.get("_seasonid") or ""),
            "tournament_id": str(value.get("_tid") or ""),
            "home": team(sides.get("home")), "away": team(sides.get("away")),
            "status": value.get("status"), "result": value.get("result"),
            "periods": value.get("periods"), "postponed": value.get("postponed"),
            "best_of": value.get("bestof"), "retired": value.get("retired"),
            "number_of_periods": value.get("numberofperiods"),
            "walkover": value.get("walkover"), "disqualified": value.get("disqualified"),
            "cancelled": value.get("cancelled", value.get("canceled"))}


def numeric_facts(value, *, limit=1500):
    """Preserve observed field paths; don't reinterpret points as goals/sets."""
    output=[]
    limited=False
    def visit(item,path):
        nonlocal limited
        if len(output)>=limit:
            limited=True
            return
        if isinstance(item,dict):
            for key,child in item.items():
                if str(key).startswith('_') or str(key).casefold() in ('uts','timestamp','id','uid','tzoffset'):
                    continue
                visit(child,path+[str(key)])
        elif isinstance(item,list):
            for index,child in enumerate(item):
                visit(child,path+[str(index)])
        elif isinstance(item,(int,float)) and not isinstance(item,bool):
            output.append({'path':'.'.join(path),'value':item})
    visit(value,[])
    return output,limited


def scalar_fields(value,limit=500):
    """Categorical context matters too: surface, venue, formation, names."""
    output=[]
    limited=False
    def visit(item,path):
        nonlocal limited
        if len(output)>=limit:
            limited=True
            return
        if isinstance(item,dict):
            for key,child in item.items():
                visit(child,path+[str(key)])
        elif isinstance(item,list):
            for index,child in enumerate(item):
                visit(child,path+[str(index)])
        else:
            if isinstance(item,str) and len(item)>500:
                item=item[:500]
                limited=True
            output.append({'path':'.'.join(path),'value':item})
    visit(value,[])
    return output,limited


def source_entries(records):
    for index, record in enumerate(records):
        body = record.get("data")
        endpoint = record.get("endpoint") or {}
        path = endpoint.get("path", "")
        route = path.split("/gismo/", 1)[-1].strip("/").split("/")
        if "/gismo/" not in path or not isinstance(body, dict):
            continue
        docs = body.get("doc")
        if not isinstance(docs, list):
            continue
        for doc in docs:
            if not isinstance(doc, dict):
                continue
            yield route, doc.get("data"), {
                "record_index": index, "observed_at": record.get("observed_at"),
                "endpoint": endpoint, "event": doc.get("event"),
                "provider_generated_uts": doc.get("_dob"), "provider_max_age": doc.get("_maxage")}


SEASON_METRICS = {
    "matches": ("totalmatches",), "wins": ("totalwins",),
    "goals_for": ("scoring", "goalsscored"),
    "goals_against": ("conceding", "goalsconceded"),
    "both_teams_scored": ("scoring", "bothteamsscored"),
    "failed_to_score": ("scoring", "failedtoscore"),
    "clean_sheets": ("conceding", "cleansheets"),
    "goals_for_per_match": ("scoring", "goalsscoredaverage"),
    "goals_against_per_match": ("conceding", "goalsconcededaverage"),
    "both_teams_scored_per_match": ("scoring", "bothteamsscoredaverage"),
    "failed_to_score_per_match": ("scoring", "failedtoscoreaverage"),
    "clean_sheets_per_match": ("conceding", "cleansheetsaverage"),
}


def dig(value, path):
    for key in path:
        if not isinstance(value, dict):
            return None
        value = value.get(key)
    return value


def standings(table, period, source, sport=None):
    output = {"season_id": str(table.get("seasonid") or ""),
              "table_id": str(table.get("_id") or ""), "name": table.get("name"),
              "group": table.get("groupname"), "tournament": table.get("tournament"),
              "period_id": period, "source": source,
              "expected_rows": table.get("totalrows"), "rows": [],'source_columns':table.get('header')}
    for row in table.get("tablerows") or []:
        scopes = {}
        for scope, suffix in (("total", "Total"), ("home", "Home"), ("away", "Away")):
            keys = {"played": scope, "position": "pos" if scope == "total" else "pos" + suffix,
                    "wins": "win" + suffix, "draws": "draw" + suffix,
                    "losses": "loss" + suffix, "goals_for": "goalsFor" + suffix,
                    "goals_against": "goalsAgainst" + suffix, "goal_difference": "goalDiff" + suffix,
                    "points": "points" + suffix}
            scopes[scope] = {key: row.get(field) for key, field in keys.items()}
            if sport not in ('football','hockey',None):
                scopes[scope]['score_for']=scopes[scope].pop('goals_for')
                scopes[scope]['score_against']=scopes[scope].pop('goals_against')
        output["rows"].append({"team": team(row.get("team")), "scopes": scopes})
    output["rows_complete"] = isinstance(output["expected_rows"], int) and len(output["rows"]) == output["expected_rows"]
    return output


def normalize_widget(records, statistics_id, *, event_id=None, binding="statistics_page_only", views=None, expected_sport=None):
    identifier = str(statistics_id)
    if not re.fullmatch(r"\d{1,20}", identifier):
        raise ValueError("A numeric statistics ID is required")
    output = {"format": "doscbot.widget-data.v1", "statistics_id": identifier,
              "event_id": event_id, "binding": binding, "match": None,
              "season_statistics": [], "standings": [], "form": [], "recent_matches": [],
              "head_to_head": [], "document_counts": {}, "ignored": [], "documents": [],
              "scope": "observed source data; includes schedules and current-match data; not training features"}
    counts = Counter()
    recent = {}
    detected, capabilities = None, {}
    fact_budget=8000
    field_budget=4000
    sport_context=expected_sport
    if not sport_context:
        for route,data,_ in source_entries(records):
            if route[0] not in ('match_info_statshub','stats_match_get') or not isinstance(data,dict):
                continue
            target=data.get('match',data)
            if str(target.get('_id'))==identifier:
                sport_context=provider_sport(target,(data.get('sport') or {}).get('name')) or sport_context
    for route, data, source in source_entries(records):
        name, *args = route
        counts[name] += 1
        facts,limited=numeric_facts(data,limit=min(1500,fact_budget)) if any(word in name for word in ('stats','squad','lineup','injur','timeline')) else ([],False)
        fact_budget-=len(facts)
        fields,fields_limited=scalar_fields(data,limit=min(500,field_budget))
        field_budget-=len(fields)
        # Document values remain in widget_json; retain a bounded searchable
        # projection for any source schema, alongside the typed football data.
        output['documents'].append({'name':name,'source':source,'numeric':facts,'truncated':limited,
                                    'fields':fields,'fields_truncated':fields_limited,
                                    'keys':list(data)[:100] if isinstance(data,dict) else [],
                                    'items':len(data) if isinstance(data,list) else None})
        if not isinstance(data, dict):
            continue
        if name in ("match_info_statshub", "stats_match_get"):
            item = data.get("match") if name == "match_info_statshub" else data
            if not isinstance(item, dict) or str(item.get("_id")) != identifier:
                output["ignored"].append({"source": source, "reason": "match_id_mismatch"})
                continue
            # Prefer the detailed match_info status over the generic null status.
            if output["match"] is None or name == "match_info_statshub":
                output["match"] = {**match(item), "season": data.get("season"), "source": source}
                detected=provider_sport(item,(data.get('sport') or {}).get('name'))
                output['provider_sport_id']=item.get('_sid')
                capabilities=data.get('statscoverage') or {}
        elif name == "stats_season_teamscoringconceding" and len(args) == 3:
            if sport_context not in ('football','hockey'):
                continue
            if team(data.get("team")).get("id") != args[1]:
                output["ignored"].append({"source": source, "reason": "team_id_mismatch"})
                continue
            scopes = {scope: {metric: dig(data.get("stats"), path + (scope,))
                              for metric, path in SEASON_METRICS.items()} for scope in ("total", "home", "away")}
            output["season_statistics"].append({"season_id": args[0], "team": team(data.get("team")),
                "window": {"source_value": args[2], "kind": "season" if args[2] == "-1" else "last_matches"},
                "scopes": scopes, "source": source})
        elif name in ("stats_season_tables", "stats_match_tableslice"):
            if name == "stats_match_tableslice":
                # This is a participant slice, not the complete league table.
                if str(data.get("matchid")) != identifier:
                    continue
                tables, period = [data], None
            else:
                tables = data.get("tables") or []
                period = args[1] if len(args) > 1 and args[1] else None
            output["standings"].extend(standings(t, period, source,sport_context) for t in tables if isinstance(t, dict))
        elif name == "stats_formtable":
            for item in data.get("teams") or []:
                output["form"].append({"season_id": str((data.get("season") or {}).get("_id") or ""),
                    "team": team(item.get("team")), "form": item.get("form"),
                    "form_table_position": item.get("position"), "source": source})
        elif name in ("stats_team_lastx","stats_player_lastx"):
            for item in data.get("matches") or []:
                item = match(item)
                # Include the team context, but don't duplicate a match by view.
                participant=team(data.get('team') or data.get('player'))
                key = (participant.get("id"), item["statistics_id"])
                recent[key] = {**item, "participant": participant, "source": source,
                               "is_target": item["statistics_id"] == identifier}
        elif name == "stats_h2h_versus":
            output["head_to_head"].append({"source": source, "data": data})
    output["recent_matches"] = list(recent.values())
    output["document_counts"] = dict(sorted(counts.items()))
    output["views"] = views or {"state": "not_collected", "snapshots": []}
    sport=expected_sport or detected
    output['sport']=sport
    output['profile']={**PROFILES.get(sport,{'label':sport or 'Не определён','score_units':[]}),
                       'key':sport,'provider_sport':detected,'identity_verified':bool(detected and detected==sport),
                       'scope':'collection profile; shared models must retain sport-specific feature and target semantics'}
    output['capabilities']=capabilities
    participants = {side.get("id") for side in ((output["match"] or {}).get("home", {}),
                    (output["match"] or {}).get("away", {})) if side.get("id")}
    season_rows = [x for x in output["season_statistics"] if x["window"]["kind"] == "season"]
    season_ids = {x["team"].get("id") for x in season_rows}
    missing_metrics = []
    for participant in sorted(participants):
        rows = [x for x in season_rows if x["team"].get("id") == participant]
        for scope in ("total", "home", "away"):
            for key in ("wins", "goals_for", "goals_against", "both_teams_scored", "failed_to_score", "clean_sheets"):
                if not any(x["scopes"][scope].get(key) is not None for x in rows):
                    missing_metrics.append({"team_id": participant, "scope": scope, "metric": key})
    teams_by_period = {p: {r["team"].get("id") for t in output["standings"]
                          if t["period_id"] == p and t["rows_complete"] for r in t["rows"]}
                       for p in ("1", "2", "3")}
    table_teams = teams_by_period["1"]
    periods = sorted({x["period_id"] for x in output["standings"] if x["period_id"] is not None})
    output["coverage"] = {"matched_target": output["match"] is not None,
        "scope": "season_statistics_6_states, standings_3_periods_4_scopes, form_6_states",
        "season_participants": sorted(participants & season_ids),
        "missing_season_participants": sorted(participants - season_ids),
        "missing_season_metrics": missing_metrics,
        "full_time_table_participants": sorted(participants & table_teams),
        "table_period_ids": periods,
        "missing_table_period_ids": [p for p in ("1", "2", "3") if p not in periods],
        "missing_table_participants": {p: sorted(participants - ids) for p, ids in teams_by_period.items()},
        "filter_state": output["views"].get("state", "not_collected"),
        "complete": False}
    # Existence of JSON doesn't establish all source blocks or filter variants.
    output["coverage"]["complete"] = bool(participants and len(participants) == 2
        and participants <= season_ids and participants <= table_teams
        and not missing_metrics
        and not output["coverage"]["missing_table_period_ids"]
        and not any(output["coverage"]["missing_table_participants"].values())
        and output["views"].get("state") == "captured")
    if sport!='football':
        output['coverage']={'matched_target':output['match'] is not None,
            'scope':'source-discovered sport-specific cards and filters; unknown metrics keep source field paths',
            'filter_state':output['views'].get('state','not_collected'),
            'table_period_ids':periods, 'complete':False}
    snapshots=output['views'].get('snapshots',[])
    non_history=[s for s in snapshots if not re.search(r'последние матчи|последние встречи|last matches|head.to.head',s.get('title',''),re.I)
                 and (s.get('rows') or s.get('tables') or s.get('percentages'))]
    rich_docs=[d for d in output['documents'] if d['numeric'] and re.search(r'scoringconceding|season_tables|formtable|overunder|squad|lineup|stats_match_stats|player_stats|team_stats',d['name'])]
    reasons=[]
    if output['match'] is None: reasons.append('target_identity_missing')
    if not output['profile']['identity_verified']: reasons.append('sport_identity_unconfirmed')
    if not non_history and not rich_docs: reasons.append('only_history_or_no_rich_statistics')
    if output['views'].get('state')!='captured': reasons.append('view_capture_incomplete')
    if any(d['truncated'] for d in output['documents']): reasons.append('numeric_projection_truncated_raw_preserved')
    output['coverage']['sport_identity_verified']=output['profile']['identity_verified']
    output['coverage']['scope']='source-discovered sport-specific cards and filters; core football season/table checks when applicable'
    output['coverage']['sections']=len(snapshots)
    output['coverage']['rich_sections']=len(non_history)
    output['coverage']['documents']=len(output['documents'])
    output['coverage']['reasons']=reasons
    output['coverage']['usable_rich_capture']=not [r for r in reasons if r!='numeric_projection_truncated_raw_preserved']
    if sport!='football':
        output['coverage']['complete']=not reasons
    else:
        output['coverage']['complete']=output['coverage']['complete'] and not reasons
    return output


def inspect_probe(path):
    path = Path(path)
    if path.stat().st_size > 32 * 1024 * 1024:
        raise ValueError("Probe exceeds 32 MiB")
    value = json.loads(path.read_text(encoding="utf-8"))
    if value.get("format") != "doscbot.browser-widget-probe.v1":
        raise ValueError("Unsupported browser probe format")
    # An uploaded report is diagnostic evidence, never an automatically trusted
    # event link or a historical database import.
    output = normalize_widget(value.get("requests") or [], value.get("statistics_id"),
                              binding="uploaded_statistics_probe")
    output["probe"] = {"started_at": value.get("started_at"), "finished_at": value.get("finished_at"),
                       "initial_requests_observed": value.get("initial_requests_observed"),
                       "truncated": value.get("truncated"), "skipped": value.get("skipped"),
                       "views_observed": sorted({s.get("path", "") for s in value.get("snapshots", [])}),
                       "requests": len(value.get("requests") or [])}
    return output
