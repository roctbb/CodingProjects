import {node, button, field, input, textarea, select, actions, modes, phases, verdicts, request, download} from './ui';
import {subscribe} from './progress';
import {resultHeader,submittedWork,resultActions} from './result-view';

export async function workspace(root) {
    const base = root.dataset.endpoint;
    const teacher = root.dataset.teacher === 'true';
    const inlineReview = root.hasAttribute('data-execution-inline-review');
    let config, draft, files = [], uploads = [], dirty = false, editing, stop = () => {}, page = 1, current, requestId, generation = 0;
    let saving = false, compareWith = null, settingsPromise, historyGeneration = 0, ownMode = false;
    let selectedUser = root.dataset.user ? {id:root.dataset.user,name:''} : null;
    let attemptItems = [], activeAttempt = null, attemptList;
    const toolbar = node('div',{class:'gc-execution-toolbar'});
    const notice = node('div', {role:'status', 'aria-live':'polite'});
    const attachmentsArea = node('div');
    const content = node('div');
    const results = node('div');
    const history = node('div');
    const browser = node('div',{class:'gc-execution-browser'},history,results);
    root.replaceChildren(toolbar,notice,attachmentsArea,content,browser);
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
    root.addEventListener('gc:execution-close',()=>{generation++;historyGeneration++;stop();current=null;});

    async function settings() {
        if (config) return config;
        settingsPromise ||= request(base+'/checker').then(value => config=value).catch(e => {settingsPromise=null;throw e;});
        return settingsPromise;
    }
    function renderToolbar() {
        if (!teacher) return;
        const title = ownMode ? 'Моя сдача' : selectedUser?.name || 'Сдачи ученика';
        const heading = node('div',{},node('h3',{class:'h6 mb-1',tabIndex:-1},title),node('p',{class:'text-muted small mb-0'},ownMode ? 'Применяются обычные правила проверки и сроков. Опыт и награды начисляются как обычно.' : 'История сдач и результаты проверки.'));
        toolbar.replaceChildren(heading);
        if (inlineReview) toolbar.prepend(button('К списку учеников',() => {
            root.dispatchEvent(new CustomEvent('gc:execution-close'));
        }));
    }
    async function attachments() {
        if (attachmentsArea.dataset.loaded) return;
        const value = await request(base+'/checker/files');
        attachmentsArea.dataset.loaded = 'true';
        if (value.items.length) attachmentsArea.replaceChildren(node('div',{class:'gc-execution-attachments'},node('h3',{class:'h6'},'Материалы задания'),value.items.map(file => node('div',{},download(file.download_url,file.path),file.read_only ? ' · только чтение' : ''))));
    }
    async function selectStudent(student,{autoOpen=true,attempt=null}={}) {
        generation++;historyGeneration++;stop();current=null;activeAttempt=null;compareWith=null;
        selectedUser=student;ownMode=false;attemptItems=[];attemptList=null;
        content.hidden=true;attachmentsArea.hidden=true;results.replaceChildren();notice.replaceChildren();renderToolbar();
        try {
            const records=await loadHistory();
            if(selectedUser===student && !ownMode){
                if(attempt)await openAttemptById(attempt);
                else if(autoOpen && records[0])await openAttempt(records[0]);
            }
        } catch(e) {history.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку сдач',safe(() => selectStudent(student))));}
    }
    async function startOwnSubmission({attempt=null}={}) {
        generation++;historyGeneration++;stop();current=null;activeAttempt=null;compareWith=null;
        ownMode=true;selectedUser=null;results.replaceChildren();notice.replaceChildren();renderToolbar();
        content.hidden=false;attachmentsArea.hidden=false;
        content.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем форму сдачи…'));
        try {
            let records;
            if(root.hasAttribute('data-history-only')){content.hidden=true;attachmentsArea.hidden=true;records=await loadHistory();}
            else{
                await settings();
                if (!config.teacher_submission_allowed) throw new Error('Сдача этой задачи пока недоступна. Проверьте настройки проверки.');
                if (!draft) setDraft(await request(base+'/code-draft'));
                renderComposer();
                [,records]=await Promise.all([attachments(),loadHistory()]);
            }
            if(ownMode){if(attempt)await openAttemptById(attempt);else if(records[0])await openAttempt(records[0]);}
        } catch(e) {content.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить',safe(startOwnSubmission)));}
    }

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
        const submittedGeneration=generation;
        if (dirty || draft.revision === 0) await save();
        requestId ||= crypto.randomUUID();
        content.setAttribute('inert','');
        try {
            const record = await request(base+'/code-submissions','POST',{request_id:requestId, use_draft:true, draft_revision:draft.revision});
            notice.className = 'text-muted small'; notice.textContent = 'Решение отправлено';
            if(generation===submittedGeneration){await loadHistory();if(generation===submittedGeneration)await show(record.id);}
        } catch (e) {
            if (e.status === 429 && teacher) {
                notice.className='alert alert-warning';notice.textContent='Лимит проверок исчерпан. Повторите сдачу позже или уточните настройки лимита проверки.';
            } else if (e.status === 429) {
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
    let reportSequence = 0;
    function renderReport(report, parent, submissionId) {
        if (!report) return;
        for (const note of report.notes || []) parent.append(node('p',{},note));
        if (report.notice) parent.append(report.legacy
            ? node('details',{class:'gc-report-provenance'},node('summary',{},'О данных исторической проверки'),node('p',{class:'text-muted small'},report.notice))
            : node('p',{class:'alert alert-info'},report.notice));
        const section = node('section',{class:'gc-test-report','aria-label':'Результаты тестов'});
        section.append(node('div',{class:'gc-test-report__heading'},node('h4',{class:'h6 mb-0'},'Результаты тестов'),
            node('span',{class:report.passed === report.total ? 'gc-test-total is-passed' : 'gc-test-total'},`${report.passed} из ${report.total} пройдено`)));
        const rows = node('tbody');
        const table = node('table',{class:'gc-test-table'},node('thead',{},node('tr',{},
            node('th',{scope:'col'},'Тест'),node('th',{scope:'col'},'Результат'),node('th',{scope:'col',class:'gc-test-access'},'Доступ'),node('th',{scope:'col'},node('span',{class:'visually-hidden'},'Данные теста')))),rows);
        const sequence = ++reportSequence;
        for (const test of report.tests || []) {
            const passed = test.verdict === 'passed';
            const detailsId = `${root.id || 'execution'}-report-${sequence}-test-${test.number}`;
            const details = node('div',{class:'gc-test-data'});
            const detailRow = node('tr',{class:'gc-test-detail',id:detailsId,hidden:true},node('td',{colSpan:4},details));
            let loaded=false, loading;
            const loadDetails=async () => {
                if(loaded)return;
                loading ||= request(`/insider/code-submissions/${submissionId}/runs/${report.run_id}/tests/${test.number}`).then(value => {
                    const data=value.test || value;
                    details.replaceChildren();
                    for(const [key,label] of Object.entries({input:'Ввод',expected:'Ожидаемый ответ',actual:'Вывод программы',error:'Ошибка'})) {
                        if(data[key])details.append(node('div',{},node('strong',{},label),artifact(data[key])));
                    }
                    if(data.notice)details.append(node('p',{class:'text-muted mb-0'},data.notice));
                    if(!details.children.length)details.append(node('p',{class:'text-muted mb-0'},'В этой проверке подробные данные теста не сохранялись.'));
                    loaded=true;
                }).catch(e => {
                    loading=null;
                    details.replaceChildren(node('p',{class:'text-danger mb-2'},e.message),button('Повторить загрузку теста',safe(loadDetails)));
                });
                details.replaceChildren(node('p',{class:'text-muted mb-0',role:'status'},'Загружаем данные теста…'));
                await loading;
            };
            const toggle = button('Данные',() => {
                detailRow.hidden = !detailRow.hidden;
                toggle.setAttribute('aria-expanded',String(!detailRow.hidden));
                toggle.textContent = detailRow.hidden ? 'Данные' : 'Свернуть';
                if(!detailRow.hidden && test.unlocked)loadDetails();
            });
            toggle.classList.add('btn-sm');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-controls',detailsId);toggle.setAttribute('aria-label',`Данные теста ${test.number}`);
            if (test.unlocked) {
                // Authorized test contents are fetched only when this row is expanded.
            } else if (test.can_purchase) {
                details.append(node('p',{class:'text-muted'},'После открытия данные этого теста будут доступны и в следующих попытках. Оценка этой работы сохранится.'));
                const purchase = button(`Открыть за ${report.price} GC`,safe(async () => {
                    await settings();
                    if (!confirm(`Открыть тест ${test.number} за ${report.price} GC?\nБаланс: ${config.balance} GC.`)) return;
                    purchase.disabled = true;
                    try {
                        const value = await request(`/insider/code-test-reports/${report.run_id}/tests/${test.test_id}/purchase?test_data=metadata`,'POST',{expected_price:report.price});
                        notice.className = 'text-muted small';notice.textContent = `Списано ${value.charged} GC. Баланс: ${value.balance} GC.`;config.balance = value.balance;
                        await show(current.id);
                    } finally {purchase.disabled = false;}
                }));
                details.append(purchase);
            } else details.append(node('p',{class:'text-muted mb-0'},report.legacy
                ? 'В старом отчёте сохранён результат, но ввод и вывод скрытого теста не записывались. Текущие тесты преподаватель может посмотреть отдельно.'
                : 'Данные этого теста скрыты. Результат проверки доступен в таблице.'));
            const verdict = node('span',{class:`gc-test-verdict ${passed ? 'is-passed' : 'is-failed'}`},
                node('i',{class:`fas ${passed ? 'fa-check-circle' : 'fa-circle-xmark'}`,'aria-hidden':'true'}),' ',verdicts[test.verdict] || test.verdict);
            const visibility=(test.is_public?'Публичный':'Скрытый')+(!teacher&&test.unlocked&&!test.is_public?' · Открыт вам':'');
            rows.append(node('tr',{},node('th',{scope:'row'},`№ ${test.number}`),node('td',{},verdict,node('small',{class:'gc-test-access-mobile text-muted'},visibility)),
                node('td',{class:'gc-test-access'},node('span',{class:'gc-test-visibility'},test.is_public ? 'Публичный' : 'Скрытый'),!teacher && test.unlocked && !test.is_public ? node('small',{class:'d-block text-muted'},'Открыт вам') : null),node('td',{},toggle)),detailRow);
        }
        section.append(node('div',{class:'gc-test-table-scroll'},table));parent.append(section);
    }
    function textReport(record) {
        const panel=node('details',{},node('summary',{},'Текстовый отчёт проверки'));
        const content=node('div');panel.append(content);
        let loaded=false,loading=false;
        const load=async () => {
            if(loaded || loading)return;
            loading=true;content.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем отчёт…'));
            try {
                const value=await request(record.text_report_url);
                content.replaceChildren(node('pre',{class:'gc-execution-output'},value.comment || 'Текстовый отчёт пуст.'));
                if(teacher && value.checker_diagnostics)content.append(node('pre',{class:'gc-execution-output'},JSON.stringify(value.checker_diagnostics,null,2)));
                loaded=true;
            }catch(e){content.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку отчёта',safe(load)));}
            finally{loading=false;}
        };
        panel.addEventListener('toggle',() => {if(panel.open)load();});
        return panel;
    }
    function currentTests() {
        const panel = node('details',{},node('summary',{},'Текущие тесты задачи (для преподавателя)'));
        let loaded = false;
        panel.addEventListener('toggle',safe(async () => {
            if (!panel.open || loaded) return;
            loaded = true;
            try {
                const value = await request(base+'/checker/private');
                const settings = value.config;
                panel.append(node('p',{class:'text-muted small'},'Это текущий набор тестов задачи. Он сохранён отдельно от исторических результатов и мог измениться после старой проверки.'));
                if (settings.check_type !== 'tests' || settings.test_mode !== 'io') {
                    panel.append(node('p',{},settings.test_mode === 'suite' ? 'Используется специальный проверяющий модуль. Его результаты доступны в отчёте проверки.' : 'Для задачи не настроены тесты ввода и вывода.')); return;
                }
                for (const [index,test] of settings.test_config.tests.entries()) {
                    const detail = node('details',{},node('summary',{},`Тест ${index+1} · ${test.is_public ? 'публичный' : 'скрытый'}`));
                    let previewed = false;
                    detail.addEventListener('toggle',safe(async () => {
                        if (!detail.open || previewed) return;
                        previewed = true;
                        for (const [key,label] of Object.entries({input:'Ввод',output:'Ожидаемый ответ'})) {
                            const data = test[key], field = node('div',{},node('strong',{},label));
                            detail.append(field);
                            if (data.type === 'inline') field.append(artifact(data));
                            else {
                                const url = base+'/checker/fixtures/'+data.blob_id;
                                field.append(download(url,`Скачать (${data.bytes} байт)`));
                                if (data.bytes <= 65536) {
                                    const response = await fetch(url,{credentials:'same-origin'});
                                    if (!response.ok) throw new Error('Не удалось загрузить тест');
                                    field.append(node('pre',{class:'gc-execution-output'},await response.text() || '(пусто)'));
                                }
                            }
                        }
                    }));
                    panel.append(detail);
                }
            } catch (e) {loaded = false; throw e;}
        }));
        return panel;
    }
    function markSelectedAttempt(id) {
        activeAttempt=id;
        writeContext(id);
        history.querySelectorAll('[data-attempt-id]').forEach(item => {
            const selected=item.dataset.attemptId === String(id);
            item.classList.toggle('is-selected',selected);item.setAttribute('aria-expanded',String(selected));
        });
        history.querySelectorAll('[data-attempt-body]').forEach(body=>{
            body.hidden=body.dataset.attemptBody!==String(id);
            if(!body.hidden)body.append(results);
        });
    }
    function attemptHeader(record){
        const label=record.kind==='legacy_attempt'?'Сдача':record.counts_as_attempt===false?'Сохранённая версия решения':'Решение';
        const score=record.kind==='legacy_attempt'?record.awarded_xp!==null&&record.awarded_xp!==undefined?`${record.awarded_xp} XP`:'Ожидает оценки':record.points!==null&&record.points!==undefined?`${record.points}${record.maximum_points&&!record.source_version_unknown?' / '+record.maximum_points:''} баллов`:phases[record.state] || 'Загружаем результат…';
        return [node('span',{},node('strong',{},record.created_at?new Date(record.created_at).toLocaleString('ru-RU'):label,record.is_best?node('small',{class:'gc-attempt-best ms-2'},'Лучшая'):null),node('small',{class:'text-muted'},record.created_at?label:'',teacher&&record.kind==='submission'&&!record.source_version_unknown&&record.teacher_viewed===false?' · Не просмотрено':'')),node('span',{class:'gc-attempt-list__score'},score),node('i',{class:'fas fa-chevron-down','aria-hidden':'true'})];
    }
    function attemptCard(record){
        const id=String(record.id),bodyId=root.id+'-attempt-'+id;
        const heading=button('',safe(async()=>{
            if(activeAttempt===id){generation++;stop();current=null;markSelectedAttempt(null);return;}
            await openAttempt(record);
        }));
        heading.className='gc-attempt-list__item';heading.dataset.attemptId=id;
        heading.id=bodyId+'-toggle';heading.setAttribute('aria-expanded',String(activeAttempt===id));heading.setAttribute('aria-controls',bodyId);
        heading.append(...attemptHeader(record));
        const body=node('div',{id:bodyId,class:'gc-attempt-card__body',hidden:activeAttempt!==id,'data-attempt-body':id,role:'region','aria-labelledby':heading.id});
        return node('li',{},node('article',{class:'gc-attempt-card'},heading,body));
    }
    function ensureAttemptCard(record){
        const id=String(record.id);let summary=attemptItems.find(item=>String(item.id)===id);
        if(!summary){summary={id,kind:record.kind || 'submission'};attemptItems.push(summary);}
        for(const key of ['kind','created_at','awarded_xp','points','maximum_points','state','counts_as_attempt','is_best','source_version_unknown','teacher_viewed','solution_id'])if(record[key]!==undefined)summary[key]=record[key];
        if(!attemptList?.isConnected){attemptList=node('ol',{class:'gc-attempt-list'});history.append(attemptList);}
        const heading=[...attemptList.querySelectorAll('[data-attempt-id]')].find(item=>item.dataset.attemptId===id);
        if(heading)heading.replaceChildren(...attemptHeader(summary));else attemptList.append(attemptCard(summary));
    }
    function writeContext(id){
        if(root.dataset.taskId && location.pathname.includes('/steps/') && root.getClientRects().length){
            const url=new URL(location.href);
            url.searchParams.set('task',root.dataset.taskId);
            url.searchParams.set('student',teacher && !ownMode ? selectedUser.id : root.dataset.viewerId);
            if(id)url.searchParams.set('attempt',id);else url.searchParams.delete('attempt');url.hash='task'+root.dataset.taskId;
            historyState(url);
        }
    }
    function historyState(url){globalThis.history.replaceState(globalThis.history.state,'',url);}
    async function openAttemptById(id){
        const item=attemptItems.find(item=>String(item.id)===String(id));
        if(item)return openAttempt(item);
        if(/^solution-\d+$/.test(id))return showLegacyAttempt({id,kind:'legacy_attempt',solution_id:Number(id.slice(9))});
        return show(id);
    }
    function renderAward(record,parent,{label=true}={}) {
        if(record.awarded_xp===null || record.awarded_xp===undefined)return;
        if(label)parent.append(node('p',{},node('strong',{},`Опыт за работу: ${record.awarded_xp} XP`)));
        const award=record.award_breakdown;
        if(!award)return;
        const notes=[];
        if(award.deadline_penalty>0){
            if(award.evaluated_points!==null && award.evaluated_points!==undefined)notes.push(`До применения правил срока: ${award.evaluated_points}`);
            notes.push(`Снижение по сроку: −${award.deadline_penalty} XP`);
        }
        if(award.xp_booster>0)notes.push(`Бонус опыта: +${award.xp_booster} XP`);
        if(notes.length)parent.append(node('p',{class:'text-muted small'},notes.join(' · ')));
    }
    async function showLegacyAttempt(item) {
        const selected=++generation;stop();current=null;compareWith=null;ensureAttemptCard({...item,kind:'legacy_attempt'});markSelectedAttempt(item.id);
        results.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем сведения о сдаче…'));
        try {
            const record=await request(base+'/code-attempts/'+item.solution_id);
            if(selected!==generation)return;
            if(Number(record.user_id)!==Number(teacher && !ownMode ? selectedUser.id : root.dataset.viewerId))throw new Error('Эта сдача принадлежит другому ученику. Выберите его в списке.');
            ensureAttemptCard(record);
            const {header}=resultHeader(record);
            const panel=node('section',{class:'gc-execution-result'},header);
            renderAward(record,panel,{label:false});
            if(record.evaluated_points!==null && record.evaluated_points!==undefined)panel.append(node('p',{},`Сохранённая оценка: ${record.evaluated_points}`));
            if(record.text)panel.append(submittedWork({...record,mode:'text'}));
            if(record.comment)panel.append(node('h4',{class:'h6'},'Комментарий к сдаче'),node('pre',{class:'gc-execution-output'},record.comment));
            if(record.source_notice || (record.source_version_unknown && !record.text))panel.append(node('details',{class:'gc-report-provenance'},node('summary',{},'О сохранённой версии кода'),node('p',{},record.source_notice || 'Отдельная версия кода этой сдачи не сохранялась. Доступную версию можно посмотреть отдельно; её проверка могла относиться к другому изменению кода.')));
            if(record.source_submission_id)panel.append(button('Открыть сохранённую версию кода',safe(() => show(record.source_submission_id,{snapshotOf:record}))));
            else if(record.source_url)panel.append(download(record.source_url,'Открыть сохранённую версию кода'));
            if(record.can_grade)panel.append(legacyGrading(record));
            results.replaceChildren(panel);
        } catch(e) {
            if(selected!==generation)return;
            results.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку сдачи',safe(() => showLegacyAttempt(item))));
        }
    }
    function legacyGrading(record){
        const panel=node('details',{class:'gc-inline-grading',open:record.awarded_xp===null},node('summary',{},record.awarded_xp===null?'Оценить работу':'Изменить оценку и комментарий'));
        let mark=record.review_mark ?? '',comment=record.review_comment || '';
        const feedback=node('p',{role:'status',class:'text-danger small'});
        const save=button('Сохранить оценку',safe(async ()=>{
            if(mark===''){feedback.textContent='Укажите оценку.';return;}
            save.disabled=true;feedback.textContent='';
            try{
                await request(record.grade_url,'POST',{mark:Number(mark),comment,review_solution_id:record.solution_id});
                await showLegacyAttempt(record);await loadHistory();
                notice.className='text-muted small';notice.textContent='Оценка и комментарий сохранены';
            }catch(e){feedback.textContent=e.message;}
            finally{save.disabled=false;}
        }),'primary');
        panel.append(node('div',{class:'gc-inline-grading__form'},
            field('Оценка, XP',input(mark,value=>mark=value,{type:'number',min:0,max:record.grading_maximum_points}),`Максимум ${record.grading_maximum_points}. Правила срока применяются при сохранении.`),
            field('Комментарий ученику',textarea(comment,value=>comment=value,{rows:3})),save,feedback));
        return panel;
    }
    async function openAttempt(item) {
        if(item.kind==='legacy_attempt')return showLegacyAttempt(item);
        return show(item.id);
    }
    async function show(id,options={}) {
        const selected = ++generation;
        stop();current=null;compareWith=options.snapshotOf ? null : compareWith;
        if(!options.snapshotOf){ensureAttemptCard({id,kind:'submission'});markSelectedAttempt(id);}
        results.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем выбранное решение…'));
        let record;
        try {record = await request('/insider/code-submissions/'+id+'?test_data=metadata');} catch(e) {
            if(selected!==generation)return;
            results.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку решения',safe(() => show(id,options))));return;
        }
        if (selected !== generation) return;
        const expectedUser=teacher && !ownMode ? selectedUser.id : root.dataset.viewerId;
        if((root.dataset.taskId && Number(record.task_id)!==Number(root.dataset.taskId)) || (root.dataset.courseId && Number(record.course_id)!==Number(root.dataset.courseId)) || Number(record.user_id)!==Number(expectedUser)){
            results.replaceChildren(node('p',{class:'text-danger'},'Эта сдача относится к другой задаче или ученику. Откройте её со страницы соответствующей задачи.'));return;
        }
        const sourceOnly=!!record.source_version_unknown || !!options.snapshotOf;
        if(!options.snapshotOf)ensureAttemptCard(record);
        if (compareWith && compareWith.id !== id) {
            const target = compareWith; compareWith = null;
            const value = await request(`/insider/code-submissions/${target.id}/compare?with=${id}`);
            const {diffLines} = await import('./diff');
            if(selected!==generation)return;
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
        const {header,terminal}=resultHeader(record,{sourceOnly});
        const menu=resultActions();menu.add(button('Обновить результат',safe(() => show(id,options))));
        const body = node('section',{class:'gc-execution-result'},header);
        const contextBar=node('div',{class:'gc-result-context-bar'},menu.root);
        if(!terminal)body.append(status);
        if(options.snapshotOf)body.prepend(button('Вернуться к выбранной сдаче',safe(() => showLegacyAttempt(options.snapshotOf))));
        if(sourceOnly)body.append(node('details',{class:'gc-report-provenance'},node('summary',{},'О сохранённой версии'),node('p',{},'Это доступная версия кода и результат её проверки. Связь этой версии с конкретной сдачей неизвестна.')));
        if (!sourceOnly)renderAward(record,body);
        body.append(contextBar,submittedWork(record));
        if (record.repository?.commit) body.append(node('p',{},`GitHub · ${record.repository.commit}`, ' ',download(`/insider/code-submissions/${id}/repository/archive`,'Скачать репозиторий')));
        if (record.comment && !record.report?.legacy) body.append(node('pre',{class:'gc-execution-output'},record.comment));
        renderReport(record.report,body,id);
        if(record.text_report_available)body.append(textReport(record));
        if (teacher && record.checker_diagnostics) body.append(node('details',{},node('summary',{},'Диагностика проверяющего модуля'),node('pre',{class:'gc-execution-output'},JSON.stringify(record.checker_diagnostics,null,2))));
        if (teacher && record.legacy) body.append(currentTests());
        if (record.legacy) {
            if (record.legacy.requires_conversion || record.legacy.warnings.length) body.append(node('details',{class:'gc-report-provenance'},node('summary',{},'О материалах работы'),node('p',{},'Некоторые материалы этой сдачи нельзя показать в текущем формате. Доступные материалы приведены выше.')));
            if (record.legacy.commit_unknown) body.append(node('p',{class:'text-muted small'},'Коммит этой версии GitHub и оригинальный архив не сохранены.'));
        }
        if (!teacher || ownMode) {
            menu.add(button(record.is_public ? 'Закрыть публичный доступ' : 'Поделиться решением',safe(async () => {
                if (!record.is_public && !confirm('Опубликовать отправленный текст и все файлы? Их сможет скачать любой, у кого есть ссылка. Тесты и критерии остаются закрытыми.')) return;
                await request(`/insider/code-submissions/${id}/flags`,'PUT',{is_public:!record.is_public}); await show(id);
            })));
            if (record.is_public) body.append(download(`/shared/code-submissions/${id}`,'Публичная ссылка'));
            if (!teacher && record.review_request?.requested) body.append(node('p',{class:'text-muted'},record.review_request.message));
            else if (!teacher && record.review_request?.available) {
                let comment = '';
                const review = node('details',{},node('summary',{},'Попросить преподавателя проверить'),field('Что нужно проверить',textarea('',value => {comment = value;},{minlength:10,maxlength:1000})),button('Отправить запрос',safe(async () => {
                    await request(`/insider/code-submissions/${id}/review-request`,'POST',{comment}); await show(id);
                })));
                body.append(review);
            }
        }
        if(!sourceOnly)menu.add(button('Сравнить с другой попыткой',safe(async () => {
            compareWith = record;notice.className='alert alert-info';notice.textContent='Выберите другую сохранённую попытку этого ученика в списке сдач.';history.scrollIntoView({block:'nearest',behavior:'instant'});
        })));
        if (teacher && !sourceOnly) {
            if (record.review_request?.requested) body.append(node('p',{class:'alert alert-info'},'Запрос ученика: '+record.review_request.comment));
            const viewed=button(record.teacher_viewed ? 'Отметить непросмотренным' : 'Отметить просмотренным',safe(async () => {await request(`/insider/code-submissions/${id}/flags`,'PUT',{teacher_viewed:!record.teacher_viewed}); await show(id); await loadHistory();}));
            if(record.teacher_viewed)menu.add(viewed);else{viewed.classList.add('btn-sm');contextBar.prepend(viewed);}
            if (['completed','error','imported_pending'].includes(record.state)) menu.add(button('Оценить вручную',safe(async () => {await request(`/insider/code-submissions/${id}/manual-review`,'POST',{}); await show(id);await loadHistory();})));
            const latest = node('input',{type:'checkbox',class:'form-check-input'});
            const recheck=node('section',{class:'gc-inline-grading',hidden:true},node('h4',{class:'h6'},'Перепроверка решения'),field('По текущим настройкам задачи',latest),actions(button('Запустить перепроверку',safe(async () => {await request(`/insider/code-submissions/${id}/recheck`,'POST',{latest_configuration:latest.checked}); await show(id);await loadHistory();}),'primary'),button('Отмена',()=>recheck.hidden=true)));
            if(terminal){body.append(recheck);menu.add(button('Перепроверить решение',()=>{recheck.hidden=false;recheck.scrollIntoView({block:'nearest',behavior:'instant'});}));}
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
            record.runs.forEach(run => {
                const detail=node('details',{},node('summary',{},`${new Date(run.created_at).toLocaleString()} · ${phases[run.state] || run.state}`));
                const result=node('div');detail.append(result);
                let loaded=false,loading=false;
                const load=async () => {
                    if(loaded || loading)return;
                    loading=true;result.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем проверку…'));
                    try {
                        const value=await request(`/insider/code-submissions/${id}/runs/${run.id}?test_data=metadata`);
                        result.replaceChildren(node('pre',{class:'gc-execution-output'},value.comment || 'Без комментария'));
                        for(const file of value.files || [])result.append(node('p',{},download(file.download_url,file.name)));
                        if(value.report)renderReport(value.report,result,id);
                        if(value.text_report_available)result.append(textReport(value));
                        loaded=true;
                    }catch(e){result.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку проверки',safe(load)));}
                    finally{loading=false;}
                };
                detail.addEventListener('toggle',() => {if(detail.open)load();});
                runs.append(detail);
            });
            body.append(runs);
        }
        results.replaceChildren(body);
        if (!['completed','error','awaiting_review','imported_pending'].includes(record.state)) {
            stop = subscribe(record.subscription,event => {status.textContent = `${phases[event.phase] || event.phase}${event.total ? ` · ${event.completed} из ${event.total}` : ''}`;},
                async () => {
                    if (current?.id !== id) return;
                    try {
                        await show(id);
                        await loadHistory();
                        if ((!teacher || ownMode) && current?.id === id && current.awarded_xp !== null) document.dispatchEvent(new CustomEvent('gc:task-checked', {detail:{taskId:current.task_id,mark:current.awarded_xp}}));
                    } catch (e) {error(e);}
                }, () => {status.textContent = 'Связь прервана. Восстанавливаем подключение…';},
                () => request(`/insider/code-submissions/${id}/subscription`));
        }
    }
    async function loadHistory(append = false) {
        if(teacher && !ownMode && !selectedUser)return [];
        const requestGeneration=++historyGeneration;
        const requestedPage=append ? page+1 : 1;
        const query = new URLSearchParams({page:String(requestedPage)});
        if(teacher && ownMode)query.set('user_id',config?.viewer_user_id || root.dataset.viewerId);
        else if(teacher && selectedUser)query.set('user_id',selectedUser.id);
        if(!append && !attemptItems.length)history.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем сдачи…'));
        let value;
        try {value = await request(base+'/code-submissions?'+query);} catch(e) {
            if(requestGeneration!==historyGeneration)return [];
            if(!append && !attemptItems.length)history.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку списка',safe(() => loadHistory())));
            throw e;
        }
        if(requestGeneration!==historyGeneration)return [];
        page=requestedPage;
        const selectedSummary=attemptItems.find(item=>String(item.id)===String(activeAttempt));
        if(!append)attemptItems=[];
        const ids=new Set(attemptItems.map(item=>String(item.id)));attemptItems.push(...value.items.filter(item=>!ids.has(String(item.id))));
        if(selectedSummary&&!attemptItems.some(item=>String(item.id)===String(activeAttempt)))attemptItems.push(selectedSummary);
        attemptItems.sort((a,b)=>(Date.parse(b.created_at)||0)-(Date.parse(a.created_at)||0));
        if(teacher && !ownMode && !selectedUser?.name && value.items[0]?.user_name){selectedUser.name=value.items[0].user_name;renderToolbar();}
        const heading=node('div',{class:'gc-attempt-history__heading'});
        if(value.total_attempts!==undefined)heading.append(node('span',{class:'text-muted small'},`Всего сдач: ${value.total_attempts}`));
        if(value.best_attempt)heading.append(button('Лучшая сдача',safe(() => openAttempt(value.best_attempt))));
        heading.append(button('Обновить список',safe(() => loadHistory())));
        const list=node('ol',{class:'gc-attempt-list'},attemptItems.map(attemptCard));
        const footer=node('div',{class:'gc-attempt-history__footer'});
        if(!attemptItems.length)footer.append(node('p',{class:'text-muted mb-0'},ownMode ? 'Ваших решений пока нет. Отправьте работу через форму выше.' : 'Сохранённых сдач пока нет.'));
        if(value.has_more){const more=button('Показать ещё сдачи',safe(async () => {
            more.disabled=true;
            try{await loadHistory(true);}finally{more.disabled=false;}
        }));footer.append(more);}
        const focusedAttempt=history.contains(document.activeElement) ? document.activeElement.closest('[data-attempt-id]')?.dataset.attemptId : null;
        results.remove();history.replaceChildren(heading,list,footer);attemptList=list;
        if(activeAttempt)markSelectedAttempt(activeAttempt);else{results.replaceChildren();browser.append(results);}
        if(focusedAttempt)Array.from(history.querySelectorAll('[data-attempt-id]')).find(item=>item.dataset.attemptId===focusedAttempt)?.focus({preventScroll:true});
        return value.items;
    }
    const activate=()=>{if(ownMode || selectedUser || !teacher)writeContext(activeAttempt);};
    const controller={selectStudent,startOwnSubmission,openAttemptById,activate,context:async attempt=>{if(attempt && activeAttempt!==attempt)await openAttemptById(attempt);else activate();}};
    async function initializeStudent(){
        let records;
        if(root.hasAttribute('data-history-only'))records=await loadHistory();
        else{
            [,records]=await Promise.all([settings(),loadHistory()]);
            if(root.dataset.blocked!=='true' && !config.requires_conversion){
                if(!draft){setDraft(await request(base+'/code-draft'));renderComposer();}
            }else if(!content.children.length)content.append(node('p',{class:'alert alert-warning'},config.requires_conversion ? 'Преподавателю нужно настроить проверку перед сдачей.' : 'Новые сдачи этой задачи заблокированы.'));
            await attachments();
        }
        if(root.dataset.submission || activeAttempt)await openAttemptById(activeAttempt || root.dataset.submission);
        else if(records[0])await openAttempt(records[0]);
    }
    function initializationError(e){
        error(e);
        notice.append(button('Повторить загрузку',async()=>{
            try{
                if(teacher){if(ownMode)await startOwnSubmission();else if(selectedUser)await selectStudent(selectedUser);}
                else await initializeStudent();
                notice.replaceChildren();
            }catch(e){initializationError(e);}
        }));
    }
    try {
        if(teacher){
            renderToolbar();content.hidden=true;attachmentsArea.hidden=true;
            if(root.dataset.user && root.dataset.user===root.dataset.viewerId)await startOwnSubmission({attempt:root.dataset.submission});
            else if(root.dataset.user)await selectStudent(selectedUser,{attempt:root.dataset.submission});
        }else await initializeStudent();
    } catch(e){initializationError(e);}
    return controller;
}
