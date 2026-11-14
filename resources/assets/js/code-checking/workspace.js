import {node, button, field, input, textarea, select, actions, check, modes, phases, verdicts, request, download} from './ui';
import {subscribe} from './progress';

export async function workspace(root) {
    const base = root.dataset.endpoint;
    const teacher = root.dataset.teacher === 'true';
    let config, draft, files = [], uploads = [], dirty = false, editing, stop = () => {}, page = 1, current, requestId, generation = 0;
    let saving = false, unreadOnly = false, compareWith = null;
    const notice = node('div', {role:'status', 'aria-live':'polite'});
    const attachmentsArea = node('div');
    const content = node('div');
    const results = node('div');
    const history = node('div');
    root.replaceChildren(notice, attachmentsArea, content, results, history);
    const error = e => {
        notice.className = 'alert alert-danger'; notice.textContent = e.message;
        if (e.status === 409 && dirty && draft) notice.append(actions(button('Скачать мой черновик',() => {
            const url = URL.createObjectURL(new Blob([JSON.stringify({mode:draft.mode,language:draft.language,text:draft.text,files:files.map(file => ({path:file.path,content:file.content})),repository:draft.repository},null,2)],{type:'application/json'}));
            const link = node('a',{href:url,download:'my-draft.json'}); link.click(); setTimeout(() => URL.revokeObjectURL(url),1000);
        }),button('Загрузить серверную версию',safe(async () => {
            if (!confirm('Заменить локальный черновик версией с сервера? Сначала скачайте нужный код и сохраните выбранные файлы.')) return;
            setDraft(await request(base+'/code-draft')); renderComposer(); notice.replaceChildren();
        }))));
    };
    const safe = action => async event => {event?.preventDefault(); try {await action(event);} catch (e) {error(e);} };
    const markDirty = () => {dirty = true; requestId = null; notice.className = 'text-muted small'; notice.textContent = 'Есть несохранённые изменения';};
    window.addEventListener('beforeunload', event => {if (dirty) {event.preventDefault(); event.returnValue = '';}});
    window.addEventListener('pagehide', () => {stop(); editing?.destroy();}, {once:true});

    function setDraft(value) {
        draft = value;
        if (value.revision === 0 && config.submission_modes.includes('files')) draft.mode = 'files';
        files = value.files.map((file,index) => ({...file, index})).filter(file => !file.is_answer);
        uploads = []; dirty = false;
    }
    function payload() {
        const body = {revision:draft.revision, mode:draft.mode, language:draft.language, text:draft.text,
            draft_revision:draft.revision, keep_files:files.filter(f => f.content === undefined).map(f => f.index),
            editor_files:files.filter(f => f.content !== undefined).map(f => ({path:f.path, content:f.content})),
            repository_url:draft.repository?.url || '', repository_ref:draft.repository?.ref || null};
        if (draft.mode === 'code' && uploads.length) {body.keep_files = []; body.editor_files = [];}
        if (!uploads.length) return body;
        const data = new FormData();
        for (const [key,value] of Object.entries(body)) {
            if (Array.isArray(value)) value.forEach((row,index) => {
                if (typeof row === 'object') Object.entries(row).forEach(([k,v]) => data.append(`${key}[${index}][${k}]`,v));
                else data.append(`${key}[${index}]`,row);
            });
            else if (value !== null) data.append(key, value);
        }
        uploads.forEach((file,index) => {data.append('files[]',file); data.append(`file_paths[${index}]`,file.name);});
        return data;
    }
    async function save() {
        if (saving) return;
        saving = true; content.setAttribute('inert','');
        try {
            const value = await request(base+'/code-draft','PUT',payload());
            setDraft(value); renderComposer(); notice.className = 'text-muted small'; notice.textContent = 'Черновик сохранён';
        } finally {saving = false; content.removeAttribute('inert');}
    }
    async function submit() {
        if (saving) return;
        if (dirty || draft.revision === 0) await save();
        requestId ||= crypto.randomUUID();
        content.setAttribute('inert','');
        try {
            const record = await request(base+'/code-submissions','POST',{request_id:requestId, use_draft:true, draft_revision:draft.revision});
            notice.className = 'text-muted small'; notice.textContent = 'Решение отправлено';
            await show(record.id); await loadHistory();
        } catch (e) {
            if (e.status === 429) {
                notice.replaceChildren(node('p',{},e.message), button('Дополнительная попытка', safe(async () => {
                    if (!confirm(`Приобрести дополнительную попытку за ${root.dataset.attemptPrice || '5'} GC?`)) return;
                    const value = await request(base+'/checker/extra-attempt','POST',{});
                    notice.textContent = `Попытка доступна. Списано ${value.charged} GC. Нажмите «Отправить решение».`;
                })));
                notice.className = 'alert alert-warning';
            } else throw e;
        } finally {content.removeAttribute('inert');}
    }
    function renderComposer() {
        editing?.destroy(); editing = null;
        const available = Object.fromEntries(Object.entries(modes).filter(([key]) => config.submission_modes.includes(key)));
        const controls = actions(field('Способ сдачи',select(draft.mode,available,mode => {
            draft.mode = mode;
            if (mode === 'code') {files = files.filter(f => f.content !== undefined).slice(0,1); uploads = []; if (!files.length) files = [{path:config.entrypoint || (draft.language === 'cpp' ? 'main.cpp' : 'main.py'),content:''}];}
            markDirty(); renderComposer();
        })), field('Язык',select(draft.language,Object.fromEntries(config.allowed_languages.map(l => [l,l === 'text' ? 'Текст / файлы' : l])),value => {draft.language = value; markDirty(); renderComposer();})));
        const body = node('div', {class:'gc-execution-composer'}, node('h3',{class:'h6'},'Сдать решение'), node('p',{class:'gc-help-text'},'Загрузите готовую работу или вставьте код из своего редактора.'), controls);
        if (draft.mode === 'repository') {
            draft.repository ||= {};
            body.append(field('Публичный репозиторий GitHub',input(draft.repository.url, value => {draft.repository.url = value; markDirty();},{type:'url',placeholder:'https://github.com/username/project'})),
                field('Ветка, тег или коммит',input(draft.repository.ref,value => {draft.repository.ref = value; markDirty();}), 'Можно оставить пустым. Сохранится конкретный коммит.'));
        } else {
            body.append(field(draft.mode === 'text' ? 'Ответ' : 'Пояснение к решению',textarea(draft.text,value => {draft.text = value; markDirty();},{rows:draft.mode === 'text' ? 8 : 3})));
            if (['code','project'].includes(draft.mode)) {
                const editable = files.filter(f => f.content !== undefined);
                if (!editable.length) files.push({path:config.entrypoint || (draft.language === 'cpp' ? 'main.cpp' : 'main.py'),content:''});
                const tabs = node('div',{class:'gc-execution-files'});
                for (const file of files.filter(f => f.content !== undefined)) {
                    const host = node('div',{class:'gc-code-editor'});
                    const section = node('details',{open:true}, node('summary',{},file.path || 'Новый файл'),
                        actions(field('Имя файла',input(file.path,value => {file.path = value; markDirty();},{placeholder:'lib/helper.py'})),
                            draft.mode === 'project' ? button('Удалить файл',() => {files = files.filter(f => f !== file); markDirty(); renderComposer();}) : null), host);
                    tabs.append(section);
                    import('./editor').then(({editor}) => {
                        if (!host.isConnected) return;
                        const view = editor(host,file.content,draft.language,value => {file.content = value; markDirty();});
                        const previous = editing;
                        editing = {destroy:() => {previous?.destroy(); view.destroy();}};
                    }).catch(error);
                }
                body.append(tabs);
                if (draft.mode === 'project') body.append(button('Добавить файл кода',() => {files.push({path:`file${files.length+1}.${draft.language === 'cpp' ? 'cpp' : 'py'}`,content:''}); markDirty(); renderComposer();}));
            }
            {
                const upload = node('input',{type:'file',class:'form-control',multiple:draft.mode !== 'code', accept:config.allowed_extensions?.map(e => '.'+e).join(',') || '',
                    onchange:event => {
                        const selected = [...event.target.files];
                        if (selected.some(file => file.size > config.max_file_bytes)) {error(new Error('Один из файлов превышает допустимый размер.')); return;}
                        const names = new Set(selected.map(file => file.name));
                        files = files.filter(file => !names.has(file.path));
                        uploads = uploads.filter(file => !names.has(file.name));
                        if (draft.mode === 'code') uploads = selected.slice(0,1); else uploads.push(...selected);
                        markDirty(); renderComposer();
                    }});
                body.append(field(draft.mode === 'code' ? 'Или загрузить исходник вместо редактора' : 'Добавить файлы (ZIP, Excel, изображения и другие разрешённые форматы)',upload,
                    `До ${config.max_files} файлов, суммарно ${Math.round(config.max_total_bytes/1048576)} МБ.`));
                body.append(node('ul',{}, (['files','text'].includes(draft.mode) ? files : files.filter(f => f.content === undefined)).map(file => node('li',{}, file.download_url ? download(file.download_url,file.path) : file.path, ' ', button('Убрать',() => {files = files.filter(f => f !== file); markDirty(); renderComposer();}))),
                    uploads.map(file => node('li',{},file.name,' ',button('Убрать',() => {uploads = uploads.filter(f => f !== file); markDirty(); renderComposer();})))));
            }
        }
        body.append(actions(button('Отправить решение',safe(submit),'primary'),button('Сохранить черновик',safe(save)),button('Восстановить шаблон',safe(async () => {
            if (!confirm('Восстановить шаблон? Текущий черновик будет заменён.')) return;
            setDraft(await request(base+'/code-draft','DELETE',{revision:draft.revision})); renderComposer();
        }))));
        content.replaceChildren(body);
    }
    function artifact(value) {
        if (!value) return node('span',{class:'text-muted'},'Нет данных');
        return value.type === 'inline' ? node('pre',{class:'gc-execution-output'},value.text || '(пусто)') : node('div',{},download(value.download_url,`Скачать (${value.bytes} байт)`),value.truncated ? ' · вывод сокращён' : '');
    }
    function renderReport(report, parent) {
        if (!report) return;
        for (const note of report.notes || []) parent.append(node('p',{},note));
        if (report.notice) parent.append(node('p',{class:'alert alert-info'},report.notice));
        parent.append(node('h4',{class:'h6'},`Тесты: ${report.passed} из ${report.total}`));
        report.tests.forEach(test => {
            const detail = node('details',{},node('summary',{},`Тест ${test.number} · ${verdicts[test.verdict] || test.verdict}${test.unlocked ? '' : ' · скрытый'}`));
            if (test.unlocked) {for (const [key,label] of Object.entries({input:'Ввод',expected:'Ожидается',actual:'Получено',error:'Ошибка'})) if (test[key]) detail.append(node('div',{},node('strong',{},label),artifact(test[key])));}
            else if (test.can_purchase) detail.append(node('p',{class:'text-muted small'},'Откроется этот тест для следующих попыток. Оценка не изменится.'),button(`Открыть за ${report.price} GC`,safe(async () => {
                if (!confirm(`Открыть тест ${test.number} за ${report.price} GC?\nБаланс: ${config.balance} GC. Тест останется доступным в следующих попытках.`)) return;
                const value = await request(`/insider/code-test-reports/${report.run_id}/tests/${test.test_id}/purchase`,'POST',{expected_price:report.price});
                notice.className = 'text-muted small'; notice.textContent = `Списано ${value.charged} GC. Баланс: ${value.balance} GC.`;
                config.balance = value.balance;
                await show(current.id);
            })));
            parent.append(detail);
        });
    }
    async function show(id) {
        const selected = ++generation;
        stop();
        const record = await request('/insider/code-submissions/'+id);
        if (selected !== generation) return;
        if (compareWith && compareWith.id !== id) {
            const target = compareWith; compareWith = null;
            const value = await request(`/insider/code-submissions/${target.id}/compare?with=${id}`);
            const {diffLines} = await import('./diff');
            const panel = node('section',{class:'gc-execution-result'},node('h3',{class:'h6'},'Сравнение попыток'),node('p',{class:'text-muted small'},'− выбранная в истории · + исходная попытка'));
            const oldFiles = new Map(value.before.files.map(f => [f.name,f]));
            const newFiles = new Map(value.after.files.map(f => [f.name,f]));
            if (value.before.text !== value.after.text) {oldFiles.set('Текст ответа',{text:value.before.text});newFiles.set('Текст ответа',{text:value.after.text});}
            for (const name of new Set([...oldFiles.keys(),...newFiles.keys()])) {
                const before = oldFiles.get(name), after = newFiles.get(name);
                const detail = node('details',{},node('summary',{},name));
                if (before?.sha256 && before.sha256 === after?.sha256) detail.append(node('p',{},'Без изменений'));
                else if ((!before || before.text !== undefined) && (!after || after.text !== undefined)) {
                    const pre = node('pre',{class:'gc-execution-output gc-execution-diff'});
                    diffLines(before?.text || '',after?.text || '').forEach(line => pre.append(node('span',{class:`gc-diff-${line.type}`},`${{same:' ',added:'+',removed:'−'}[line.type]} ${line.text}\n`)));
                    detail.append(pre);
                } else detail.append(node('p',{},!before ? 'Файл добавлен' : !after ? 'Файл удалён' : 'Файл изменён. Для больших и двоичных файлов доступно скачивание из каждой попытки.'));
                panel.append(detail);
            }
            results.replaceChildren(panel);notice.replaceChildren();return;
        }
        current = record;
        const status = node('div',{role:'status','aria-live':'polite',class:'gc-execution-status'}, phases[record.progress?.phase || record.state] || record.state);
        const body = node('section',{class:'gc-execution-result'},actions(node('h3',{class:'h6'},'Отправленное решение'),button('Обновить результат',safe(() => show(id)))),status);
        if (record.points !== null) body.append(node('p',{},`Результат проверки: ${record.points} / ${record.maximum_points} баллов`));
        if (record.awarded_xp !== null) body.append(node('p',{},`Оценка работы: ${record.awarded_xp} XP`));
        if (record.text) body.append(node('pre',{class:'gc-execution-output'},record.text));
        for (const file of record.files) {
            const detail = node('details',{},node('summary',{},file.original_name || file.name));
            detail.append(download(file.download_url,'Скачать оригинал'));
            if (file.source !== undefined) detail.append(node('pre',{class:'gc-execution-output'},file.source));
            if (/\.(png|jpe?g|gif|webp)$/i.test(file.name)) {
                const preview = button('Показать изображение',safe(async () => {
                    const response = await fetch(file.download_url+'?proxy=1',{credentials:'same-origin'});
                    if (!response.ok) throw new Error('Не удалось загрузить изображение');
                    const url = URL.createObjectURL(await response.blob());
                    const img = node('img',{src:url,alt:file.name,class:'gc-execution-image',onload:() => URL.revokeObjectURL(url)});
                    preview.replaceWith(img);
                })); detail.append(preview);
            }
            body.append(detail);
        }
        if (record.repository?.commit) body.append(node('p',{},`GitHub · ${record.repository.commit}`, ' ',download(`/insider/code-submissions/${id}/repository/archive`,'Скачать репозиторий')));
        if (record.comment && !record.report?.legacy) body.append(node('pre',{class:'gc-execution-output'},record.comment));
        renderReport(record.report,body);
        if (record.legacy) {
            body.append(node('p',{},download(record.legacy.source_url,'Скачать исходную запись GeekPaste')));
            if (record.legacy.requires_conversion || record.legacy.warnings.length) body.append(node('p',{class:'alert alert-warning'},'Историческая работа. Часть материалов может требовать восстановления или преобразования; сохранена исходная запись.'));
            if (record.legacy.commit_unknown) body.append(node('p',{class:'text-muted small'},'Исходники GitHub сохранены из старой базы. Коммит и оригинальный архив неизвестны.'));
        }
        if (!teacher) {
            body.append(button(record.is_public ? 'Закрыть публичный доступ' : 'Поделиться решением',safe(async () => {
                if (!record.is_public && !confirm('Опубликовать отправленный текст и все файлы? Их сможет скачать любой, у кого есть ссылка. Тесты и критерии остаются закрытыми.')) return;
                await request(`/insider/code-submissions/${id}/flags`,'PUT',{is_public:!record.is_public}); await show(id);
            })));
            if (record.is_public) body.append(download(`/shared/code-submissions/${id}`,'Публичная ссылка'));
            if (record.review_request?.requested) body.append(node('p',{class:'text-muted'},record.review_request.message));
            else if (record.review_request?.available) {
                let comment = '';
                const review = node('details',{},node('summary',{},'Попросить преподавателя проверить'),field('Что нужно проверить',textarea('',value => {comment = value;},{minlength:10,maxlength:1000})),button('Отправить запрос',safe(async () => {
                    await request(`/insider/code-submissions/${id}/review-request`,'POST',{comment}); await show(id);
                })));
                body.append(review);
            }
        }
        body.append(button('Сравнить с другой попыткой',safe(async () => {
            compareWith = record; notice.className='alert alert-info'; notice.textContent='Выберите другую попытку этого ученика в истории ниже.';
        })));
        if (teacher) {
            if (record.review_request?.requested) body.append(node('p',{class:'alert alert-info'},'Запрос ученика: '+record.review_request.comment));
            body.append(button(record.teacher_viewed ? 'Отметить непросмотренным' : 'Отметить просмотренным',safe(async () => {await request(`/insider/code-submissions/${id}/flags`,'PUT',{teacher_viewed:!record.teacher_viewed}); await show(id); await loadHistory();})));
            if (['completed','error','imported_pending'].includes(record.state)) body.append(button('Оценить вручную',safe(async () => {await request(`/insider/code-submissions/${id}/manual-review`,'POST',{}); await show(id);await loadHistory();})));
            const latest = node('input',{type:'checkbox',class:'form-check-input'});
            body.append(actions(button('Перепроверить',safe(async () => {await request(`/insider/code-submissions/${id}/recheck`,'POST',{latest_configuration:latest.checked}); await show(id);await loadHistory();})),field('По текущим настройкам',latest)));
            if (record.state === 'awaiting_review') {
                let points = '', comment = '';
                body.append(field('Баллы',input('',value => {points = value;},{type:'number',min:0,max:record.maximum_points})),field('Комментарий ученику',textarea('',value => {comment = value;})),button('Поставить оценку',safe(async () => {
                    if (points === '') throw new Error('Укажите оценку.');
                    await request(`/insider/code-submissions/${id}/grade`,'PUT',{points:Number(points),comment}); await show(id);await loadHistory();
                }),'primary'));
            }
            if (record.integrity) {
                const review = node('details',{},node('summary',{},record.integrity.dismissed ? 'Анализ авторства · предупреждение снято' : 'Анализ авторства'));
                const plagiarism = record.integrity.plagiarism || {};
                const ai = record.integrity.ai || {};
                review.append(node('p',{},'Автоматический анализ — повод для проверки преподавателем.'),
                    node('p',{},plagiarism.state !== 'done' ? 'Сравнение исходников не проводилось.' : plagiarism.warning ? `Найдены похожие работы: ${plagiarism.matches?.length || 0}` : 'Значимых совпадений не найдено.'),
                    ...(plagiarism.matches || []).map(match => node('p',{},download(`/insider/code-submissions/${match.submission_id}`,'Открыть похожую работу'),` · совпадение ${match.percent}%`)),
                    node('p',{},record.integrity.state === 'running' ? 'Анализ помощи ИИ выполняется…' : ai.state === 'error' ? 'Анализ помощи ИИ недоступен.' : !['done','imported'].includes(ai.state) ? 'Анализ помощи ИИ не проводился.' : ai.warning ? 'Есть признаки возможной помощи ИИ.' : 'Предупреждений об использовании ИИ нет.'),
                    node('ul',{},(ai.reasons || []).map(reason => node('li',{},reason))),
                    button(record.integrity.dismissed ? 'Вернуть предупреждение' : 'Снять предупреждение',safe(async () => {
                    await request(`/insider/code-submissions/${id}/integrity/review`,'PUT',{dismissed:!record.integrity.dismissed}); await show(id);
                }))); body.append(review);
            }
        }
        if (record.runs.length > 1) {
            const runs = node('details',{},node('summary',{},'История проверок'));
            record.runs.forEach(run => runs.append(button(`${new Date(run.created_at).toLocaleString()} · ${phases[run.state] || run.state}`,safe(async () => {
                const value = await request(`/insider/code-submissions/${id}/runs/${run.id}`);
                const result = node('div',{},node('pre',{class:'gc-execution-output'},value.comment || 'Без комментария'));
                for (const file of value.files || []) result.append(node('p',{},download(file.download_url,file.name)));
                try {renderReport(value.report || await request('/insider/code-test-reports/'+run.id),result);} catch (e) {if (e.status !== 404) throw e;}
                runs.append(result);
            })))); body.append(runs);
        }
        results.replaceChildren(body);
        if (!['completed','error','awaiting_review','imported_pending'].includes(record.state)) {
            stop = subscribe(record.subscription,event => {status.textContent = `${phases[event.phase] || event.phase}${event.total ? ` · ${event.completed} из ${event.total}` : ''}`;},
                async () => {
                    if (current?.id !== id) return;
                    try {
                        await show(id);
                        if (!teacher && current?.id === id && current.awarded_xp !== null) document.dispatchEvent(new CustomEvent('gc:task-checked', {detail:{taskId:current.task_id,mark:current.awarded_xp}}));
                    } catch (e) {error(e);}
                }, () => {status.textContent = 'Связь прервана. Восстанавливаем подключение…';},
                () => request(`/insider/code-submissions/${id}/subscription`));
        }
    }
    async function loadHistory(append = false) {
        if (!append) page = 1;
        const query = new URLSearchParams({page:String(page)});
        if (teacher && unreadOnly) query.set('teacher_viewed','0');
        if (root.dataset.user) query.set('user_id',root.dataset.user);
        const value = await request(base+'/code-submissions?'+query);
        if (!append) history.replaceChildren(node('h3',{class:'h6 mt-3'},teacher ? 'Решения учеников' : 'Ваши попытки'));
        if (teacher && !append && !root.dataset.user) history.append(button('Перепроверить последние работы группы',safe(async () => {
            if (!confirm('Перепроверить последние решения всех учеников с текущими настройками? Прежние оценки сохранятся до нового результата.')) return;
            const value = await request(base+'/code-submissions/recheck-latest','POST',{latest_configuration:true});
            notice.className = 'text-muted small'; notice.textContent = `Проверок запущено: ${value.execution_ids.length}. Уже выполняются: ${value.skipped_active.length}.`;
        })));
        if (teacher && !append) history.append(check('Только непросмотренные',unreadOnly,value => {unreadOnly=value; page=1;loadHistory().catch(error);}));
        value.items.forEach(record => history.append(button(`${new Date(record.created_at).toLocaleString()}${teacher && !record.teacher_viewed ? ' · новое' : ''}${teacher ? ` · ${record.user_name || 'Ученик '+record.user_id}` : ''} · ${phases[record.state] || record.state}${record.points !== null ? ` · ${record.points} баллов` : ''}`,safe(() => show(record.id)))));
        if (value.has_more) {const more = button('Показать ещё',safe(async () => {more.remove(); page++; await loadHistory(true);})); history.append(more);}
        return value.items;
    }
    try {
        config = await request(base+'/checker');
        if (!teacher && root.dataset.blocked !== 'true' && !config.requires_conversion) {setDraft(await request(base+'/code-draft')); renderComposer();}
        else if (!teacher) content.append(node('p',{class:'alert alert-warning'},config.requires_conversion ? 'Преподавателю нужно настроить проверку перед сдачей.' : 'Новые сдачи этой задачи заблокированы.'));
        const attachments = await request(base+'/checker/files');
        if (attachments.items.length) attachmentsArea.append(node('div',{class:'gc-execution-attachments'},node('h3',{class:'h6'},'Материалы задания'), attachments.items.map(file => node('div',{}, download(file.download_url,file.path),file.read_only ? ' · только чтение' : ''))));
        const records = await loadHistory();
        if (root.dataset.submission) await show(root.dataset.submission);
        else if (records[0]) await show(records[0].id);
    } catch (e) {error(e); notice.append(button('Повторить загрузку',() => workspace(root)));}
}
