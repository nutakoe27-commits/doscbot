"""Browse all captured public sections, preserving sport-specific labels."""

from collections import OrderedDict
import json
import unicodedata

from rich.console import Group
from rich.table import Table
from rich.text import Text
from rich.segment import Segment


def public(value,limit=150000):
    value=str(value or '')[:limit]
    return Text(''.join(c for c in value if c in '\n\t' or not unicodedata.category(c).startswith('C')))


def section_blocks(detail):
    widget=detail.get('widget_data') or {}
    groups=OrderedDict()
    snapshots=(widget.get('views') or {}).get('snapshots',[])
    for snapshot in snapshots:
        key=(snapshot.get('view'),snapshot.get('card_id'))
        group=groups.setdefault(key,{'title':snapshot.get('title') or snapshot.get('card_id'),'parts':[]})
        labels=' / '.join(snapshot.get('selected') or [])
        group['parts'].append(Text(labels or 'Основной вид',style='bold cyan'))
        group['parts'].append(public(snapshot.get('text')))
        group['parts'].append(Text(''))
    if not snapshots:
        for snapshot in (detail.get('team_statistics') or {}).get('sections',[]):
            key=(snapshot.get('view'),snapshot.get('title'))
            group=groups.setdefault(key,{'title':snapshot.get('title'),'parts':[]})
            group['parts'].append(public(snapshot.get('text')))
    blocks=[(f"{item['title']} · {view or 'источник'}",Group(*item['parts'])) for (view,_),item in groups.items()]
    history=[]
    for row in widget.get('recent_matches',[]):
        result=row.get('result') or {}
        score=f"{result.get('home','—')}:{result.get('away','—')}"
        history.append(f"{row.get('kickoff_at','—')}   {(row.get('home') or {}).get('name','—')} — {(row.get('away') or {}).get('name','—')}   {score}")
    if history:
        blocks.append(('История участников · JSON',public('\n'.join(history))))
    # Keep different periods/groups as distinct tables. Never assign a goal
    # or a football half to a tennis/volleyball source value.
    unique={}
    for row in widget.get('standings',[]):
        unique[(row.get('table_id'),row.get('period_id'))]=row
    for row in unique.values():
        grid=Table('Команда','И','В','Н','П','За','Против','Место','Очки',expand=True)
        for item in row.get('rows',[]):
            values=item['scopes']['total']
            values={**values,'goals_for':values.get('goals_for',values.get('score_for')),
                    'goals_against':values.get('goals_against',values.get('score_against'))}
            grid.add_row(public(item['team'].get('name')),*(public(str(values.get(k)) if values.get(k) is not None else '—')
                for k in ('played','wins','draws','losses','goals_for','goals_against','position','points')))
        blocks.append((f"{row.get('name','Таблица')} · период источника {row.get('period_id') or 'по умолчанию'}",grid))
    docs=OrderedDict()
    for document in widget.get('documents',[]):
        key=document.get('name')
        if document.get('numeric') or document.get('fields'):
            docs.setdefault(key,[]).append(document)
    for name,documents in docs.items():
        parts=[]
        for document in documents:
            parts.append(public((document.get('source',{}).get('endpoint') or {}).get('path')))
            grid=Table('Поле источника','Значение',expand=True)
            for row in document.get('fields') or document.get('numeric',[]):
                grid.add_row(public(row['path']),public(json.dumps(row['value'],ensure_ascii=False)))
            parts.append(grid)
            if document.get('truncated') or document.get('fields_truncated'):
                parts.append(Text('Числовая проекция ограничена; полный документ сохранён отдельно.',style='yellow'))
        blocks.append((f"Поля API · {name}",Group(*parts)))
    markets=(detail.get('market_document') or {}).get('markets',[])
    if markets:
        grid=Table('Рынок','Исход','Коэффициент',expand=True)
        for market in markets:
            for outcome in market.get('outcomes',[]):
                grid.add_row(public(market.get('name')),public(outcome.get('name')),public(str(outcome.get('odds','—'))))
        blocks.append(('Сохранённые рынки',grid))
    return blocks


class Scrolled:
    def __init__(self,content,offset,height):
        self.content,self.offset,self.height=content,offset,height

    def __rich_console__(self,console,options):
        lines=console.render_lines(self.content,options.update(height=None),pad=False)
        start=min(self.offset,max(0,len(lines)-self.height))
        for line in lines[start:start+self.height]:
            yield from line
            yield Segment.line()
