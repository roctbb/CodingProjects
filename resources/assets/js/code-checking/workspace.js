import {node, button, field, input, textarea, select, actions, phases, verdicts, request, download} from './ui';
import {subscribe} from './progress';
import {resultHeader,submittedWork,resultActions} from './result-view';
import {submissionGroups,submissionMode,uploadDescription,executionPhase,draftFilePayload} from './composer-options';

export async function workspace(root) {
    const base = root.dataset.endpoint;
    const teacher = root.dataset.teacher === 'true';
    const inlineReview = root.hasAttribute('data-execution-inline-review');
    let config, draft, files = [], uploads = [], dirty = false, editing, stop = () => {}, page = 1, current, requestId, generation = 0;
    let saving = false, compareWith = null, settingsPromise, historyGeneration = 0, ownMode = false;
    let selectedUser = root.dataset.user ? {id:root.dataset.user,name:''} : null;
    let attemptItems = [], activeAttempt = null, attemptList, composerVisible=true;
    const submittedOrder=[];
    const rewardedRuns=new Set();
    const toolbar = node('div',{class:'gc-execution-toolbar'});
    const notice = node('div', {role:'status', 'aria-live':'polite'});
    const attachmentsArea = node('div');
    const content = node('div');
    const results = node('div');
    const history = node('div');
    const browser = node('div',{class:'gc-execution-browser'},history,results);
    root.replaceChildren(toolbar,notice,browser,attachmentsArea,content);
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
    const markDirty = () => {dirty = true; requestId = null; notice.className = 'text-muted small'; notice.textContent = 'Решение ещё не отправлено';};
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
        if(ownMode){toolbar.replaceChildren();return;}
        const title = selectedUser?.name || 'Сдачи ученика';
        const heading = node('div',{},node('h3',{class:'h6 mb-0',tabIndex:-1},title));
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
    async function selectStudent(student,{attempt=null}={}) {
        generation++;historyGeneration++;stop();current=null;activeAttempt=null;compareWith=null;
        selectedUser=student;ownMode=false;attemptItems=[];attemptList=null;
        content.hidden=true;attachmentsArea.hidden=true;results.replaceChildren();notice.replaceChildren();renderToolbar();
        try {
            await loadHistory();
            if(selectedUser===student && !ownMode && attempt)await openAttemptById(attempt);
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
            if(ownMode && attempt)await openAttemptById(attempt);
        } catch(e) {content.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить',safe(startOwnSubmission)));}
    }

    function setDraft(value) {
        draft = value;
        if (value.revision === 0 && config.submission_modes.includes('files')) draft.mode = 'files';
        files = value.files.map((file,index) => ({...file, index})).filter(file => !file.is_answer);
        uploads = []; dirty = false;
        if(!config.allowed_languages.includes(draft.language)){draft.language=config.allowed_languages[0];dirty=true;}
        if(!config.submission_modes.includes(draft.mode)){draft.mode=config.submission_modes[0];dirty=true;}
    }
    function payload() {
        const kind=composerKind(),sourceFiles=kind==='repository'?[]:files;
        const selectedUploads=['files','text'].includes(kind)?uploads:[];
        const mode=submissionMode(config,kind,sourceFiles.length+selectedUploads.length);
        const body = {revision:draft.revision, mode, language:draft.language, text:draft.text,
            draft_revision:draft.revision, ...draftFilePayload(sourceFiles,draft.revision),
            repository_url:draft.repository?.url || '', repository_ref:draft.repository?.ref || null};
        if (!selectedUploads.length) return body;
        const data = new FormData();
        for (const [key,value] of Object.entries(body)) {
            if (Array.isArray(value)) value.forEach((row,index) => {
                if (typeof row === 'object') Object.entries(row).forEach(([k,v]) => data.append(`${key}[${index}][${k}]`,v));
                else data.append(`${key}[${index}]`,row);
            });
            else if (value !== null) data.append(key, value);
        }
        selectedUploads.forEach((file,index) => {data.append('files[]',file); data.append(`file_paths[${index}]`,file.name);});
        return data;
    }
    function composerKind(){
        const groups=submissionGroups(config);
        if(config.submission_modes.includes('text') && !groups.includes('code') && !groups.includes('repository'))return 'text';
        const selected=['code','project'].includes(draft.mode)?'code':draft.mode;
        return groups.includes(selected)?selected:groups[0] || 'text';
    }
    function showComposer(){
        generation++;stop();current=null;markSelectedAttempt(null);
        composerVisible=true;requestId=null;notice.replaceChildren();notice.className='';content.hidden=false;renderComposer();
        content.scrollIntoView({block:'nearest',behavior:'smooth'});
    }
    async function save() {
        if (saving) return;
        saving = true; content.setAttribute('inert','');
        try {
            const value = await request(base+'/code-draft','PUT',payload());
            setDraft(value); renderComposer(); notice.className = 'text-muted small'; notice.textContent = 'Решение сохранено, но ещё не отправлено';
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
            notice.replaceChildren();notice.className='';
            if(generation===submittedGeneration){composerVisible=false;content.hidden=true;if(!submittedOrder.includes(record.id))submittedOrder.push(record.id);await loadHistory();if(generation===submittedGeneration)await show(record.id);}
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
        const kind=composerKind(),groups=submissionGroups(config),allowed=config.submission_modes;
        content.hidden=!composerVisible;
        const composerHeading=node('div',{class:'gc-composer-heading'},node('h3',{class:'h6 mb-0'},'Новое решение'));
        const body=node('section',{class:'gc-execution-composer gc-attempt-card','aria-label':'Новое решение'},composerHeading);
        const form=node('div',{class:'gc-composer-body'});body.append(form);
        if(kind!=='text' && groups.length>1){
            const labels={files:['fa-file-arrow-up','Файлы'],code:['fa-code','Код'],repository:['fa-code-branch','Репозиторий']};
            const choices=node('div',{class:'gc-segmented-tabs gc-submission-modes',role:'group','aria-label':'Способ сдачи'});
            for(const group of groups){
                const choice=button('',()=>{draft.mode=group==='code'?(allowed.includes('code')?'code':'project'):group;markDirty();renderComposer();});
                choice.className='nav-link'+(group===kind?' active':'');choice.setAttribute('aria-pressed',String(group===kind));
                choice.append(node('i',{class:'fas '+labels[group][0],'aria-hidden':'true'}),' '+labels[group][1]);choices.append(choice);
            }
            form.append(choices);
        }
        if(kind==='code' || kind==='repository'){
            const languageNames={cpp:'C++',python:'Python',py:'Python',javascript:'JavaScript',text:'Текст'};
            const languages=config.allowed_languages.filter(value=>value!=='text');
            if(languages.length>1)form.append(field('Язык',select(draft.language,Object.fromEntries(languages.map(value=>[value,languageNames[value] || value])),value=>{draft.language=value;markDirty();renderComposer();})));
            else if(languages.length)composerHeading.append(node('span',{class:'gc-composer-language text-muted small'},node('i',{class:'fas fa-code','aria-hidden':'true'}),' '+(languageNames[languages[0]] || languages[0])));
        }
        if(kind==='repository'){
            draft.repository ||= {};
            form.append(field('Ссылка на репозиторий',input(draft.repository.url,value=>{draft.repository.url=value;markDirty();},{type:'url',placeholder:'https://github.com/username/project'})),
                field('Ветка, тег или коммит',input(draft.repository.ref,value=>{draft.repository.ref=value;markDirty();},{placeholder:'По умолчанию — основная ветка'})));
        }else if(kind==='code'){
            if(!files.length)files.push({path:config.entrypoint || (draft.language==='cpp'?'main.cpp':'main.py'),content:''});
            const editable=files;
            const editors=node('div',{class:'gc-composer-editors'}),tabs=node('div',{class:'gc-source-tabs',role:'tablist','aria-label':'Файлы кода'});
            let selected=editable[0];
            const panes=[];
            const activate=file=>{selected=file;for(const entry of panes){entry.pane.hidden=entry.file!==file;entry.tab.setAttribute('aria-selected',String(entry.file===file));entry.tab.tabIndex=entry.file===file?0:-1;entry.tab.classList.toggle('is-active',entry.file===file);}};
            tabs.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight'].includes(event.key)||event.target.getAttribute('role')!=='tab')return;event.preventDefault();activate(editable[(editable.indexOf(selected)+(event.key==='ArrowRight'?1:editable.length-1))%editable.length]);tabs.querySelector('[aria-selected="true"]')?.focus();});
            for(const file of editable){
                const host=node('div',{class:'gc-code-editor'}),pane=node('div',editable.length>1?{role:'tabpanel'}:{});
                const name=input(file.path,value=>{file.path=value;file.changed=true;tab.textContent=value || 'Новый файл';markDirty();},{placeholder:'main.py','aria-label':'Имя файла кода'});
                const controls=actions(field('Имя файла',name));
                if(editable.length>1)controls.append(button('Удалить файл',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}));
                if(file.content===undefined){
                    if(editable.length>1)pane.append(button('Удалить файл',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}));
                    pane.append(node('p',{class:'text-muted small'},'Исходник сохранён и будет отправлен без изменений. Для редактирования большого файла используйте свой редактор.'),file.download_url?download(file.download_url,'Скачать '+file.path):null,
                        actions(button('Заменить код',()=>{if(!confirm('Заменить сохранённый исходник новым кодом? Сначала скачайте файл, если он нужен.'))return;file.content='';file.changed=true;markDirty();renderComposer();})));
                }else{if(editable.length>1)pane.append(controls);pane.append(host);}
                const tab=button(file.path || 'Новый файл',()=>activate(file));tab.className='gc-source-tab';tab.setAttribute('role','tab');if(editable.length>1){tab.id=root.id+'-source-tab-'+editable.indexOf(file);pane.id=tab.id+'-panel';tab.setAttribute('aria-controls',pane.id);pane.setAttribute('aria-labelledby',tab.id);}
                tabs.append(tab);panes.push({file,pane,tab});editors.append(pane);
                if(file.content!==undefined)import('./editor').then(({editor})=>{if(!host.isConnected)return;const view=editor(host,file.content,draft.language,value=>{file.content=value;file.changed=true;markDirty();});const previous=editing;editing={destroy:()=>{previous?.destroy();view.destroy();}};}).catch(error);
            }
            if(allowed.includes('project') && editable.length<config.max_files){const add=button('+ Файл',()=>{files.push({path:`file${editable.length+1}.${draft.language==='cpp'?'cpp':'py'}`,content:''});markDirty();renderComposer();const buttons=content.querySelectorAll('.gc-source-tab');buttons[buttons.length-1]?.click();});add.classList.add('btn-sm');if(editable.length>1)tabs.append(add);else composerHeading.append(add);}
            if(editable.length>1)form.append(tabs);form.append(editors);activate(selected);
        }else{
            if(kind==='text' || kind==='files' && allowed.includes('text'))form.append(field('Ответ',textarea(draft.text,value=>{draft.text=value;markDirty();},{rows:8})));
            if(allowed.includes('files')){
                const upload=node('input',{type:'file',class:'form-control',multiple:config.max_files>1,accept:config.allowed_extensions?.map(value=>'.'+value.replace(/^\./,'')).join(',') || '',onchange:event=>{
                    const selected=[...event.target.files];
                    if(selected.some(file=>file.size>config.max_file_bytes)){error(new Error('Один из файлов превышает допустимый размер.'));return;}
                    const names=new Set(selected.map(file=>file.name)),existing=files.filter(file=>!names.has(file.path)),pending=uploads.filter(file=>!names.has(file.name));
                    if(existing.length+pending.length+selected.length>config.max_files){error(new Error(`Можно отправить не больше ${config.max_files} файлов.`));return;}
                    files=existing;uploads=[...pending,...selected];markDirty();renderComposer();
                }});
                form.append(field(uploadDescription(config),upload,`${config.max_files===1?'Один файл':`До ${config.max_files} файлов`} · суммарно до ${Math.round(config.max_total_bytes/1048576)} МБ.`));
                if(files.length || uploads.length)form.append(node('ul',{class:'gc-upload-list'},files.map(file=>node('li',{},file.download_url?download(file.download_url,file.path):file.path,' ',button('Убрать',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}))),uploads.map(file=>node('li',{},file.name,' ',button('Убрать',()=>{uploads=uploads.filter(value=>value!==file);markDirty();renderComposer();})))));
            }
        }
        form.append(actions(button('Отправить решение',safe(submit),'primary')));content.replaceChildren(body);
    }
    function artifact(value) {
        if (!value) return node('span',{class:'text-muted'},'Нет данных');
        return value.type === 'inline' ? node('pre',{class:'gc-execution-output'},value.text || '(пусто)') : node('div',{},download(value.download_url,`Скачать (${value.bytes} байт)`),value.truncated ? ' · вывод сокращён' : '');
    }
    let reportSequence = 0;
    function renderReport(report, parent, submissionId,{savedSource=false}={}) {
        if (!report) return;
        for (const note of report.notes || []) parent.append(node('p',{},note));
        if (report.notice && !savedSource) parent.append(report.legacy
            ? node('details',{class:'gc-report-provenance'},node('summary',{},'О данных исторической проверки'),node('p',{class:'text-muted small'},report.notice))
            : node('p',{class:'alert alert-info'},report.notice));
        const section = node('section',{class:'gc-test-report','aria-label':'Результаты тестов'});
        section.append(node('div',{class:'gc-test-report__heading'},node('h4',{class:'h6 mb-0'},savedSource?'Проверка сохранённого кода':'Результаты тестов'),
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
                        await show(current.id,current.snapshotOf?{snapshotOf:current.snapshotOf}:{});
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
        let score=record.state==='error'?'Ошибка проверки · без оценки':record.kind==='legacy_attempt'?record.awarded_xp!==null&&record.awarded_xp!==undefined?`${record.awarded_xp} XP`:'Ожидает оценки':record.points!==null&&record.points!==undefined?`${record.points}${record.maximum_points&&!record.source_version_unknown?' / '+record.maximum_points:''} баллов`:phases[record.state] || 'Загружаем результат…';
        if(record.kind!=='legacy_attempt' && record.state!=='error' && record.awarded_xp!==null && record.awarded_xp!==undefined && record.awarded_xp!==record.points)score+=` · ${record.awarded_xp} XP`;
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
        if(!teacher || ownMode){composerVisible=false;content.hidden=true;}
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
            if(record.source_submission_id){await show(record.source_submission_id,{snapshotOf:record});return;}
            const {header}=resultHeader(record,{showScore:false,showSourceDate:false});
            const panel=node('section',{class:'gc-execution-result'},header);
            renderAward(record,panel,{label:false});
            if(record.text)panel.append(submittedWork({...record,mode:'text'}));
            if(!record.text)panel.append(node('p',{class:'text-muted small'},'Код этой сдачи не сохранился отдельно.'));
            if(record.can_grade)panel.append(legacyGrading(record));
            else if(record.comment)panel.append(feedbackDisplay(record.comment));
            if(!teacher || ownMode)panel.append(button('Ещё одно решение',showComposer,'primary'));
            results.replaceChildren(panel);
        } catch(e) {
            if(selected!==generation)return;
            results.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку сдачи',safe(() => showLegacyAttempt(item))));
        }
    }
    function feedbackDisplay(comment){
        return node('div',{class:'gc-grading-feedback'},node('h4',{class:'h6'},'Комментарий'),node('pre',{class:'gc-execution-output'},comment));
    }
    function inlineGrading({comment='',mark='',maximum,ungraded=false,hint,onSave}){
        const display=node('div',{class:'gc-inline-grade__display'});
        if(comment)display.append(feedbackDisplay(comment));
        const editor=node('div',{class:'gc-inline-grade__editor',hidden:true});
        const panel=node('section',{class:'gc-inline-grade','aria-label':'Оценка и комментарий'},display,editor);
        const edit=button(ungraded?'Оценить работу':'Изменить оценку',()=>{
            let editedMark=mark ?? '',editedComment=comment;
            const feedback=node('p',{role:'status',class:'text-danger small mb-0'});
            const save=button('Сохранить оценку',safe(async()=>{
                if(editedMark===''){feedback.textContent='Укажите оценку.';return;}
                save.disabled=true;editor.setAttribute('inert','');feedback.textContent='';
                try{await onSave(Number(editedMark),editedComment);}
                catch(e){feedback.textContent=e.message;}
                finally{save.disabled=false;editor.removeAttribute('inert');}
            }),'primary');
            const cancel=button('Отмена',()=>{editor.hidden=true;editor.replaceChildren();display.hidden=false;edit.focus({preventScroll:true});});
            editor.replaceChildren(field('Оценка',input(editedMark,value=>editedMark=value,{type:'number',min:0,max:maximum}),hint || `Максимум ${maximum} баллов.`),
                field('Комментарий ученику',textarea(editedComment,value=>editedComment=value,{rows:5})),actions(save,cancel),feedback);
            display.hidden=true;editor.hidden=false;editor.querySelector('input')?.focus({preventScroll:true});
        });
        edit.classList.add('btn-sm');display.append(edit);
        return panel;
    }
    function legacyGrading(record,{comment=record.teacher_feedback ? record.review_comment ?? '' : record.review_comment || record.comment || ''}={}){
        return inlineGrading({comment,mark:record.review_mark,maximum:record.grading_maximum_points,ungraded:record.awarded_xp===null,
            hint:`Максимум ${record.grading_maximum_points} XP. Правила срока применяются при сохранении.`,
            onSave:async(mark,comment)=>{
                const savedGeneration=generation;
                await request(record.grade_url,'POST',{mark,comment,review_solution_id:record.solution_id});
                if(savedGeneration!==generation)return;
                await showLegacyAttempt(record);if(activeAttempt!==String(record.id))return;await loadHistory();
                notice.className='text-muted small';notice.textContent='Оценка и комментарий сохранены';
            }});
    }
    async function openAttempt(item) {
        if(!teacher || ownMode){composerVisible=false;content.hidden=true;}
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
        current = record;current.snapshotOf=options.snapshotOf;
        const status = node('div',{role:'status','aria-live':'polite',class:'gc-execution-status'}, phases[record.progress?.phase || record.state] || record.state);
        const {header,terminal}=resultHeader(options.snapshotOf || record,{sourceOnly:sourceOnly&&!options.snapshotOf,showScore:false,showSourceDate:false});
        const menu=resultActions();menu.add(button('Обновить результат',safe(() => show(id,options))));
        const body = node('section',{class:'gc-execution-result'},header);
        const contextBar=node('div',{class:'gc-result-context-bar'},menu.root);
        if(!terminal){header.hidden=true;body.append(status);}
        if(options.snapshotOf)renderAward(options.snapshotOf,body,{label:false});
        else if(!sourceOnly && record.state!=='error')renderAward(record,body,{label:false});
        if(sourceOnly)header.append(node('span',{class:'text-muted small gc-saved-source-info',title:'Это доступная сохранённая версия кода; её точная связь с этой сдачей не зафиксирована. Сохранена '+new Date(record.created_at).toLocaleString('ru-RU')},node('i',{class:'fas fa-circle-info','aria-hidden':'true'}),' Сохранённый код'));
        body.append(contextBar);
        if(record.state==='error'){
            body.append(node('p',{class:'gc-execution-error',role:'alert'},record.execution_error?.message || 'Проверяющий сервис не смог завершить работу. Решение сохранено.'));
            if(record.execution_error?.can_retry && !sourceOnly)body.append(button('Повторить проверку',safe(async()=>{await request(record.execution_error.retry_url,'POST',{});await show(id,options);await loadHistory();}),'primary'));
        }
        body.append(submittedWork(record));
        if (record.repository?.commit) body.append(node('p',{},`GitHub · ${record.repository.commit}`, ' ',download(`/insider/code-submissions/${id}/repository/archive`,'Скачать репозиторий')));
        renderReport(record.report,body,id,{savedSource:!!options.snapshotOf});
        if(record.text_report_available)body.append(textReport(record));
        if (teacher && record.checker_diagnostics) body.append(node('details',{},node('summary',{},'Диагностика проверяющего модуля'),node('pre',{class:'gc-execution-output'},JSON.stringify(record.checker_diagnostics,null,2))));
        if (teacher && record.legacy && record.check_type==='tests') body.append(currentTests());
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
            if (!teacher && !sourceOnly && record.review_request?.requested) body.append(node('p',{class:'text-muted'},record.review_request.message));
            else if (!teacher && !sourceOnly && record.review_request?.available) {
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
            const latest = node('input',{type:'checkbox',class:'form-check-input'});
            const recheck=node('section',{class:'gc-inline-grading',hidden:true},node('h4',{class:'h6'},'Перепроверка решения'),field('По текущим настройкам задачи',latest),actions(button('Запустить перепроверку',safe(async () => {await request(`/insider/code-submissions/${id}/recheck`,'POST',{latest_configuration:latest.checked}); await show(id);await loadHistory();}),'primary'),button('Отмена',()=>recheck.hidden=true)));
            if(terminal && record.state!=='error'){body.append(recheck);menu.add(button('Перепроверить решение',()=>{recheck.hidden=false;recheck.scrollIntoView({block:'nearest',behavior:'instant'});}));}
            if(record.ai_recheck?.available)recheck.append(button('Перепроверить усиленной ИИ-моделью',safe(async()=>{if(!confirm('Запустить повторную проверку усиленной ИИ-моделью?'))return;await request(`/insider/code-submissions/${id}/recheck`,'POST',{stronger_ai:true});await show(id,options);await loadHistory();})));
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
        if(options.snapshotOf?.text && options.snapshotOf.text!==record.text)body.append(submittedWork({...options.snapshotOf,mode:'text'}));
        const target=options.snapshotOf || record;
        const comment=target.teacher_feedback ? target.review_comment ?? target.comment ?? '' : target.review_comment || target.comment || (target.check_type!=='tests' && record.state!=='error'?record.comment:'') || '';
        if(options.snapshotOf?.can_grade)body.append(legacyGrading(options.snapshotOf,{comment}));
        else if(teacher && !sourceOnly && terminal)body.append(inlineGrading({comment,mark:record.points,maximum:record.maximum_points,ungraded:record.state==='awaiting_review',onSave:async(points,comment)=>{
            const savedGeneration=generation;
            if(record.state!=='awaiting_review' || record.check_type!=='manual')await request(`/insider/code-submissions/${id}/manual-review`,'POST',{});
            await request(`/insider/code-submissions/${id}/grade`,'PUT',{points,comment});if(savedGeneration!==generation)return;await show(id,options);if(current?.id!==id)return;await loadHistory();
            notice.className='text-muted small';notice.textContent='Оценка и комментарий сохранены';
        }}));
        else if(comment)body.append(feedbackDisplay(comment));
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
                        result.replaceChildren();
                        if(value.comment)result.append(node('pre',{class:'gc-execution-output'},value.comment));
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
        if(terminal && (!teacher || ownMode) && !root.hasAttribute('data-history-only'))body.append(actions(button('Ещё одно решение',showComposer,'primary')));
        results.replaceChildren(body);
        if (!['completed','error','awaiting_review','imported_pending'].includes(record.state)) {
            const progressBar=node('progress',{class:'gc-execution-progress','aria-label':'Ход проверки тестов'});
            const phaseTitle=node('strong'),phaseHint=node('span',{class:'text-muted small'},'Результат появится здесь автоматически.');
            const phaseSteps=node('div',{class:'gc-execution-phases'},(record.check_type==='gpt'?['preparing','grading','publishing']:['preparing','compiling','testing','publishing'].filter(phase=>phase!=='compiling'||record.language==='cpp')).map(phase=>node('span',{'data-phase':phase},phases[phase])));
            status.replaceChildren(node('i',{class:'fas fa-gear gc-execution-gear','aria-hidden':'true'}),node('div',{},phaseTitle,phaseHint),progressBar,phaseSteps);
            const update=event=>{
                const phase=executionPhase(event);phaseTitle.textContent=phase.label;
                if(phase.total){progressBar.max=phase.total;progressBar.value=phase.completed;}else progressBar.removeAttribute('value');
                phaseSteps.querySelectorAll('[data-phase]').forEach(step=>step.classList.toggle('is-current',step.dataset.phase===event.phase));
            };
            update({...record.progress,phase:record.progress?.phase || record.state});
            const executionId=record.execution_id;
            stop = subscribe(record.subscription,update,
                async event => {
                    if (current?.id !== id || current.execution_id!==executionId || event.execution_id!==executionId) return;
                    try {
                        await show(id,options);
                        await loadHistory();
                        if ((!teacher || ownMode) && current?.id===id && current.execution_id===executionId && current.state==='completed' && current.awarded_xp!==null && !rewardedRuns.has(executionId)){
                            rewardedRuns.add(executionId);
                            document.dispatchEvent(new CustomEvent('gc:task-checked', {detail:{taskId:current.task_id,mark:current.awarded_xp,executionId}}));
                        }
                    } catch (e) {error(e);}
                }, () => {phaseHint.textContent='Связь прервана. Восстанавливаем подключение…';},
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
        attemptItems.sort((a,b)=>{const first=submittedOrder.indexOf(String(a.id)),second=submittedOrder.indexOf(String(b.id));return first>=0&&second>=0?first-second:first>=0?1:second>=0?-1:(Date.parse(b.created_at)||0)-(Date.parse(a.created_at)||0);});
        if(teacher && !ownMode && !selectedUser?.name && value.items[0]?.user_name){selectedUser.name=value.items[0].user_name;renderToolbar();}
        if(!attemptItems.length && (!teacher || ownMode) && !root.hasAttribute('data-history-only')){results.remove();results.replaceChildren();history.replaceChildren();attemptList=null;browser.append(results);return value.items;}
        const heading=node('div',{class:'gc-attempt-history__heading'});
        if(value.total_attempts!==undefined)heading.append(node('span',{class:'text-muted small'},`Всего сдач: ${value.total_attempts}`));
        if(value.best_attempt)heading.append(button('Лучшая сдача',safe(() => openAttempt(value.best_attempt))));
        const list=node('ol',{class:'gc-attempt-list'},attemptItems.map(attemptCard));
        const footer=node('div',{class:'gc-attempt-history__footer'});
        if(!attemptItems.length)footer.append(node('p',{class:'text-muted mb-0'},ownMode ? 'Ваших решений пока нет.' : 'Сохранённых сдач пока нет.'));
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
