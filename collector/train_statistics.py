"""New CPU fit without price features. Two views share the same probability fit."""
from collections import Counter
from importlib.metadata import version
import hashlib
import json
from pathlib import Path
import shutil

from .store import digest,encoded,now,instant
from .train_baseline import read_dataset,evaluate
from .train_all import file_sha256
from . import statistics_contract as contract


def train_statistics(dataset,finding,output,*,threads=2,iterations=200,depth=4,max_rows=200000):
    if any(type(v) is not int for v in (threads,iterations,depth,max_rows)) or not 1<=threads<=16 or not 10<=iterations<=2000 or not 2<=depth<=8 or not 10<=max_rows<=2000000:
        raise ValueError('Invalid CPU training bounds')
    dataset,finding,output=(Path(p).resolve() for p in (dataset,finding,output))
    if output.exists() or any(p==finding or finding in p.parents or p in finding.parents for p in (dataset,output)) or output in dataset.parents or dataset in output.parents:
        raise ValueError('Use a new model directory outside finding and the dataset')
    manifest,old_names,folds=read_dataset(dataset,max_rows,all_available=True)
    def values(row):
        return {k:(None if type(v) is float and v!=v else v) for k,v in zip(old_names,row['x'])}
    rows=[];features=[];excluded=Counter()
    for row in folds['all']:
        projected=contract.project(values(row))
        if not contract.supported(projected):excluded[row['sport']]+=1;continue
        rows.append(row);features.append(projected)
    if not rows or {r['y'] for r in rows}!={0,1}:raise ValueError('No labelled two-sided statistics with both classes')
    counts=Counter(r['sport'] for r in rows)
    if any({r['y'] for r in rows if r['sport']==s}!={0,1} for s in counts):raise ValueError('Each trained sport needs both classes')
    groups={r['group']:r for r in rows}
    if any(n!=2 for n in Counter(r['group'] for r in rows).values()):raise ValueError('Incomplete participant pair')
    from catboost import CatBoostClassifier,Pool
    if version('catboost')!='1.2.10':raise ValueError('CatBoost 1.2.10 required')
    weights={s:len(rows)/(len(counts)*n) for s,n in counts.items()}
    pool=Pool([contract.vector(f) for f in features],label=[r['y'] for r in rows],feature_names=contract.NAMES,
              cat_features=contract.CATEGORICAL,weight=[weights[r['sport']] for r in rows])
    started=now()
    model=CatBoostClassifier(iterations=iterations,depth=depth,learning_rate=.05,random_seed=42,loss_function='Logloss',thread_count=threads,task_type='CPU',allow_writing_files=False,verbose=False)
    model.fit(pool)
    probabilities=model.predict_proba(pool)[:,1].tolist()
    report={'format':'doscbot.statistics-model.v1','stage':'experimental_all_data','target':'participant_win.v1',
        'feature_profile':contract.PROFILE,'started_at':started,'finished_at':now(),'packages':{'catboost':version('catboost')},
        'parameters':{'iterations':iterations,'depth':depth,'learning_rate':.05,'seed':42,'threads':threads,'device':'CPU','initialization':'from_scratch'},
        'tree_count':model.tree_count_,'dataset_directory':str(dataset),'dataset_rows_sha256':manifest['rows_sha256'],
        'schema_sha256':digest(contract.SCHEMA),'preparation_code_sha256':manifest['preparation_code_sha256'],
        'statistics_contract_sha256':file_sha256(Path(contract.__file__)),
        'trainer_code_sha256':file_sha256(Path(__file__)),'training_events':len(groups),'training_rows':len(rows),
        'excluded_insufficient_statistics_events':{s:n//2 for s,n in excluded.items()},
        'unsupported_sports':sorted(set(manifest['requested_sports'])-set(counts)),'sports':dict(counts),'sport_weights':weights,
        'evaluation':{'training':evaluate(rows,probabilities)},'holdout_evaluation':None,'needs_future_evaluation':True,
        'validated_for_betting':False,'decision_policy':contract.DEFAULT_POLICY,
        'warnings':['Training fit is not future accuracy. Evaluate new matches only.',
                    'No prices or market-derived features enter CatBoost. Prices are used after prediction for the frozen paper policy.',
                    'Normal-period labels only. Both participants require verified history/season statistics.',
                    'Policy thresholds are fixed experimental defaults, not calibrated profitability guarantees.']}
    output.mkdir(parents=True,exist_ok=False)
    try:
        model.save_model(str(output/'model.cbm'));report['model_sha256']=file_sha256(output/'model.cbm')
        (output/'feature-schema.json').write_text(json.dumps(contract.SCHEMA,ensure_ascii=False,indent=2)+'\n')
        (output/'training-events.jsonl').write_text(''.join(encoded({'event_id':key,**{k:r[k] for k in ('sample_sha256','as_of','kickoff_at','label_received_at')}})+'\n' for key,r in sorted(groups.items())))
        (output/'feature-importance.tsv').write_text('feature\timportance\n'+''.join(k+'\t'+str(float(v))+'\n' for k,v in zip(contract.NAMES,model.get_feature_importance())))
        report['finished_at']=now();(output/'metrics.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    except BaseException:shutil.rmtree(output);raise
    return report


def package_statistics(model_dir,output):
    """Portable verified input + model; never copies credentials or collector databases."""
    model_dir,output=Path(model_dir).resolve(),Path(output).resolve()
    report=json.loads((model_dir/'metrics.json').read_text())
    dataset=Path(report['dataset_directory']).resolve()
    if output.exists() or any(output==p or p in output.parents or output in p.parents for p in (dataset,model_dir)):
        raise ValueError('Use a new bundle outside model and dataset')
    manifest,_,_=read_dataset(dataset,2000000,all_available=True)
    if (report.get('feature_profile')!=contract.PROFILE or report['model_sha256']!=file_sha256(model_dir/'model.cbm') or
        report['schema_sha256']!=digest(contract.SCHEMA) or json.loads((model_dir/'feature-schema.json').read_text())!=contract.SCHEMA or
        report['statistics_contract_sha256']!=file_sha256(Path(contract.__file__)) or report['dataset_rows_sha256']!=manifest['rows_sha256']):
        raise ValueError('Statistics bundle identity mismatch')
    output.mkdir(parents=True,exist_ok=False)
    try:
        for sub,source,names in [('model',model_dir,('model.cbm','metrics.json','feature-schema.json','training-events.jsonl','feature-importance.tsv')),
                                 ('dataset',dataset,('manifest.json','schema.json','rows.jsonl'))]:
            (output/sub).mkdir()
            for name in names:
                if (source/name).is_symlink():raise ValueError('Redirected bundle input')
                shutil.copyfile(source/name,output/sub/name)
        checks={str(p.relative_to(output)):file_sha256(p) for p in sorted(output.rglob('*')) if p.is_file()}
        (output/'SHA256SUMS').write_text(''.join(sha+'  '+name+'\n' for name,sha in checks.items()))
    except BaseException:shutil.rmtree(output);raise
    return {'state':'statistics_bundle_ready','bundle':str(output),'model_sha256':report['model_sha256'],'training_events':report['training_events']}
