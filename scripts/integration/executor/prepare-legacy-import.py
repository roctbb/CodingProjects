"""Prepare a resumable subset of a private, immutable export. No DB access/writes.

Usage: python prepare-legacy-import.py RUN BASELINE CATALOGUE BUNDLE
Only copied metadata and same-owner supplemental sources are used. Every skipped
record is retained in a checksummed sidecar and in the original export.
"""
import hashlib
import json
import os
from pathlib import Path
import sys
import tarfile


def sha(data):return hashlib.sha256(data).hexdigest()
def rows(path):return [json.loads(line) for line in Path(path).open() if line.strip()]
def dump(path,value):Path(path).write_text(json.dumps(value,ensure_ascii=False,indent=2));Path(path).chmod(0o600)
def jsonl(path,values):Path(path).write_text(''.join(json.dumps(v,ensure_ascii=False)+'\n' for v in values));Path(path).chmod(0o600)
def fingerprint(row):
    # Match PHP's canonical JSON (including escaped slashes and non-ASCII).
    return sha(json.dumps(row,sort_keys=True,separators=(',',':'),ensure_ascii=True).replace('/','\\/').encode())


def prepare(run, baseline, catalogue_path, bundle_path):
    run,baseline=Path(run),Path(baseline);source=run/'export';target=run/'ready';target.mkdir(mode=0o700)
    cp={x['id']:x for x in rows(run/'codingprojects/tasks.jsonl')}
    courses={x['id']:x for x in rows(run/'codingprojects/courses.jsonl')};users={x['id'] for x in rows(run/'codingprojects/users.jsonl')}
    review={x['legacy_task_id']:x for x in json.load(open(baseline/'reports/ai-task-plan.json'))['tasks']}
    cat=json.load(open(catalogue_path));files={}
    with tarfile.open(source/'environments.tar.gz') as a:
        for m in a:
            if not m.isfile():raise ValueError('Nonregular source environment')
            data=a.extractfile(m).read();files[m.name]=dict(sha256=sha(data),bytes=len(data))
    if files!=cat['source_files']:raise ValueError('Environment files changed after manual review')
    (target/'blobs').mkdir(mode=0o700)
    for path in (source/'blobs').iterdir():os.link(path,target/'blobs'/path.name)
    bundle=Path(bundle_path).read_bytes();digest=sha(bundle);(target/'blobs'/digest).write_bytes(bundle);(target/'blobs'/digest).chmod(0o600)
    descriptor=dict(path='blobs/'+digest,sha256=digest,bytes=len(bundle))
    tasks=[];plan={};mapping={};deferred=[];changes=[]
    for row in rows(source/'tasks.jsonl'):
        legacy=row['id'];key=str(legacy);tid=int(key[3:] if key.startswith('cp-') else key)
        if tid not in cp:
            deferred.append(dict(kind='task',reason='target_task_was_deleted',record=row));continue
        current=cp[tid];entry=dict(task_id=tid,source_definition_sha256=fingerprint(row),
            target_public_text_sha256=sha((current['text'] or '').encode()),target_points=int(current['max_mark']),action='configure',private_instructions='')
        if row.get('migration_archive_only'):
            entry['action']='archive_only'
        elif row['check_type']=='gpt':
            reviewed=review.get(legacy)
            if not reviewed or reviewed['source_prompt_sha256']!=sha((row['text'] or '').encode()) or reviewed['target_public_text_sha256']!=entry['target_public_text_sha256']:
                entry['action']='archive_only';changes.append(dict(task_id=tid,reason='AI_text_changed_since_review'))
            else:
                r=reviewed['review'];entry['private_instructions']=r.get('private_instructions') or ''
                if r.get('reference_answer'):entry['reference_answer']=r['reference_answer']
                if legacy==1415:
                    entry['private_instructions']=r['proposed_private_instructions']
                    entry['set_public_text']=current['text']+'\n\nУточнение критерия последнего задания: требуется MSE строго меньше 200.'
                elif legacy==1439:
                    entry['private_instructions']='Целевая метрика relative_error = mean((y - y_pred)^2 / y^2) на тестовой выборке, не выше 0.165. В исходном примере TF-IDF + Ridge ошибка 0.170221641 (17.02%); требуется улучшение до 16.5% или ниже. Данные Train_rev1.csv; цель SalaryNormalized. Не добавлять новые данные и не использовать SalaryRaw как признак: это утечка целевой зарплаты. Отдельно сравнить результат при обучении на 100000 примеров. Сравнивать модели на одной и той же отложенной выборке, не по ошибке обучения; отсутствие конкретного random_state в старом примере не превращать в новое формальное требование. Изменение числа признаков, лемматизация и другие модели — способы достичь цели, а не три обязательных независимых критерия.'
                    entry['set_public_text']=current['text']+'\n\nПод 16,5% понимается относительная ошибка на тестовой выборке: mean((y − y_pred)² / y²) ≤ 0,165. Сравните её с исходной моделью и отдельно покажите результат обучения на 100 000 примеров.'
                elif legacy==1885:
                    coords=[(0,0),(1,1),(1,-1),(-1,1),(-1,-1),(1000000000,1000000000),(-1000000000,1000000000)]
                    coords += [(2,0),(0,2),(-2,0),(0,-2),(3,1),(1,3),(-3,1),(3,-1),(999999999,-999999997)]
                    coords += [(1,0),(0,1),(-1,0),(0,-1),(2,1),(1,2),(-2,1),(1000000000,999999999)]
                    tests=[]
                    for x,y in coords:
                        answer=0 if x==y==0 else 1 if abs(x)==abs(y) else 2 if (x+y)%2==0 else -1
                        group='diagonal' if answer in (0,1) else 'reachable' if answer==2 else 'unreachable'
                        tests.append(dict(input=str(x)+'\n'+str(y)+'\n',output=str(answer)+'\n',is_public=(x,y) in ((0,0),(1,0)),group=group))
                    entry.update(check_type='tests',test_mode='io',test_config=dict(comparison='tokens',scoring='groups',
                        groups=[dict(id='diagonal',name='Начальная точка и диагонали',points=5),dict(id='reachable',name='Два прыжка',points=5),dict(id='unreachable',name='Недостижимые точки',points=5)],tests=tests),private_instructions='')
                    entry['set_public_text']=current['text']+'\n\nФормат ввода: два целых числа M и N, каждое на отдельной строке, от −10⁹ до 10⁹. Формат вывода: одно целое число — минимальное число прыжков; −1, если точка недостижима. В начальной точке ответ 0. Поясняющие строки и приглашения ко вводу выводить не нужно.'
                elif legacy==2134:entry['set_max_mark']=15
                elif legacy==2241:
                    entry['private_instructions']='Бот отвечает на вопросы с помощью YandexGPT. Не требовать интеграцию с ChatGPT/OpenAI: преподаватель подтвердил YandexGPT.'
                    entry['set_public_text']=current['text'].replace('ChatGPT','YandexGPT')
                elif legacy==2414:entry['private_instructions']=r['proposed_private_instructions']
                elif r['status']!='reviewed':raise ValueError('Unresolved AI task '+str(legacy))
        elif row.get('test_mode')!='io':
            spec=cat['tasks'].get(str(legacy))
            if not spec or spec['points']!=current['max_mark'] or spec['language']!=row['lang']:raise ValueError('Suite mapping changed '+str(legacy))
            entry.update(test_mode='suite',test_config=dict(suite_id=str(legacy),suite_version=cat['version'],bundle=descriptor))
        if row['points']!=current['max_mark'] and legacy!=2134 and not row.get('migration_archive_only'):
            entry['action']='archive_only';changes.append(dict(task_id=tid,reason='maximum_changed_since_review'))
        tasks.append(row);mapping[str(legacy)]=tid;plan[str(legacy)]=entry
    solutions=[]
    for row in rows(source/'solutions.jsonl'):
        tid=mapping.get(str(row['task_id']));course=courses.get(row.get('course_id'))
        if not tid or not course or row.get('user_id') not in users or cp[tid]['program_id']!=course['program_id']:
            deferred.append(dict(kind='solution',reason='unresolved_owner_course_task',record=row));continue
        solutions.append(row)
    ids={x['id'] for x in solutions};edges=[]
    for row in rows(source/'similarities.jsonl'):
        if row['code_id'] in ids and row['code_id2'] in ids:edges.append(row)
        else:deferred.append(dict(kind='similarity',reason='deferred_endpoint',record=row))
    jsonl(target/'tasks.jsonl',tasks);jsonl(target/'solutions.jsonl',solutions);jsonl(target/'similarities.jsonl',edges);jsonl(target/'deferred-records.jsonl',deferred)
    (target/'source-manifest.json').write_bytes((source/'manifest.json').read_bytes())
    audit=dict(source_manifest_sha256=sha((source/'manifest.json').read_bytes()),suite_version=cat['version'],
               policy='Only existing target tasks, valid contexts, same-owner supplemental sources; no regrading or source deletion',tasks=plan,not_configured=changes)
    dump(target/'migration-plan.json',audit)
    for name in ['environments.tar.gz']:
        os.link(source/name,target/name)
    names=['tasks.jsonl','solutions.jsonl','similarities.jsonl','deferred-records.jsonl','source-manifest.json','migration-plan.json','environments.tar.gz']
    manifest=dict(schema_version=2,source='GeekPasteV2',source_data_deleted=False,snapshot='curated-repeatable-read',
        exported_at=json.load(open(source/'manifest.json'))['exported_at'],files={n:sha((target/n).read_bytes()) for n in names},
        counts=dict(tasks=len(tasks),solutions=len(solutions),similarities=len(edges),blobs=len(list((target/'blobs').iterdir())),
                    missing_original_archives=sum(x['lang']=='zip' and not x.get('original_archive') for x in solutions)))
    dump(target/'manifest.json',manifest)
    dump(run/'task-plan.json',dict(schema_version=1,source_manifest_sha256=sha((target/'manifest.json').read_bytes()),tasks=plan))
    dump(run/'map.json',dict(tasks=mapping,courses={},users={}))
    summary=dict(counts=manifest['counts'],deferred={kind:sum(x['kind']==kind for x in deferred) for kind in ('task','solution','similarity')},
                 configured=sum(x['action']=='configure' for x in plan.values()),archival_tasks=sum(x['action']=='archive_only' for x in plan.values()),
                 suites=sum(x.get('test_mode')=='suite' for x in plan.values()),not_configured=changes)
    dump(run/'selection-summary.json',summary);print(json.dumps(summary,ensure_ascii=False))
    for p in target.rglob('*'):
        if p.is_file():p.chmod(0o600)


if __name__=='__main__':prepare(*sys.argv[1:])
