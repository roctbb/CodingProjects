"""Combine the first snapshot with user-approved, previously unattributed work.

No database access. Existing definitions and complete source metadata remain
immutable. Existing teacher checkers are preserved; added contexts are archival.
"""
import hashlib
import json
import os
from pathlib import Path
import sys

def sha(data):return hashlib.sha256(data).hexdigest()
def rows(path):return [json.loads(s) for s in Path(path).open() if s.strip()]
def dump(path,value):Path(path).write_text(json.dumps(value,ensure_ascii=False,indent=2));Path(path).chmod(0o600)
def jsonl(path,items):Path(path).write_text(''.join(json.dumps(x,ensure_ascii=False)+'\n' for x in items));Path(path).chmod(0o600)
def fingerprint(row):return sha(json.dumps(row,sort_keys=True,separators=(',',':'),ensure_ascii=True).replace('/','\\/').encode())

def prepare(primary):
    primary=Path(primary);root=primary/'unattributed';target=root/'ready';target.mkdir(mode=0o700);(target/'blobs').mkdir(mode=0o700)
    for source in [primary/'ready',root/'export']:
        manifest=json.load(open(source/'manifest.json'))
        for name,digest in manifest['files'].items():
            if sha((source/name).read_bytes())!=digest:raise ValueError('Input manifest mismatch '+name)
        for p in (source/'blobs').iterdir():
            if not (target/'blobs'/p.name).exists():os.link(p,target/'blobs'/p.name)
    cp={x['id']:x for x in rows(root/'codingprojects/tasks.jsonl')};checkers={x['task_id']:x for x in rows(root/'codingprojects/checkers.jsonl')}
    tasks={str(x['id']):x for x in rows(primary/'ready/tasks.jsonl')}
    for x in rows(root/'export/tasks.jsonl'):tasks.setdefault(str(x['id']),x)
    solutions={x['id']:x for x in rows(primary/'ready/solutions.jsonl')};extra=rows(root/'export/solutions.jsonl');selected=json.load(open(root/'selection.json'))
    for x in extra:
        context=selected[x['id']]
        if x['id'] in solutions or any(x[k]!=context[k] for k in ('user_id','course_id','task_id')):raise ValueError('Ambiguous supplemental source '+x['id'])
        if x.get('legacy_original_context',{}).get('user_id','MISSING') is not None:raise ValueError('Unexpected original account '+x['id'])
        solutions[x['id']]=x
    mapping={str(k):v for k,v in json.load(open(primary/'map.json'))['tasks'].items()}
    plan=json.load(open(primary/'task-plan.json'))['tasks']
    for legacy,row in tasks.items():
        tid=mapping.get(legacy,int(legacy[3:]) if legacy.startswith('cp-') else int(legacy));mapping[legacy]=tid
        if tid not in cp:raise ValueError('Target task removed '+str(tid))
        p=plan.get(legacy) or dict(task_id=tid,source_definition_sha256=fingerprint(row),action='archive_only',private_instructions='')
        # These corrections have already been applied by the first migration.
        # The extra batch must not write them again or replace teacher settings.
        p.pop('set_public_text',None);p.pop('set_max_mark',None)
        p.update(target_public_text_sha256=sha((cp[tid]['text'] or '').encode()),target_points=cp[tid]['max_mark'])
        if p['action']=='archive_only' and tid in checkers:p.update(preserve_existing_checker=True,expected_checker_revision=checkers[tid]['revision'])
        plan[legacy]=p
    ids=set(solutions);edge_map={};deferred=[x for x in rows(primary/'ready/deferred-records.jsonl') if x['kind']!='similarity']
    for source in [primary/'export/similarities.jsonl',root/'export/similarities.jsonl']:
        for edge in rows(source):edge_map[(edge['code_id'],edge['code_id2'])]=edge
    edges=[]
    for edge in edge_map.values():
        if edge['code_id'] in ids and edge['code_id2'] in ids:edges.append(edge)
        else:deferred.append(dict(kind='similarity',reason='deferred_endpoint',record=edge))
    jsonl(target/'tasks.jsonl',list(tasks.values()));jsonl(target/'solutions.jsonl',[solutions[k] for k in sorted(solutions)]);jsonl(target/'similarities.jsonl',edges);jsonl(target/'deferred-records.jsonl',deferred)
    lineage={'primary':json.load(open(primary/'ready/source-manifest.json')),'unattributed':json.load(open(root/'export/manifest.json')),
             'policy':'User approved attachment to the unique existing CP submission; original NULL account retained; no regrading'}
    dump(target/'source-manifest.json',lineage);dump(target/'migration-plan.json',dict(tasks=plan,policy=lineage['policy']))
    os.link(primary/'ready/environments.tar.gz',target/'environments.tar.gz')
    names=['tasks.jsonl','solutions.jsonl','similarities.jsonl','deferred-records.jsonl','source-manifest.json','migration-plan.json','environments.tar.gz']
    manifest=dict(schema_version=2,source='GeekPasteV2',source_data_deleted=False,snapshot='combined-immutable-snapshots',
        exported_at=lineage['unattributed']['exported_at'],files={n:sha((target/n).read_bytes()) for n in names},
        counts=dict(tasks=len(tasks),solutions=len(solutions),similarities=len(edges),blobs=len(list((target/'blobs').iterdir())),missing_original_archives=sum(x['lang']=='zip' and not x.get('original_archive') for x in solutions.values())))
    dump(target/'manifest.json',manifest);dump(root/'task-plan.json',dict(schema_version=1,source_manifest_sha256=sha((target/'manifest.json').read_bytes()),tasks=plan));dump(root/'map.json',dict(tasks=mapping,courses={},users={}))
    summary=dict(counts=manifest['counts'],additional_solutions=len(extra),additional_contexts=len(tasks)-len(rows(primary/'ready/tasks.jsonl')),
        deferred={kind:sum(x['kind']==kind for x in deferred) for kind in ['task','solution','similarity']})
    dump(root/'selection-summary-final.json',summary);print(json.dumps(summary,ensure_ascii=False))
    for p in target.rglob('*'):
        if p.is_file():p.chmod(0o600)

if __name__=='__main__':prepare(sys.argv[1])
