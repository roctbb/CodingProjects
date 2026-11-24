import {node, button, iconButton, field, input, textarea, select, actions, phases, verdicts, request as httpRequest, download, openDialog} from './ui';
import {subscribe} from './progress';
import {detailCache} from './detail-cache';
import {gradingPanel,authorshipPanel} from './review-panels';
import {resultHeader,submittedWork,resultActions} from './result-view';
import {submissionGroups,submissionMode,uploadDescription,executionPhase,draftFilePayload} from './composer-options';
import {feedbackPanel} from './feedback';
import {taskChat} from './task-chat';
import {awardPanel} from './award-view';
import {criterionTable, testGroupSummary} from './chat-results';
import {combinedCheck, usesAi} from './authoring-settings';

const attemptTime = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
});
const scorePlural = new Intl.PluralRules('ru-RU');
const attemptMarkers = {
    pending: {icon:'fa-clock', label:'На проверке'},
    technical: {icon:'fa-triangle-exclamation', label:'Ошибка проверки'},
    failed: {icon:'fa-xmark', label:'Не принято'},
    partial: {icon:'fa-circle-half-stroke', label:'Частично'},
    passed: {icon:'fa-check', label:'Полностью'},
    stored: {icon:'fa-clock-rotate-left', label:'Сохранённая сдача'},
};

function attemptOutcome(record) {
    if (record.state === 'error') return 'technical';
    if (record.state === 'imported_pending') return 'stored';
    if (record.state !== 'completed') return 'pending';

    const points = record.evaluated_points ?? record.points ?? record.awarded_xp;
    const maximum = record.maximum_points ?? record.grading_maximum_points;
    if (points == null || !(maximum > 0)) return 'stored';
    if (points <= 0) return 'failed';
    return points >= maximum ? 'passed' : 'partial';
}

export async function workspace(root) {
    const base = root.dataset.endpoint;
    const teacher = root.dataset.teacher === 'true';
    const ownWorkspace = root.hasAttribute('data-execution-own-workspace');
    const inlineReview = root.hasAttribute('data-execution-inline-review');
    const taskHeading = teacher ? null : root.closest('.gc-student-attempt')?.querySelector('.gc-attempt-heading');
    const sheet = root.closest('.gc-task-sheet');
    const taskName = sheet?.querySelector('.gc-task-title-row :is(h1, h2)')?.textContent.trim();
    const taskOutcome = !teacher && sheet?.querySelector('[data-task-outcome]');
    const solutionsStatus = inlineReview || ownWorkspace ? node('p', {class:'gc-help-text', role:'status'}) : null;
    const panelHistory = solutionsStatus ? node('section', {
        id: root.id+'-solutions', class:'gc-task-history gc-chat-solutions', 'aria-label':'Список решений', hidden:true,
    }, solutionsStatus, node('ul', {class:'gc-attempt-list'}), node('button', {
        type:'button', class:'btn btn-outline-secondary mt-3', 'data-task-attempt-more':'', hidden:true,
    }, 'Показать ещё сдачи')) : null;
    const taskHistory = panelHistory || (!teacher ? sheet?.querySelector('[data-task-attempt-history]') : null);
    const taskAttemptList = taskHistory?.querySelector('.gc-attempt-list');
    const moreTaskAttempts = taskHistory?.querySelector('[data-task-attempt-more]');
    const focusedVerdict = taskHeading?.querySelector('[data-attempt-verdict]');
    let config, draft, files = [], uploads = [], dirty = false, editing, stop = () => {}, page = 1, current, requestId, generation = 0;
    let saving = false, submitting = false, savePromise, editVersion=0, autoSaveTimer, settingsPromise, historyGeneration = 0, ownMode = false;
    let selectedUser = root.dataset.user ? {id:root.dataset.user,name:''} : null;
    let attemptItems = [], activeAttempt = null, composerVisible=true, lastResultAttempt=null;
    const submittedOrder=[];
    const rewardedRuns=new Set();
    const gradingDrafts=new Map(),reviewDrafts=new Map(),expandedTests=new Map(),testPages=new Map();
    const details=detailCache((url,signal)=>httpRequest(url,'GET',undefined,{signal}));
    async function request(url,method='GET',body,options){
        const value=await httpRequest(url,method,body,options);
        if(method!=='GET' && !url.split('?')[0].endsWith('/code-draft'))details.clear();
        return value;
    }
    const toolbar = node('div',{class:'gc-execution-toolbar'});
    const notice = node('div', {role:'status', 'aria-live':'polite'});
    const content = node('div',{class:'gc-submission-editor'});
    const results = node('div',{id:root.id+'-result',class:'gc-submission-result',hidden:true});
    const history = node('div',{class:'gc-submission-history'});
    const draftState=node('span',{class:'gc-draft-state',role:'status'});
    const heading=node('div',{class:'gc-submission-heading'},node('h3',{class:'h6 mb-0'},teacher?'Работа ученика':'Моё решение'),draftState);
    const stage=node('div',{class:'gc-submission-stage'},content,results);
    const summary=node('section',{
        class:'gc-result-summary'+(inlineReview ? ' is-review' : !teacher ? ' is-student' : ''),
        'aria-label':'Сдача и результат',hidden:true,
    },toolbar,history);
    const browser = node('section',{class:'gc-execution-browser gc-submission-surface'},summary,stage);
    root.replaceChildren(notice,browser);
    const teacherOwnChat = teacher && root.dataset.user === root.dataset.viewerId;
    const personalChat = !root.hasAttribute('data-quiz-review') && (!teacher || inlineReview || !!root.dataset.user);
    const legacyForm = (!teacher || teacherOwnChat) && root.hasAttribute('data-history-only') ?
        root.closest('.gc-task-own-workspace, .gc-task-answer')?.querySelector('.step-submit-form')?.closest('.row') : null;
    let workspaceDialog, dialogHeading, previousAttempt;
    function styleWorkDialog(dialog, view) {
        dialog.classList.add('gc-work-dialog');
        dialog.dataset.view = view;
        const header = dialog.firstElementChild;
        const title = header.firstElementChild;
        const heading = node('div', {class: 'gc-work-dialog__heading'});
        header.prepend(heading);
        heading.append(title);
        if (taskName) heading.append(node('p', {}, taskName));
    }
    function updateWorkDialogView(view) {
        if (teacher && !ownWorkspace || !workspaceDialog?.open || workspaceDialog.dataset.view === view) return;
        workspaceDialog.dataset.view = view;
        dialogHeading.textContent = view === 'compose' ? 'Отправить решение' : 'Отправленное решение';
        workspaceDialog.scrollTop = 0;
    }
    function openWorkspace(title, view = 'result') {
        dialogHeading.textContent=title;
        workspaceDialog.dataset.view = view;
        browser.before(notice);
        const verdict=verdictHost?.querySelector(':scope > header');
        if(verdict)history.after(verdict);
        root.querySelectorAll('[data-chat-source-preview]').forEach(el=>el.dispatchEvent(new Event('gc:preview-open')));

        if (!workspaceDialog.open) {
            workspaceDialog.showModal();
            workspaceDialog.scrollTop = 0;
            revealAttemptStep();
        }
    }
    async function viewDetails(attempt, check) {
        openWorkspace(teacher && !ownWorkspace ? 'Проверка и оценка' : 'Отправленное решение');
        if(typeof attempt==='string')await openAttemptById(attempt, check);
    }
    let draftSource = null, switchingDraft = false;
    const draftQuery = source => source ? '?from_submission='+encodeURIComponent(source) : '';
    const draftUrl = () => base+'/code-draft'+draftQuery(draftSource);

    async function editDraft(source = null) {
        if (switchingDraft || submitting) return;
        switchingDraft = true;
        try {
            if (draftSource !== source) {
                if (savePromise) await savePromise;
                if (dirty) await save();
                const value = await request(base+'/code-draft'+draftQuery(source), source ? 'POST' : 'GET');
                draftSource = source;
                setDraft(value);
                requestId = null;
                draftState.textContent = '';
                renderComposer();
            }
            showComposer();
        } catch (e) {
            error(e);
        } finally {
            switchingDraft = false;
        }
    }

    function openEditor() {
        if(legacyForm) {
            const body = node('div', {class: 'gc-work-dialog__content'}, legacyForm);
            const dialog=openDialog('Отправить решение',body);
            styleWorkDialog(dialog, 'compose');
            legacyForm.hidden=false;
            dialog.addEventListener('close',()=>{legacyForm.hidden=true;root.after(legacyForm);},{once:true});
        }else{
            previousAttempt=activeAttempt;
            openWorkspace('Отправить решение', 'compose');editDraft();
        }
    }
    function chatDialog(title,loadContent,{wide=true,studentWork=false}={}) {
        const body=node('div',{class:studentWork ? 'gc-work-dialog__content' : null},node('p',{role:'status'},'Загружаем…'));
        const dialog=openDialog(title,body);
        if(wide)dialog.classList.add('gc-source-dialog');
        if(studentWork)styleWorkDialog(dialog, 'result');
        const render=async()=>{try{const content=await loadContent(render,dialog);if(dialog.open)body.replaceChildren(content);}
            catch(e){if(dialog.open)body.replaceChildren(node('p',{role:'alert'},e.message),button('Повторить',render));}};
        render();
    }
    async function chatSubmission(attempt) {
        const data=attempt.startsWith('solution-')
            ? await request(base+'/code-attempts/'+attempt.slice(9))
            : await request('/insider/code-submissions/'+attempt+'?test_data=metadata');
        const source=data.source_submission_id ? await request('/insider/code-submissions/'+data.source_submission_id+'?test_data=metadata') : data;
        return {data,source};
    }
    function openSource(attempt) {
        chatDialog('Отправленное решение',async()=>{
            const {data,source}=await chatSubmission(attempt);
            const repository=source.repository;
            const preview=repository?.url && !source.files?.length ? node('div') :
                submittedWork(data.kind==='legacy_attempt' && !data.source_submission_id ? {...data,mode:'text'} : source);
            if(data.source_notice)preview.prepend(node('p',{class:'gc-help-note'},data.source_notice));
            if(repository?.url)preview.append(node('p',{},download(repository.url,repository.url)));
            if(repository?.commit)preview.append(node('p',{},node('code',{},repository.commit),' ',
                download('/insider/code-submissions/'+source.id+'/repository/archive','Скачать репозиторий')));
            return preview;
        }, {studentWork: true});
    }
    async function gradeChatSubmission(attempt) {
        details.clear();
        await viewDetails(attempt);
        results.querySelector('.gc-inline-grade input')?.focus();
    }
    function openChatReview(event) {
        chatDialog('Перепроверить решение', async (reload,dialog) => {
            const {data,source}=await chatSubmission(event.attempt);
            const snapshot=data.kind==='legacy_attempt' ? data : null;
            const active=source.execution_id && !['completed','error','imported_pending','awaiting_review'].includes(source.state);
            if (active || (event.run && event.run!==source.execution_id)) {
                chat.refresh();
                return node('p',{role:'status'},active ? 'Эта посылка уже на проверке.' : 'В чате уже есть более свежая проверка этой посылки.');
            }
            const methods={manual:'Оценить вручную'};
            const type=source.recheck_type || source.check_type;
            const automatic=source.execution_id && source.state!=='error' && (!source.source_version_unknown || snapshot);
            if (automatic && type==='tests') methods.normal='Повторить автотесты';
            if (automatic && type===combinedCheck) methods.normal='Повторить тесты и проверку ИИ';
            if (automatic && source.ai_recheck?.available) methods.strong='Усиленная модель';
            const method=select('manual',methods,update);
            const note=textarea('',()=>{},{rows:3,maxLength:4000,placeholder:'Что проверить внимательнее?'});
            const noteField=field('Комментарий к перепроверке',note);
            const status=node('p',{class:'gc-help-text',role:'status'});
            const submit=button('Оценить вручную',async()=>{
                if(method.value==='manual') {
                    dialog.close();
                    await gradeChatSubmission(event.attempt);
                    return;
                }
                submit.disabled=true;
                method.disabled=true;
                try {
                    await startRecheck(source,snapshot,{stronger_ai:method.value==='strong',comment:note.value});
                    dialog.close();
                    chat.refresh();
                } catch(error) {
                    status.textContent=error.message;
                    submit.disabled=false;
                    method.disabled=false;
                }
            },'primary');
            function update() {
                const manual=method.value==='manual';
                noteField.hidden=manual;
                submit.textContent=manual ? 'Оценить вручную' : 'Запустить перепроверку';
            }
            update();
            return node('div',{},node('p',{class:'gc-help-text'},event.reference),
                data.source_notice ? node('p',{class:'gc-help-note'},data.source_notice) : null,
                Object.keys(methods).length>1 ? field('Способ проверки',method) : null,
                usesAi(type) && !methods.strong && source.ai_recheck?.reason ? node('p',{class:'gc-help-text'},source.ai_recheck.reason) : null,
                noteField,status,actions(submit));
        }, {wide: false});
    }
    function openReport(event) {
        chatDialog('Журнал тестирования',async reload=>{
            const path='/insider/code-submissions/'+event.source+'/runs/'+event.run;
            const run=await request(path+'?test_data=metadata');
            const body=node('div');
            if(run.report)renderReport(run.report,body,event.source,{afterPurchase:reload});
            else if(run.text_report_available)body.append(node('pre',{class:'gc-execution-output'},(await request(path+'?test_data=full')).comment || 'Текстовый отчёт пуст.'));
            else body.append(node('p',{},run.execution_error?.message || 'Подробный журнал для этой проверки не сохранился.'));
            return body;
        }, {studentWork: true});
    }
    function previousCheck(event) {
        const score = event.points != null ? ' · ' + [event.points, event.maximum].filter(value => value != null).join(' из ') : '';
        const body = node('div');
        const panel = node('details', {class:'gc-report-provenance', open:true},
            node('summary', {}, 'Предыдущая проверка · ' + attemptTime.format(new Date(event.at)) + score), body);
        async function load() {
            body.replaceChildren(node('p', {role:'status'}, 'Загружаем проверку…'));
            try {
                const run = await request(`/insider/code-submissions/${event.source}/runs/${event.run}?test_data=metadata`);
                body.replaceChildren();
                if (run.comment) body.append(feedbackPanel(run.comment, {title:'Комментарий проверки'}));
                if (run.criteria?.length) body.append(criterionTable(run.criteria, true));
                if (run.report) renderReport(run.report, body, event.source, {afterPurchase: load});
                if (run.text_report_available) body.append(textReport(run));
                if (!body.childElementCount) body.append(node('p', {}, run.execution_error?.message || 'Подробный журнал для этой проверки не сохранился.'));
            } catch (e) {
                body.replaceChildren(node('p', {role:'alert'}, e.message), button('Повторить загрузку проверки', load));
            }
        }
        load();
        return panel;
    }
    const pendingChatAttempts = new Set();
    async function refreshChatAttempts(attempts) {
        for (const attempt of attempts) pendingChatAttempts.add(attempt);
        if (workspaceDialog?.open || !pendingChatAttempts.size) return;
        const id = activeAttempt, affected = new Set(pendingChatAttempts);
        pendingChatAttempts.clear();
        details.clear();
        try {
            const records = await loadHistory();
            if (activeAttempt !== id || workspaceDialog?.open) return;
            if (id && affected.has(id)) await openAttemptById(id);
            else if (!id && records?.length) await openAttempt(records[0]);
        } catch (e) { error(e); }
    }
    const chat=personalChat ? taskChat(root,{
        back:inlineReview || ownWorkspace ? ()=>root.dispatchEvent(new CustomEvent('gc:execution-close')) : null,
        solutions:panelHistory,
        grade:gradeChatSubmission,recheck:openChatReview,requestReview:openReviewRequest,
        details:viewDetails,source:teacher ? openSource : viewDetails,report:openReport,compose:openEditor,
        check:teacher ? null : event => viewDetails(event.attempt, event),
        updated:refreshChatAttempts,
    }) : null;
    if(chat){
        dialogHeading=node(teacher ? 'h3' : 'h2',{class:'h5 mb-0'});
        workspaceDialog=node('dialog',{id:root.id+'-dialog',class:'gc-dialog gc-source-dialog','aria-label':'Работа с решением'},
            node('header',{},dialogHeading,iconButton('Закрыть','fa-xmark',()=>workspaceDialog.close())),browser);
        styleWorkDialog(workspaceDialog, 'result');
        root.append(chat.surface,workspaceDialog);
        if(legacyForm && !legacyForm.querySelector('.text-danger'))legacyForm.hidden=true;
        workspaceDialog.addEventListener('close',()=>{
            root.prepend(notice);
            if(!composerVisible)placeVerdict(summary.querySelector(':scope > .gc-result-verdict'));
            if(composerVisible && previousAttempt)openAttemptById(previousAttempt).catch(error);
            previousAttempt=null;
            refreshChatAttempts([]);
            chat.refresh();
        });
    }
    const verdictHost = focusedVerdict;
    const busy=node('span',{class:'gc-submission-loading',role:'status'},node('i',{class:'fas fa-spinner fa-spin','aria-hidden':'true'}),' Загружаем работу…');
    function beginResult(){
        updateWorkDialogView('result');
        verdictHost?.replaceChildren();const jump=toolbar.querySelector('.gc-review-jump');if(jump)jump.disabled=true;stage.hidden=false;summary.hidden=false;summary.setAttribute('aria-busy','true');summary.querySelectorAll(':scope > header,:scope > .gc-award').forEach(el=>el.setAttribute('inert',''));stage.setAttribute('aria-busy','true');stage.setAttribute('inert','');stage.append(busy);
    }
    function finishResult(panel){
        if(panel){chat?.refresh();results.querySelectorAll('.gc-inline-grade').forEach(grade=>grade.dispatchEvent(new Event('gc:grading-dispose')));results.replaceChildren(panel);results.hidden=false;content.hidden=true;composerVisible=false;history.hidden=false;summary.hidden=false;}
        summary.removeAttribute('aria-busy');summary.querySelectorAll('[inert]').forEach(el=>el.removeAttribute('inert'));
        busy.remove();stage.removeAttribute('inert');stage.removeAttribute('aria-busy');
        const jump=toolbar.querySelector('.gc-review-jump');if(jump)jump.disabled=!results.querySelector('.gc-inline-grade');
        if(panel)revealAttemptStep();
    }
    function failResult(message,retry){
        finishResult();
        clearResult();stage.hidden=true;content.hidden=true;composerVisible=false;
        summary.replaceChildren(toolbar,history);history.hidden=!attemptItems.length;
        if(!attemptItems.length)history.replaceChildren();
        const jump=toolbar.querySelector('.gc-review-jump');if(jump)jump.disabled=true;
        notice.replaceChildren(node('p',{},message),button('Повторить загрузку',safe(retry)));notice.className='alert alert-danger';
    }
    function clearResult(){
        verdictHost?.replaceChildren();
        current=null;
        results.querySelectorAll('.gc-inline-grade').forEach(grade=>grade.dispatchEvent(new Event('gc:grading-dispose')));
        results.replaceChildren();results.hidden=true;
        summary.querySelectorAll(':scope > header,:scope > .gc-award,:scope > .gc-execution-status').forEach(el=>el.remove());
        toolbar.querySelector(':scope > .gc-result-context-bar')?.remove();
    }
    const studentLinks=()=>[...document.querySelectorAll('[data-execution-student]')].filter(link=>link.dataset.executionStudent==='#'+root.id && !link.classList.contains('d-none') && !link.hasAttribute('data-without-attempts'));
    const canNavigate=()=>inlineReview && studentLinks().length>1;
    const nextStudent=()=>root.dispatchEvent(new CustomEvent('gc:execution-navigate',{detail:{direction:1}}));
    const error = e => {
        notice.className = 'alert alert-danger'; notice.textContent = e.message;
        if (e.status === 409 && dirty && draft) notice.append(actions(button('Скачать мой черновик',() => {
            const url = URL.createObjectURL(new Blob([JSON.stringify({mode:draft.mode,language:draft.language,text:draft.text,files:files.map(file => ({path:file.path,content:file.content})),repository:draft.repository},null,2)],{type:'application/json'}));
            const link = node('a',{href:url,download:'my-draft.json'}); link.click(); setTimeout(() => URL.revokeObjectURL(url),1000);
        }),button('Загрузить серверную версию',safe(async () => {
            if (!confirm('Заменить локальный черновик версией с сервера? Сначала скачайте нужный код и сохраните выбранные файлы.')) return;
            setDraft(await request(draftUrl())); renderComposer(); notice.replaceChildren();
        }))));
    };
    const safe = action => async event => {event?.preventDefault(); try {await action(event);} catch (e) {if(e.name!=='AbortError')error(e);} };
    const markDirty = () => {dirty = true;editVersion++; requestId = null;draftState.textContent='Изменения не сохранены';clearTimeout(autoSaveTimer);autoSaveTimer=setTimeout(()=>save().catch(e=>{draftState.textContent='Не удалось сохранить черновик';if(e.status===409)error(e);}),900);};
    window.addEventListener('beforeunload', event => {if (dirty) {event.preventDefault(); event.returnValue = '';}});
    window.addEventListener('pagehide', () => {stop();details.cancel();clearTimeout(autoSaveTimer); editing?.destroy();}, {once:true});
    root.addEventListener('gc:execution-close',()=>{chat?.select(null);workspaceDialog?.close();generation++;historyGeneration++;stop();details.cancel();finishResult();current=null;selectedUser=null;activeAttempt=null;});

    async function settings() {
        if (config) return config;
        settingsPromise ||= request(base+'/checker').then(value => config=value).catch(e => {settingsPromise=null;throw e;});
        return settingsPromise;
    }
    function renderToolbar() {
        if (!teacher) return;
        heading.querySelector('h3').textContent=ownMode?'Моё решение':'Работа ученика';
        if(ownMode){toolbar.replaceChildren();return;}
        const title = selectedUser?.name || 'Сдачи ученика';
        const authorHeading = node('div',{},node('h3',{class:'h6 mb-0',tabIndex:-1},title));
        toolbar.replaceChildren(authorHeading);
        if (inlineReview) toolbar.prepend(iconButton('К списку работ','fa-arrow-left',() => {
            root.dispatchEvent(new CustomEvent('gc:execution-close'));
        }));
        if(canNavigate())toolbar.append(actions(iconButton('Предыдущая работа','fa-chevron-left',()=>root.dispatchEvent(new CustomEvent('gc:execution-navigate',{detail:{direction:-1}}))),iconButton('Следующая работа','fa-chevron-right',nextStudent)));
        if(teacher&&!ownMode){const jump=button('К оценке ↓',()=>{const grade=results.querySelector('.gc-inline-grade');grade?.querySelector('input')?.focus({preventScroll:true});grade?.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});});jump.classList.add('gc-review-jump');const navigation=toolbar.querySelector('.gc-execution-actions');if(navigation)navigation.prepend(jump);else toolbar.append(actions(jump));}
    }
    async function selectStudent(student,{attempt=null}={}) {
        generation++;historyGeneration++;stop();current=null;activeAttempt=null;
        clearResult();
        selectedUser=student;chat?.select(student.id,student.name,false);ownMode=false;attemptItems=[];
        content.hidden=true;notice.replaceChildren();renderToolbar();beginResult();
        try {
            await loadHistory();
            if(selectedUser===student && !ownMode){const target=attempt || attemptItems.find(item=>item.needs_review || item.state==='awaiting_review')?.id || attemptItems.find(item=>item.teacher_viewed===false)?.id || attemptItems[0]?.id;if(target)await openAttemptById(target);else{results.hidden=false;finishResult(node('p',{class:'gc-help-text'},'Сохранённых сдач пока нет.'));}}
        } catch(e) {if(selectedUser===student && e.name!=='AbortError')failResult(e.message,()=>selectStudent(student));}
    }
    async function startOwnSubmission({attempt=null}={}) {
        const selected = ++generation;
        historyGeneration++;stop();current=null;activeAttempt=null;
        ownMode=true;selectedUser=null;chat?.select(root.dataset.viewerId,'',!!legacyForm);results.replaceChildren();notice.replaceChildren();renderToolbar();
        content.hidden=false;
        content.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем форму сдачи…'));
        try {
            let records;
            if(root.hasAttribute('data-history-only')){content.hidden=true;records=await loadHistory();}
            else{
                await settings();
                if (selected !== generation) return;
                if (!config.teacher_submission_allowed) throw new Error('Сдача этой задачи пока недоступна. Проверьте настройки проверки.');
                if (!draft) setDraft(await request(draftUrl()));
                if (selected !== generation) return;
                renderComposer();
                chat?.setCanSubmit(true);
                records=await loadHistory();
            }
            if (selected !== generation) return;
            if(attempt)await openAttemptById(attempt);
            else if(records?.length && chat)await openAttempt(records[0]);
        } catch(e) {
            if (selected !== generation) return;
            error(e);notice.append(button('Повторить',safe(() => startOwnSubmission({attempt}))));
        }
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
    const backToResult=button('Вернуться к результату',safe(()=>openAttemptById(lastResultAttempt)));
    if (!teacher) {
        backToResult.setAttribute('aria-label', 'Вернуться к результату');
        backToResult.replaceChildren(node('i', {class: 'fas fa-arrow-left', 'aria-hidden': 'true'}), ' К результату');
    }
    function showComposer(){
        if(!draft || root.hasAttribute('data-history-only'))return;
        updateWorkDialogView('compose');
        verdictHost?.replaceChildren();
        lastResultAttempt=activeAttempt || lastResultAttempt;
        backToResult.hidden=!lastResultAttempt;
        generation++;stop();details.cancel();current=null;markSelectedAttempt(null);
        composerVisible=true;requestId=null;notice.replaceChildren();notice.className='';content.hidden=false;if(!content.querySelector('.gc-execution-composer'))renderComposer();
        results.hidden=true;history.hidden=true;summary.hidden=true;finishResult();editing?.measure?.();
    }
    async function save() {
        clearTimeout(autoSaveTimer);
        if(savePromise){await savePromise;return dirty?save():undefined;}
        if(!draft || !dirty && draft.revision>0)return;
        const version=editVersion,body=payload(),sentFiles=files.map(file=>({file,path:file.path,content:file.content})),sentUploads=[...uploads];
        saving=true;draftState.textContent='Сохраняем черновик…';
        savePromise=request(draftUrl(),'PUT',body).then(value=>{
            draft.revision=value.revision;
            const savedFiles=value.files.map((file,index)=>({...file,index})).filter(file=>!file.is_answer);
            for(const sent of sentFiles){
                if(!files.includes(sent.file))continue;
                const saved=savedFiles.find(file=>file.path===sent.path);
                if(saved){
                    const unchanged=sent.file.path===sent.path && sent.file.content===sent.content;
                    Object.assign(sent.file,{index:saved.index,download_url:saved.download_url,bytes:saved.bytes,changed:!unchanged});
                }else{delete sent.file.index;sent.file.changed=true;}
            }
            for(const upload of sentUploads){
                const saved=savedFiles.find(file=>file.path===upload.name);
                if(!saved || !uploads.includes(upload))continue;
                files.push(saved);uploads=uploads.filter(file=>file!==upload);
            }
            dirty=version!==editVersion;draftState.textContent=dirty?'Изменения не сохранены':'Черновик сохранён';
        }).finally(()=>{saving=false;savePromise=null;});
        return savePromise;
    }
    async function submit() {
        if (submitting) return;
        submitting=true;content.setAttribute('inert','');
        const submittedGeneration=generation;
        try{if(savePromise)await savePromise;if (dirty || draft.revision === 0) await save();}catch(e){submitting=false;content.removeAttribute('inert');throw e;}
        requestId ||= crypto.randomUUID();
        content.setAttribute('inert','');
        try {
            const record = await request(base+'/code-submissions'+draftQuery(draftSource),'POST',{request_id:requestId, use_draft:true, draft_revision:draft.revision});
            root.dispatchEvent(new CustomEvent('gc:attempt-submitted', {bubbles: true, detail: {taskId: root.dataset.taskId}}));
            previousAttempt=null;workspaceDialog?.close();chat?.refresh();
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
                }), 'coin'));
                notice.className = 'alert alert-warning';
            } else throw e;
        } finally {submitting=false;content.removeAttribute('inert');}
    }
    function renderComposer() {
        stage.hidden=false;
        editing?.destroy(); editing = null;
        const kind=composerKind(),groups=submissionGroups(config),allowed=config.submission_modes;
        content.hidden=!composerVisible;
        const composerHeading=node('div',{class:'gc-composer-heading'},node('h3',{class:'h6 mb-0'},teacher?'Пробное решение':'Ваше решение'));
        backToResult.hidden=!lastResultAttempt;
        composerHeading.append(backToResult);
        if (draftSource) composerHeading.append(node('span', {class:'gc-help-text'}, 'Доработка выбранной сдачи'));
        const body=node('section',{class:'gc-execution-composer'+(kind==='code'?' is-code-composer':''),'aria-label':'Форма сдачи решения'},composerHeading);
        if(composerVisible)summary.hidden=true;
        const form=node('div',{class:'gc-composer-body'});body.append(form);
        const options = node('div', {class:'gc-composer-options'});
        form.append(options);
        if(kind!=='text' && groups.length>1){
            const labels={files:['fa-file-arrow-up','Файлы'],code:['fa-code','Код'],repository:['fa-code-branch','Репозиторий']};
            const choices=node('div',{class:'gc-segmented-tabs gc-submission-modes',role:'group','aria-label':'Способ сдачи'});
            for(const group of groups){
                const choice=button('',()=>{
                    draft.mode=group==='code'?(allowed.includes('code')?'code':'project'):group;
                    markDirty();renderComposer();
                    content.querySelector('.gc-submission-modes .active')?.focus({preventScroll:true});
                });
                choice.className='nav-link'+(group===kind?' active':'');choice.setAttribute('aria-pressed',String(group===kind));
                choice.append(node('i',{class:'fas '+labels[group][0],'aria-hidden':'true'}),' '+labels[group][1]);choices.append(choice);
            }
            options.append(choices);
        }
        if(kind==='code' || kind==='repository'){
            const languageNames={cpp:'C++',python:'Python',py:'Python',javascript:'JavaScript',text:'Текст'};
            const languages=config.allowed_languages.filter(value=>value!=='text');
            if(languages.length>1)options.append(field('Язык',select(draft.language,Object.fromEntries(languages.map(value=>[value,languageNames[value] || value])),value=>{
                draft.language=value;markDirty();renderComposer();
                content.querySelector('.gc-composer-options select')?.focus({preventScroll:true});
            })));
            else if(languages.length)composerHeading.append(node('span',{class:'gc-composer-language text-muted small'},node('i',{class:'fas fa-code','aria-hidden':'true'}),' '+(kind==='code'?(files[0]?.path || config.entrypoint || (draft.language==='cpp'?'main.cpp':'main.py'))+' · ':'')+(languageNames[languages[0]] || languages[0])));
        }
        if(kind==='repository'){
            draft.repository ||= {};
            form.append(field('Ссылка на репозиторий',input(draft.repository.url,value=>{draft.repository.url=value;markDirty();},{type:'url',placeholder:'https://github.com/username/project'})),
                field('Ветка, тег или коммит',input(draft.repository.ref,value=>{draft.repository.ref=value;markDirty();},{placeholder:'По умолчанию — основная ветка'})));
        }else if(kind==='code'){
            if (root.dataset.editorStarter) composerHeading.append(node('a',{
                href:root.dataset.editorStarter, class:'gc-quiet-link small', download:'',
            }, 'Скачать шаблон'));
            const editable=files.length ? files : [{path:config.entrypoint || (draft.language==='cpp'?'main.cpp':'main.py'),content:'',placeholder:true}];
            const retain=file=>{if(file.placeholder){delete file.placeholder;files.push(file);}};
            const editors=node('div',{class:'gc-composer-editors'}),tabs=node('div',{class:'gc-source-tabs',role:'tablist','aria-label':'Файлы кода'});
            let selected=editable[0];
            const panes=[];
            const activate=file=>{selected=file;for(const entry of panes){entry.pane.hidden=entry.file!==file;entry.tab.setAttribute('aria-selected',String(entry.file===file));entry.tab.tabIndex=entry.file===file?0:-1;entry.tab.classList.toggle('is-active',entry.file===file);}};
            tabs.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight'].includes(event.key)||event.target.getAttribute('role')!=='tab')return;event.preventDefault();activate(editable[(editable.indexOf(selected)+(event.key==='ArrowRight'?1:editable.length-1))%editable.length]);tabs.querySelector('[aria-selected="true"]')?.focus();});
            for(const file of editable){
                const host=node('div',{class:'gc-code-editor'}),pane=node('div',editable.length>1?{role:'tabpanel'}:{});
                const name=input(file.path,value=>{retain(file);file.path=value;file.changed=true;tab.textContent=value || 'Новый файл';markDirty();},{placeholder:'main.py','aria-label':'Имя файла кода'});
                const controls=actions(field('Имя файла',name));
                if(editable.length>1)controls.append(button('Удалить файл',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}));
                if(file.content===undefined){
                    if(editable.length>1)pane.append(button('Удалить файл',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}));
                    pane.append(node('p',{class:'text-muted small'},'Исходник сохранён и будет отправлен без изменений. Для редактирования большого файла используйте свой редактор.'));
                    if (file.download_url) pane.append(download(file.download_url,'Скачать '+file.path));
                    pane.append(actions(button('Заменить код',()=>{if(!confirm('Заменить сохранённый исходник новым кодом? Сначала скачайте файл, если он нужен.'))return;file.content='';file.changed=true;markDirty();renderComposer();})));
                }else{if(editable.length>1)pane.append(controls);pane.append(host);}
                const tab=button(file.path || 'Новый файл',()=>activate(file));tab.className='gc-source-tab';tab.setAttribute('role','tab');if(editable.length>1){tab.id=root.id+'-source-tab-'+editable.indexOf(file);pane.id=tab.id+'-panel';tab.setAttribute('aria-controls',pane.id);pane.setAttribute('aria-labelledby',tab.id);}
                tabs.append(tab);panes.push({file,pane,tab});editors.append(pane);
                if(file.content!==undefined)import('./editor').then(({editor})=>{if(!host.isConnected)return;const view=editor(host,file.content,draft.language,value=>{retain(file);file.content=value;file.changed=true;markDirty();});const previous=editing;editing={destroy:()=>{previous?.destroy();view.destroy();},measure:()=>{previous?.measure?.();view.requestMeasure();}};}).catch(error);
            }
            if(allowed.includes('project') && editable.length<config.max_files){const add=button('+ Файл',()=>{retain(editable[0]);files.push({path:`file${editable.length+1}.${draft.language==='cpp'?'cpp':'py'}`,content:''});markDirty();renderComposer();const buttons=content.querySelectorAll('.gc-source-tab');buttons[buttons.length-1]?.click();});add.classList.add('btn-sm');if(editable.length>1)tabs.append(add);else composerHeading.append(add);}
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
                if(files.length || uploads.length)form.append(node('ul',{class:'gc-upload-list'},files.map(file=>node('li',{},file.download_url?download(file.download_url,file.path):file.path,' ',button('Убрать',()=>{files=files.filter(value=>value!==file);markDirty();renderComposer();}))),uploads.map(file=>node('li',{},file.name,' ',button('Убрать',()=>{uploads=uploads.filter(value=>value!==file);files=files.filter(value=>value.path!==file.name);markDirty();renderComposer();})))));
            }
        }
        const footer=actions(button('Отправить решение',safe(submit),'primary'),draftState);footer.classList.add('gc-composer-footer');form.append(footer);content.replaceChildren(body);
    }
    function artifact(value) {
        if (!value) return node('span',{class:'text-muted'},'Нет данных');
        return value.type === 'inline' ? node('pre',{class:'gc-execution-output'},value.text || '(пусто)') : node('div',{},download(value.download_url,`Скачать (${value.bytes} байт)`),value.truncated ? ' · вывод сокращён' : '');
    }
    let reportSequence = 0;
    function renderReport(report, parent, submissionId,{savedSource=false,afterPurchase=null}={}) {
        if (!report) return;
        if (report.compilation_error) {
            parent.append(node('section',{class:'gc-compilation-error','aria-label':'Ошибка компиляции'},
                node('h4',{class:'h6 text-danger'},'Ошибка компиляции'),
                node('p',{class:'text-muted'},'Код не скомпилирован. Тесты не запускались.'),
                artifact(report.compilation_error)));
            return;
        }
        for (const note of report.notes || []) parent.append(node('p',{},note));
        if (report.notice && !savedSource) parent.append(report.legacy
            ? node('details',{class:'gc-report-provenance'},node('summary',{},'О данных исторической проверки'),node('p',{class:'text-muted small'},report.notice))
            : node('p',{class:'alert alert-info'},report.notice));
        const section = node('section',{class:'gc-test-report','aria-label':'Результаты тестов'});
        section.append(node('div',{class:'gc-test-report__heading'},node('h4',{class:'h6 mb-0'},savedSource?'Проверка сохранённого кода':'Результаты тестов'),
            node('span',{class:report.passed === report.total ? 'gc-test-total is-passed' : 'gc-test-total'},`${report.passed} из ${report.total} пройдено`)));
        const groups = testGroupSummary(report.groups);
        if (groups) section.append(groups);
        const groupNames = new Map((report.groups || []).map(group=>[group.id,group.name]));
        const rows = node('tbody');
        const table = node('table',{class:'gc-test-table'},node('thead',{},node('tr',{},
            node('th',{scope:'col'},'Тест'),node('th',{scope:'col'},'Результат'),node('th',{scope:'col',class:'gc-test-access'},'Доступ'),node('th',{scope:'col'},node('span',{class:'visually-hidden'},'Данные теста')))),rows);
        const sequence = ++reportSequence;
        const expansionKey=submissionId+'/'+report.run_id;
        if(!expandedTests.has(expansionKey))expandedTests.set(expansionKey,new Set());
        const expanded=expandedTests.get(expansionKey),allTests=report.tests || [];
        const pageCount=Math.max(1,Math.ceil(allTests.length/10)),pageNodes=new Map();
        const firstForSale=allTests.findIndex(test => test.can_purchase);
        const initialPage=firstForSale < 0 ? 1 : Math.floor(firstForSale / 10) + 1;
        let testPage=Math.min(pageCount,testPages.get(expansionKey) || initialPage);
        const pagination=node('nav',{class:'gc-test-pages','aria-label':'Страницы тестов'});
        const tableViewport=node('div',{class:'gc-test-table-scroll'},table);let pageHeight=0;
        function renderTests(){
        if(pageNodes.has(testPage)){rows.replaceChildren(...pageNodes.get(testPage));renderPages();return;}
        rows.replaceChildren();
        const batch=allTests.slice((testPage-1)*10,testPage*10);
        for (const test of batch) {
            const passed = test.verdict === 'passed';
            const detailsId = `${root.id || 'execution'}-report-${sequence}-test-${test.number}`;
            const details = node('div',{class:'gc-test-data'});
            const detailRow = node('tr',{class:'gc-test-detail',id:detailsId,hidden:true},node('td',{colSpan:4},details));
            detailRow.hidden=!expanded.has(test.number);
            let loaded=false, loading;
            const loadDetails=async () => {
                if(loaded)return;
                loading ||= request(`/insider/code-submissions/${submissionId}/runs/${report.run_id}/tests/${test.number}`).then(value => {
                    const data=value.test || value;
                    details.replaceChildren();
                    for(const [key,label] of Object.entries({input:'Ввод',expected:'Ожидаемый ответ',actual:'Вывод программы',error:'Ошибка'})) {
                        if(data[key])details.append(node('div',{},node('strong',{},label),artifact(data[key])));
                    }
                    if(data.notice)details.append(node('p',{class:'gc-help-text gc-test-source-note mb-0'},data.notice));
                    if(!details.children.length)details.append(node('p',{class:'text-muted mb-0'},'В этой проверке подробные данные теста не сохранялись.'));
                    loaded=true;
                }).catch(e => {
                    loading=null;
                    details.replaceChildren(node('p',{class:'text-danger mb-2'},e.message),button('Повторить загрузку теста',safe(loadDetails)));
                });
                details.replaceChildren(node('p',{class:'text-muted mb-0',role:'status'},'Загружаем данные теста…'));
                await loading;
            };
            let control;
            if (test.unlocked) {
                const toggle = button('Данные',() => {
                    detailRow.hidden = !detailRow.hidden;
                    if(detailRow.hidden)expanded.delete(test.number);else expanded.add(test.number);
                    toggle.setAttribute('aria-expanded',String(!detailRow.hidden));
                    toggle.textContent = detailRow.hidden ? 'Данные' : 'Свернуть';
                    if(!detailRow.hidden)loadDetails();
                });
                toggle.classList.add('btn-sm');
                toggle.textContent=detailRow.hidden?'Данные':'Свернуть';
                toggle.setAttribute('aria-expanded',String(!detailRow.hidden));
                toggle.setAttribute('aria-controls',detailsId);
                toggle.setAttribute('aria-label','Данные теста '+test.number);
                if(test.has_details===false){toggle.disabled=true;toggle.textContent='Нет данных';toggle.title='Подробные данные этой проверки не сохранились.';}
                else if(!detailRow.hidden)loadDetails();
                control=toggle;
            } else if (test.can_purchase) {
                const purchase = button(`Открыть за ${report.price} GC`,async () => {
                    purchase.disabled=true;
                    try {
                        await settings();
                        const provenance=test.data_source==='geekpaste_archive'?'\nВвод и эталон — из снимка GeekPaste при переносе; набор мог меняться после отправки. Вывод программы не сохранился.':'';
                        if (!confirm(`Открыть тест № ${test.number} за ${report.price} GC?\nБаланс: ${config.balance} GC. Данные останутся доступны в следующих попытках.${provenance}`)) return;
                        const value=await request(test.purchase_url || `/insider/code-test-reports/${report.run_id}/tests/${test.test_id}/purchase?test_data=metadata`,'POST',{expected_price:report.price});
                        config.balance=value.balance;
                        expanded.add(test.number);
                        testPages.set(expansionKey,testPage);
                        if(afterPurchase)await afterPurchase();else await show(current.id,current.snapshotOf?{snapshotOf:current.snapshotOf}:{});
                    } catch(error) {
                        details.replaceChildren(node('p',{class:'text-danger mb-0',role:'alert'},error.message));
                        detailRow.hidden=false;
                    } finally {purchase.disabled=false;}
                },'coin');
                purchase.classList.add('btn-sm');
                purchase.prepend(node('i',{class:'fas fa-coins','aria-hidden':'true'}),' ');
                control=purchase;
            } else {
                const reason=passed ? 'Данные пройденного теста закрыты.' : 'Данные этого теста недоступны.';
                control=node('span',{class:'text-muted',title:reason},node('i',{class:'fas fa-lock','aria-hidden':'true'}),node('span',{class:'visually-hidden'},reason));
            }
            const verdict = node('span',{class:`gc-test-verdict ${passed ? 'is-passed' : 'is-failed'}`},
                node('i',{class:`fas ${passed ? 'fa-check-circle' : 'fa-circle-xmark'}`,'aria-hidden':'true'}),' ',verdicts[test.verdict] || test.verdict);
            const visibility=(test.is_public?'Публичный':'Скрытый')+(!teacher&&test.unlocked&&!test.is_public?' · Открыт вам':'');
            const groupName = groupNames.get(test.group);
            rows.append(node('tr',{},node('th',{scope:'row'},`№ ${test.number}`,groupName ? node('small',{class:'d-block fw-normal text-muted text-break'},groupName) : null),node('td',{},verdict,node('small',{class:'gc-test-access-mobile text-muted'},visibility)),
                node('td',{class:'gc-test-access'},node('span',{class:'gc-test-visibility'},test.is_public ? 'Публичный' : 'Скрытый'),!teacher && test.unlocked && !test.is_public ? node('small',{class:'d-block text-muted'},'Открыт вам') : null),node('td',{},control)),detailRow);
        }
        pageNodes.set(testPage,[...rows.children]);while(pageNodes.size>3)pageNodes.delete(pageNodes.keys().next().value);renderPages();
        }
        function renderPages(){
            pagination.hidden=pageCount<=1;
            if(pageCount<=1)return;
            const controls=node('div',{class:'gc-test-pages__controls'});
            const change=value=>{
                const height=table.querySelector('thead').getBoundingClientRect().height+[...rows.children].filter(row=>!row.classList.contains('gc-test-detail')).reduce((sum,row)=>sum+row.getBoundingClientRect().height,0);
                pageHeight=Math.max(pageHeight,height);tableViewport.style.minHeight=Math.ceil(pageHeight)+'px';
                testPage=value;testPages.set(expansionKey,value);renderTests();pagination.querySelector('[aria-current="page"]')?.focus({preventScroll:true});
            };
            const previous=iconButton('Предыдущая страница тестов','fa-chevron-left',()=>change(testPage-1));previous.disabled=testPage===1;controls.append(previous);
            let previousNumber=0;
            for(const number of [...new Set([1,testPage-1,testPage,testPage+1,pageCount])].filter(n=>n>0&&n<=pageCount).sort((a,b)=>a-b)){
                if(previousNumber&&number>previousNumber+1)controls.append(node('span',{'aria-hidden':'true'},'…'));
                const choice=button(String(number),()=>change(number),number===testPage?'primary':'outline-secondary');choice.setAttribute('aria-label','Страница тестов '+number);
                if(number===testPage)choice.setAttribute('aria-current','page');controls.append(choice);previousNumber=number;
            }
            const next=iconButton('Следующая страница тестов','fa-chevron-right',()=>change(testPage+1));next.disabled=testPage===pageCount;controls.append(next);
            pagination.replaceChildren(node('span',{class:'gc-help-text',role:'status'},`${(testPage-1)*10+1}–${Math.min(testPage*10,allTests.length)} из ${allTests.length}`),controls);
        }
        renderTests();
        section.append(tableViewport);
        section.append(pagination);
        parent.append(section);
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
    function markSelectedAttempt(id) {
        activeAttempt=id;writeContext(id);
        history.querySelectorAll('[data-attempt-id]').forEach(item=>{
            const selected=item.dataset.attemptId===String(id);item.classList.toggle('is-selected',selected);
            if(selected)item.setAttribute('aria-current','true');else item.removeAttribute('aria-current');
        });
        const picker=history.querySelector('[data-attempt-picker]');if(picker)picker.value=id || '';
        const counter = history.querySelector('.gc-attempt-count');
        const item = attemptItems.find(item => String(item.id) === String(id));
        const viewed = history.querySelector('.gc-attempt-history__viewed');
        if (counter) {
            const count = Number(history.dataset.totalAttempts) || attemptItems.length;
            const unit = {one:'сдача', few:'сдачи', many:'сдач', other:'сдача'}[scorePlural.select(count)];
            const status = typeof item?.teacher_viewed === 'boolean' && !item.source_version_unknown
                ? (item.teacher_viewed ? (teacher ? 'Просмотрено' : 'Просмотрено учителем') : (teacher ? 'Не просмотрено' : 'Учитель ещё не просмотрел'))
                : '';
            const countLabel = `${count} ${unit}`;
            if (viewed) {
                viewed.textContent = status;
                viewed.hidden = !status;
            }
            counter.textContent = viewed || !status ? countLabel : `${status} · ${countLabel}`;
        }
        showAttemptDate(item);
    }
    function showAttemptDate(item) {
        const date = history.querySelector('.gc-attempt-history__date');
        if (!date) return;
        date.hidden = !item?.created_at;
        date.dateTime = item?.created_at || '';
        date.textContent = item?.created_at ? attemptTime.format(new Date(item.created_at)) : '';
    }
    function revealAttemptStep(step = history.querySelector('.gc-attempt-step[aria-current="true"]')) {
        const rail = step?.parentElement;
        if (!rail?.clientWidth) return;
        const left = step.offsetLeft - rail.scrollLeft;
        if (left < 0) rail.scrollLeft += left;
        else if (left + step.offsetWidth > rail.clientWidth) rail.scrollLeft += left + step.offsetWidth - rail.clientWidth;
    }
    function attemptStep(record, existing) {
        const id = String(record.id);
        const step = existing || node('button', {type:'button', class:'gc-attempt-step', 'data-attempt-id':id,
            onclick:safe(() => openAttemptById(id))});
        step.dataset.tone = attemptOutcome(record);
        const marker = attemptMarkers[step.dataset.tone];
        const details = attemptLabel(record);
        const label = details.includes(marker.label) ? details : [details, marker.label].filter(Boolean).join(' · ');
        step.title = label;
        step.setAttribute('aria-label', label);
        step.replaceChildren(node('span', {class:'gc-attempt-step__icon', 'aria-hidden':'true'},
            node('i', {class:'fas '+marker.icon})));
        return step;
    }
    function attemptHeader(record) {
        const points = record.evaluated_points ?? record.points;
        const maximum = record.source_version_unknown ? null : record.maximum_points;
        let result = phases[record.state] || 'Ожидает проверки';
        let tone = record.state === 'error' ? 'error' : 'neutral';
        if (record.state === 'completed') {
            const units = {one: 'балл', few: 'балла', many: 'баллов', other: 'балла'}[scorePlural.select(maximum ?? points)];
            const score = points == null ? null : [points, maximum].filter(value => value != null).join(' / ') + ' ' + units;
            const xp = record.awarded_xp != null && record.awarded_xp !== points ? `${record.awarded_xp} XP` : null;
            result = [score, xp].filter(Boolean).join(' · ') || result;
            if (points != null && maximum > 0) {
                if (points >= maximum) tone = 'passed';
                else if (points > 0) tone = 'partial';
                else tone = 'error';
            }
        }
        return [
            node('span', {class:'gc-task-history__file', 'aria-hidden':'true'}, node('i', {class:'far fa-file-lines'})),
            node('span', {class:'gc-task-history__date'},
                record.created_at ? node('time', {dateTime: record.created_at}, attemptTime.format(new Date(record.created_at))) : 'Решение',
                record.counts_as_attempt === false ? node('small', {class:'text-muted'}, 'Сохранённая версия') : null),
            node('span', {class:'gc-attempt-list__score', 'data-tone':tone}, result),
            node('i', {class:'fas fa-chevron-right', 'aria-hidden':'true'}),
        ];
    }
    function renderTaskHistory(hasMore) {
        if (!taskAttemptList) return;
        const previousRows = new Map([...taskAttemptList.children].map(row => [row.firstElementChild.dataset.attemptId, row]));
        const focused = taskAttemptList.contains(document.activeElement) ? document.activeElement : null;
        const rows = attemptItems.map(record => {
            const id = String(record.id);
            const row = previousRows.get(id) || node('li', {}, node('button', {
                type: 'button', class: 'gc-attempt-list__item', 'data-attempt-id': id,
                'aria-haspopup': 'dialog', 'aria-controls': workspaceDialog.id,
                onclick: safe(() => viewDetails(id)),
            }));
            row.firstElementChild.replaceChildren(...attemptHeader(record));
            return row;
        });
        taskAttemptList.replaceChildren(...rows);
        if (solutionsStatus) {
            solutionsStatus.textContent = rows.length ? '' : 'Сдач пока нет.';
            solutionsStatus.hidden = !!rows.length;
        } else taskHistory.hidden = !rows.length;
        moreTaskAttempts.hidden = !hasMore;
        focused?.focus({preventScroll: true});
    }
    function ensureAttemptCard(record){
        const id=String(record.id);let summary=attemptItems.find(item=>String(item.id)===id);
        if(!summary){summary={id,kind:record.kind || 'submission'};attemptItems.push(summary);}
        for(const key of ['kind','created_at','awarded_xp','points','evaluated_points','maximum_points','grading_maximum_points','state','counts_as_attempt','is_best','source_version_unknown','teacher_viewed','solution_id'])if(record[key]!==undefined)summary[key]=record[key];
        if (record.award_breakdown?.evaluated_points != null) summary.evaluated_points = record.award_breakdown.evaluated_points;
        const picker=history.querySelector('[data-attempt-picker]');
        if(picker){let option=[...picker.options].find(o=>o.value===id);if(!option){option=node('option',{value:id});picker.append(option);}option.textContent=choiceLabel(summary);}
        const rail = history.querySelector('.gc-attempt-steps');
        if (rail) {
            const existing = rail.querySelector(`[data-attempt-id="${CSS.escape(id)}"]`);
            const step = attemptStep(summary, existing);
            if (!existing) rail.append(step);
            if (String(activeAttempt) === id) showAttemptDate(summary);
        }
        taskAttemptList?.querySelector(`[data-attempt-id="${CSS.escape(id)}"]`)?.replaceChildren(...attemptHeader(summary));
    }
    function choiceLabel(record){
        return focusedVerdict && record.created_at ? new Date(record.created_at).toLocaleString('ru-RU',
            {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : attemptLabel(record);
    }
    function attemptLabel(record){
        const score=record.awarded_xp!==null&&record.awarded_xp!==undefined?`${record.awarded_xp}${record.maximum_points?' / '+record.maximum_points:''} XP`:record.state==='error'?'Ошибка проверки':record.points!==null&&record.points!==undefined?`${record.points} баллов`:phases[record.state];
        return [record.created_at?attemptTime.format(new Date(record.created_at)):null,record.is_best?'лучшая':null,record.counts_as_attempt===false?'материалы':null,score].filter(Boolean).join(' · ');
    }
    function writeContext(id){
        // Opening a task loads its latest attempt in the background. Keep the task URL on reload.
        if (taskOutcome && !root.closest('.gc-student-attempt')) return;
        if(root.dataset.taskId && location.pathname.includes('/steps/') && root.getClientRects().length){
            const url=new URL(location.href);
            url.searchParams.set('task',root.dataset.taskId);
            url.searchParams.set('student',teacher && !ownMode ? selectedUser.id : root.dataset.viewerId);
            if(id)url.searchParams.set('attempt',id);else url.searchParams.delete('attempt');url.hash='task'+root.dataset.taskId;
            historyState(url);
        }
    }
    function historyState(url){globalThis.history.replaceState(globalThis.history.state,'',url);}
    async function openAttemptById(id, check){
        const item=attemptItems.find(item=>String(item.id)===String(id));
        if(item)return openAttempt(item, check);
        if(/^solution-\d+$/.test(id))return showLegacyAttempt({id,kind:'legacy_attempt',solution_id:Number(id.slice(9))}, check);
        return show(id, {check});
    }
    function renderAward(record,parent,{label=true}={}) {
        let panel;
        const render=()=>awardPanel(record,{student:!teacher||ownMode,onChange:async value=>{
            if(!panel?.isConnected)return;
            Object.assign(record,value);details.clear();const updated=render();if(updated)panel.replaceWith(updated);else panel.remove();panel=updated;
            const score=summary.querySelector('.gc-result-score > strong') ?? verdictHost?.querySelector('.gc-result-score > strong');
            if(score)score.textContent=String(value.awarded_xp);
            if(current && current.id===record.id)Object.assign(current,value);
            await loadHistory();
            if(!teacher||ownMode)document.dispatchEvent(new CustomEvent('gc:task-checked',{detail:{taskId:root.dataset.taskId,mark:value.awarded_xp}}));
        }});
        panel=render();if(panel)parent.append(panel);
    }
    async function showLegacyAttempt(item, check) {
        const selected=++generation;stop();current=null;ensureAttemptCard({...item,kind:'legacy_attempt'});markSelectedAttempt(item.id);
        beginResult();
        try {
            const record=await details.get(base+'/code-attempts/'+item.solution_id);
            if(selected!==generation)return;
            if(Number(record.user_id)!==Number(teacher && !ownMode ? selectedUser.id : root.dataset.viewerId))throw new Error('Эта сдача принадлежит другому ученику. Выберите его в списке.');
            ensureAttemptCard(record);
            if(record.source_submission_id){await show(record.source_submission_id,{snapshotOf:record,check});return;}
            const {header}=resultHeader(record,{showSourceDate:false,reviewing:teacher&&!ownMode,compact:!teacher || ownWorkspace || inlineReview});
            const panel=node('section',{class:'gc-execution-result'},header);
            if(teacher && !ownMode){const menu=resultActions();const remove=removeAttemptAction(record);menu.add(remove);remove.classList.add('text-danger');panel.append(node('div',{class:'gc-result-context-bar'},menu.root));}
            renderAward(record,panel,{label:false});
            if(!teacher && record.source_notice)panel.append(node('p',{class:'gc-help-note'},record.source_notice));
            if(record.text)panel.append(workPreview({...record,mode:'text'}));
            if(!record.text)panel.append(node('p',{class:'text-muted small'},'Код этой сдачи не сохранился отдельно.'));
            if(record.can_grade && !ownMode)panel.append(legacyGrading(record));
            else if(record.comment)panel.append(feedbackDisplay(record.comment));
            if((!teacher || ownMode) && !root.hasAttribute('data-history-only'))panel.append(actions(button('Ещё одно решение',showComposer,'primary')));
            if (!teacher) {
                const review = reviewRequest(record, () => showLegacyAttempt(item));
                if (review) panel.append(review);
            }
            arrangeResult(panel);finishResult(panel);
        } catch(e) {
            if(selected!==generation || e.name==='AbortError')return;
            failResult(e.message,()=>showLegacyAttempt(item, check));
        }
    }
    function feedbackDisplay(comment){
        return feedbackPanel(comment);
    }
    function inlineGrading(options){
        return gradingPanel({...options,drafts:gradingDrafts,onNext:canNavigate()?nextStudent:null});
    }
    function removeAttemptAction(record) {
        const control=button('Удалить решение…',()=>{
            const status=node('p',{class:'text-danger',role:'alert'});
            let dialog;
            const remove=button('Удалить решение',async()=>{
                remove.disabled=true;
                try {
                    const attempt=record.kind==='legacy_attempt'?'solution-'+record.solution_id:record.id;
                    const value=await request(base+'/attempts/'+attempt,'DELETE',{confirm:true});
                    dialog.close();location.assign(value.redirect_url);
                } catch(error) {status.textContent=error.message;remove.disabled=false;}
            },'danger');
            dialog=openDialog('Удалить решение?',node('div',{},
                node('p',{},selectedUser?.name || record.user_name || 'Ученик',record.created_at?' · '+new Date(record.created_at).toLocaleString('ru-RU'):''),
                node('p',{},'Эта сдача, её проверки и связанные достижения будут удалены без возможности восстановления. Баллы и прогресс пересчитаются по оставшимся решениям.'),
                node('p',{class:'gc-help-text'},'Начисленные за эту сдачу GC будут списаны с баланса.'),status),
                [button('Отмена',()=>dialog.close()),remove]);
        });
        return control;
    }
    function startRecheck(record,snapshot,values) {
        return request('/insider/code-submissions/'+record.id+'/recheck','POST',{
            ...values, expected_run_id:record.execution_id, ...(snapshot ? {review_solution_id:snapshot.solution_id} : {}),
        });
    }
    function recheckDialog(record,options,stronger=false){
        const ai=usesAi(record.recheck_type || record.check_type);
        const reviewSolution=options.snapshotOf?.solution_id;
        const model=select(stronger?'strong':'normal',{'normal':ai?'Обычная модель':'Автотесты',...(record.ai_recheck?.available?{'strong':'Усиленная модель'}:{})},()=>{});
        const note=textarea('',()=>{},{rows:4,maxLength:4000,placeholder:'Что нужно проверить внимательнее?'});
        const latest=node('input',{type:'checkbox',class:'form-check-input'});
        const status=node('p',{class:'gc-help-text',role:'status'});
        const content=node('div',{},ai?field('Модель',model):null,field('Комментарий к перепроверке',note),node('p',{class:'gc-help-text'},reviewSolution?'Будут проверены сохранённые файлы, показанные на экране. Результат обновит оценку выбранной сдачи; предыдущие проверки останутся в журнале.':'Будет проверена сохранённая работа. Предыдущие проверки останутся в журнале этой сдачи.'),node('details',{},node('summary',{},'Настройки проверки'),field('Использовать текущие настройки задачи',latest)),status);
        model.addEventListener('change',()=>{latest.disabled=model.value==='strong';if(latest.disabled)latest.checked=false;});latest.disabled=stronger;
        let dialog;
        const run=button('Запустить перепроверку',async()=>{run.disabled=true;try{await startRecheck(record,options.snapshotOf,{stronger_ai:model.value==='strong',latest_configuration:latest.checked,comment:note.value});dialog.close();await show(record.id,{...options,refreshReviewSolution:reviewSolution});await loadHistory();}catch(error){status.textContent=error.message;run.disabled=false;}},'primary');
        dialog=openDialog(ai?'Перепроверить решение':'Повторить автотесты',content,[button('Отмена',()=>dialog.close()),run]);
    }
    function compareDialog(record){
        const candidates=attemptItems.filter(item=>item.kind==='submission'&&!item.source_version_unknown);
        const labels=Object.fromEntries(candidates.map(item=>[item.id,attemptLabel(item)]));
        const before=select(candidates.find(item=>item.id!==record.id)?.id || record.id,labels,()=>{}),after=select(record.id,labels,()=>{});
        const status=node('p',{class:'gc-help-text',role:'status'});let dialog;
        const run=button('Показать изменения',async()=>{
            if(before.value===after.value){status.textContent='Выберите две разные сдачи.';return;}
            run.disabled=true;
            try{
                const value=await request(`/insider/code-submissions/${before.value}/compare?with=${after.value}`);
                const {diffLines}=await import('./diff');
                const source=data=>new Map([...(data.files || []).map(file=>[file.name,file]),...(data.text?[['Текст ответа',{text:data.text}]]:[])]);
                const old=source(value.before),next=source(value.after),content=node('div',{class:'gc-comparison'});
                for(const name of new Set([...old.keys(),...next.keys()])){
                    const first=old.get(name),second=next.get(name),lines=diffLines(first?.text || '',second?.text || '');
                    const side=(added)=>node('section',{},node('h4',{class:'h6'},(added?'Стало':'Было')+' · '+name),node('pre',{class:'gc-execution-output gc-execution-diff'},lines.filter(line=>line.type!==(added?'removed':'added')).map(line=>node('span',{class:'gc-diff-'+line.type},`${line.type==='added'?'+':line.type==='removed'?'−':' '} ${line.text}\n`))));
                    content.append(first?.text!==undefined||second?.text!==undefined?node('div',{class:'gc-comparison__pair'},side(false),side(true)):node('p',{},name+': '+(first?.sha256===second?.sha256?'без изменений':'файл изменён; исходники доступны для скачивания в каждой сдаче')));
                }
                dialog.close();const result=openDialog('Изменения между сдачами',content);result.classList.add('gc-comparison-dialog');
            }catch(error){status.textContent=error.message;run.disabled=false;}
        },'primary');
        dialog=openDialog('Сравнить сдачи',node('div',{},field('Было',before),field('Стало',after),status),[run]);
    }
    function workPreview(record) {
        if(!chat || workspaceDialog.open)return submittedWork(record);
        const placeholder=node('div',{class:'gc-submitted-work','data-chat-source-preview':''});
        placeholder.addEventListener('gc:preview-open',()=>placeholder.replaceWith(submittedWork(record)),{once:true});
        return placeholder;
    }
    function placeVerdict(header) {
        if(!verdictHost || !header || workspaceDialog?.open)return;
        verdictHost.replaceChildren(header);
    }
    function arrangeResult(body){
        const header=body.querySelector(':scope > header.gc-result-verdict'),tools=body.querySelector(':scope > .gc-result-context-bar');
        toolbar.querySelector(':scope > .gc-result-context-bar')?.remove();
        if(header && !header.hidden && tools){
            if(chat && teacher && !ownMode)toolbar.append(tools);
            else if (!ownWorkspace) header.append(tools);
        }
        const award=body.querySelector(':scope > .gc-award'),status=body.querySelector(':scope > .gc-execution-status');
        if (inlineReview) {
            if (header?.hidden && tools) toolbar.append(tools);
            summary.replaceChildren(toolbar, history, ...[header,award,status].filter(Boolean));
        } else if(teacher&&!ownMode){const navigation=toolbar.querySelector('.gc-execution-actions');if(navigation)navigation.before(history);else toolbar.append(history);if(header?.hidden&&tools)toolbar.append(tools);summary.replaceChildren(toolbar,...[header,award,status].filter(Boolean));}
        else {
            if (history.parentElement === summary) {
                for (const child of [...summary.children]) if (child !== history) child.remove();
                summary.append(...[header,award,status].filter(Boolean));
            } else summary.replaceChildren(history,...[header,award,status].filter(Boolean));
        }
        placeVerdict(header);
        summary.classList.toggle('is-toolbar-only',teacher&&!ownMode && !!header?.hidden && !award && !status);
        summary.classList.toggle('is-student',!teacher||ownMode);
        if(!teacher || ownMode){
            let controls=body.querySelector(':scope > .gc-execution-actions');
            const review=body.querySelector(':scope > .gc-review-request');
            if(review){controls??=actions();controls.append(review);}
            if(controls){
                controls.classList.add('gc-submission-actions');
                controls.setAttribute('role','group');controls.setAttribute('aria-label','Действия с решением');
                if(focusedVerdict)body.append(controls);else body.prepend(controls);
            }
            const reviewNote = body.querySelector(':scope > .gc-review-request-note');
            if (reviewNote) {
                if (controls) controls.after(reviewNote);
                else body.prepend(reviewNote);
            }
            const source=[...body.children].filter(child=>child.matches('.gc-submitted-work'));
            const feedback=[...body.children].filter(child=>child.matches('.gc-grading-feedback'));
            const tests = teacher ? null : body.querySelector(':scope > :is(.gc-test-report, .gc-compilation-error)');
            if(source.length && feedback.length){
                const work=node('section',{class:'gc-student-result-columns__work','aria-label':'Сданная работа'},source);
                const review=node('section',{class:'gc-student-result-columns__feedback','aria-label':'Комментарий к проверке'},feedback);
                body.insertBefore(node('div',{class:'gc-student-result-columns'},work,review), tests);
            }else source.forEach(child=>body.insertBefore(child, tests));
            return;
        }
        const overview=node('div',{class:'gc-result-overview'}),main=node('div',{class:'gc-result-main'}),side=node('aside',{class:'gc-result-review','aria-label':'Проверка и оценивание'});
        for(const child of [...body.children]){
            const target=child.matches('.gc-inline-grade,.gc-review-request-note')?side
                :child.matches('header,.gc-award,.gc-submitted-work')?overview:main;
            target.append(child);
        }
        [...side.querySelectorAll(':scope > .gc-inline-grade')].reverse().forEach(el=>side.prepend(el));
        const grade=side.querySelector('.gc-inline-grade'),question=side.querySelector(':scope > .gc-review-request-note');if(grade&&question)grade.querySelector('header').after(question);
        body.classList.add('gc-execution-result--review');
        const gradingOnly=!main.children.length && side.children.length>0;
        const assessment=node('div',{class:`gc-result-assessment${gradingOnly?' is-grading-only':''}${side.children.length?'':' is-results-only'}`},main,side);
        // Without a separate review, the submitted work and grading form share one sheet.
        if(gradingOnly)assessment.prepend(overview);else body.append(overview);
        if(main.children.length || side.children.length)body.append(assessment);
    }
    function reviewRequest(record,reload){
        const status=record.review_request;
        if(!status)return null;
        if(status.requested)return node('div',{class:'gc-review-request-note'},node('strong',{},'Запрос на перепроверку отправлен'),status.comment?node('p',{},status.comment):null,node('p',{class:'gc-help-text mb-0'},'Преподаватель увидит вопрос вместе с этой сдачей.'));
        if(!status.available)return status.message?node('p',{class:'gc-help-text'},status.message):null;
        const attempt = record.kind === 'legacy_attempt' ? 'solution-'+record.solution_id : record.id;
        return node('div', {class:'gc-review-request'}, button('Попросить перепроверить',
            () => openReviewRequest(attempt, new Date(record.created_at).toLocaleString('ru-RU'), reload), 'outline-primary'));
    }

    function openReviewRequest(attempt, label, onResult) {
        const legacy = attempt.startsWith('solution-');
        const url = legacy ? base+'/solution/'+attempt.slice(9)+'/recheck' : '/insider/code-submissions/'+attempt+'/review-request';
        const message = node('p',{class:'gc-help-text',role:'status'});
        const area = textarea(reviewDrafts.get(attempt) || '',value=>reviewDrafts.set(attempt,value),
            {minLength:10,maxLength:1000,rows:3});
        const submit = button('Отправить запрос',async()=>{
            const comment = area.value.trim();
            if (comment.length < 10) {
                message.textContent='Опишите вопрос подробнее: от 10 символов.';
                area.focus();
                return;
            }
            submit.disabled=true;
            try {
                const status=await request(url,'POST',legacy ? {recheck_comment:comment} : {comment});
                if (status.requested) {
                    reviewDrafts.delete(attempt);
                    dialog.close();
                } else {
                    message.textContent=status.message || 'Перепроверка этой сдачи сейчас недоступна.';
                }
                await onResult(status);
            } catch (error) {
                message.textContent=error.message;
            } finally { submit.disabled=false; }
        },'primary');
        const dialog=openDialog('Запрос на перепроверку',node('div',{},node('p',{class:'gc-help-text'},label),
            field('Что преподавателю нужно проверить?',area),message),[submit]);
        area.focus();
    }
    function skipReview(record){
        return record.review?.skip_url?async()=>{const selected=generation;await request(record.review.skip_url,'POST',{review_token:record.review.token});if(selected!==generation)return;await loadHistory();if(canNavigate())nextStudent();else await openAttemptById(record.id);}:null;
    }
    function legacyGrading(record,{comment=record.teacher_feedback ? record.review_comment ?? '' : record.review_comment || record.comment || ''}={}){
        return inlineGrading({key:'legacy-'+record.solution_id,comment,mark:record.review_mark,maximum:record.grading_maximum_points,ungraded:record.awarded_xp===null,
            hint:`Максимум ${record.grading_maximum_points} баллов. Правила срока применяются при сохранении.`,onSkip:skipReview(record),
            onSave:async(mark,comment)=>{
                const savedGeneration=generation;
                await request(record.grade_url,'POST',{mark,comment,review_solution_id:record.solution_id});
                if(savedGeneration!==generation)return false;
                await showLegacyAttempt(record);if(activeAttempt!==String(record.id))return false;await loadHistory();
                notice.className='text-muted small';notice.textContent='Оценка и комментарий сохранены';return true;
            }});
    }
    async function openAttempt(item, check) {
        if(item.kind==='legacy_attempt')return showLegacyAttempt(item, check);
        return show(item.id, {check});
    }
    async function show(id,options={}) {
        const selected = ++generation;
        stop();current=null;
        if(!options.snapshotOf){ensureAttemptCard({id,kind:'submission'});markSelectedAttempt(id);}
        beginResult();
        let record;
        try {record = await details.get('/insider/code-submissions/'+id+'?test_data=metadata');} catch(e) {
            if(selected!==generation || e.name==='AbortError')return;
            failResult(e.message,()=>show(id,options));return;
        }
        if (selected !== generation) return;
        if(options.refreshReviewSolution && ['completed','error'].includes(record.state)){
            options={...options,snapshotOf:await details.get(base+'/code-attempts/'+options.refreshReviewSolution)};
            if(selected!==generation)return;
        }
        const expectedUser=teacher && !ownMode ? selectedUser.id : root.dataset.viewerId;
        if((root.dataset.taskId && Number(record.task_id)!==Number(root.dataset.taskId)) || (root.dataset.courseId && Number(record.course_id)!==Number(root.dataset.courseId)) || Number(record.user_id)!==Number(expectedUser)){
            failResult('Эта сдача относится к другой задаче или ученику.',()=>show(id,options));return;
        }
        const sourceOnly=!!record.source_version_unknown || !!options.snapshotOf;
        if(!options.snapshotOf)ensureAttemptCard(record);
        current = {...record,snapshotOf:options.snapshotOf};
        const status = node('div',{role:'status','aria-live':'polite',class:'gc-execution-status'}, phases[record.progress?.phase || record.state] || record.state);
        const target=options.snapshotOf || record;
        const {header}=resultHeader(target,{sourceOnly:sourceOnly&&!options.snapshotOf,showSourceDate:false,reviewing:teacher&&!ownMode,compact:!teacher || ownWorkspace || inlineReview});
        const terminal=['completed','error','awaiting_review','imported_pending'].includes(record.state);
        const menu=teacher ? resultActions() : null;
        const body = node('section',{class:'gc-execution-result'},header);
        const stagedReport = record.check_type === combinedCheck && !terminal && !record.report ? node('div',{hidden:true}) : null;
        const contextBar=menu ? node('div',{class:'gc-result-context-bar'},menu.root) : null;
        if(teacher && !ownMode && (options.snapshotOf || !record.source_version_unknown)) {
            const remove=removeAttemptAction(options.snapshotOf || record);menu.add(remove);remove.classList.add('text-danger');
        }
        if(!terminal){header.hidden=!focusedVerdict && (record.awarded_xp===null || record.awarded_xp===undefined);body.append(status);}
        if(options.snapshotOf)renderAward(options.snapshotOf,body,{label:false});
        else if(!sourceOnly && record.state!=='error')renderAward(record,body,{label:false});
        if(contextBar)body.append(contextBar);
        if(record.state==='error'){
            body.append(node('p',{class:'gc-execution-error',role:'alert'},record.execution_error?.message || 'Проверяющий сервис не смог завершить работу. Решение сохранено.'));
            if(record.execution_error?.can_retry && !sourceOnly)body.append(button('Повторить проверку',safe(async()=>{await request(record.execution_error.retry_url,'POST',{});await show(id,options);await loadHistory();}),'primary'));
        }
        body.append(workPreview(record));
        if (!teacher && options.snapshotOf?.source_notice) body.append(node('p',{class:'gc-help-note'},options.snapshotOf.source_notice));
        const repository = record.repository;
        if (!teacher && repository?.url) body.append(node('p',{},download(repository.url,'Репозиторий GitHub')));
        if (!focusedVerdict && repository?.commit) body.append(node('p',{},`GitHub · ${repository.commit}`, ' ',download(`/insider/code-submissions/${id}/repository/archive`,'Скачать репозиторий')));
        const chatCheck = options.check;
        const viewingPreviousCheck = chatCheck?.source === id && chatCheck.run !== record.execution_id;
        if (viewingPreviousCheck) body.append(previousCheck(chatCheck));
        renderReport(record.report,body,id,{savedSource:!!options.snapshotOf,
            afterPurchase:viewingPreviousCheck ? () => selected === generation && show(id, options) : null});
        if(stagedReport)body.append(stagedReport);
        if(record.text_report_available)body.append(textReport(record));
        if (teacher && record.checker_diagnostics) body.append(node('details',{},node('summary',{},'Диагностика проверяющего модуля'),node('pre',{class:'gc-execution-output'},JSON.stringify(record.checker_diagnostics,null,2))));
        if (record.legacy) {
            if (record.legacy.requires_conversion || record.legacy.warnings.length) body.append(node('details',{class:'gc-report-provenance'},node('summary',{},'О материалах работы'),node('p',{},'Некоторые материалы этой сдачи нельзя показать в текущем формате. Доступные материалы приведены выше.')));
        }
        if (!teacher || ownMode) {
            if (teacher || record.is_public) {
                const sharing = button(record.is_public ? 'Закрыть публичный доступ' : 'Поделиться решением',safe(async () => {
                    if (!record.is_public && !confirm('Опубликовать отправленный текст и все файлы? Их сможет скачать любой, у кого есть ссылка. Тесты и критерии остаются закрытыми.')) return;
                    await request(`/insider/code-submissions/${id}/flags`,'PUT',{is_public:!record.is_public}); await show(id);
                }));
                if (menu) menu.add(sharing);
                else body.append(actions(sharing));
            }
            if (teacher && record.is_public) body.append(download(`/shared/code-submissions/${id}`,'Публичная ссылка'));
            if (!teacher && (!sourceOnly || options.snapshotOf)) {
                const review = reviewRequest(target, () => options.snapshotOf ? showLegacyAttempt(target) : show(id,options));
                if (review) body.append(review);
            }
        }
        if(menu && !sourceOnly && attemptItems.filter(item=>item.kind==='submission'&&!item.source_version_unknown).length>1)menu.add(button('Сравнить две сдачи',()=>compareDialog(record)));
        if (teacher && !ownMode && !sourceOnly) {
            if (target.review_request?.requested) {
                body.append(node('p',{class:'gc-review-request-note'},'Запрос ученика: '+target.review_request.comment));
                menu.add(button(record.teacher_viewed ? 'Отметить непросмотренным' : 'Отметить просмотренным',safe(async () => {
                    await request(`/insider/code-submissions/${id}/flags`,'PUT',{teacher_viewed:!record.teacher_viewed});
                    await show(id,options);
                    await loadHistory();
                })));
            }
            contextBar.prepend(authorshipPanel(record,async dismissed=>{await request(`/insider/code-submissions/${id}/integrity/review`,'PUT',{dismissed});await show(id,options);root.querySelector('[data-authorship-trigger]')?.focus({preventScroll:true});}));

        }
        if(teacher && !ownMode && sourceOnly)contextBar.prepend(authorshipPanel(record,null));
        const recheckType=record.recheck_type || record.check_type;
        if(teacher && !ownMode && terminal && record.state!=='error' && recheckType!=='manual'){
            const needsSelection=record.source_version_unknown && !options.snapshotOf?.solution_id;
            const normal=button(recheckType===combinedCheck?'Повторить тесты и проверку ИИ':
                usesAi(recheckType)?'Перепроверить обычной моделью':'Повторить автотесты',()=>recheckDialog(record,options));
            normal.disabled=needsSelection;if(needsSelection)normal.title='Выберите сдачу ученика в списке.';menu.add(normal);
            if(record.ai_recheck){const strong=button('Перепроверить усиленной моделью',()=>recheckDialog(record,options,true));strong.disabled=needsSelection || !record.ai_recheck.available;strong.title=needsSelection?'Выберите сдачу ученика в списке.':record.ai_recheck.reason || '';menu.add(strong);}
        }
        if(options.snapshotOf?.text && options.snapshotOf.text!==record.text)body.append(workPreview({...options.snapshotOf,mode:'text'}));
        const comment=target.teacher_feedback ? target.review_comment ?? target.comment ?? '' : target.review_comment || target.comment || (target.check_type!=='tests' && record.state!=='error'?record.comment:'') || '';
        const teacherComment=target.teacher_feedback||target.check_type==='manual';
        if(comment)body.append(feedbackPanel(comment,{title:teacherComment?'Комментарий преподавателя':usesAi(target.check_type)?'Разбор ИИ-проверки':'Комментарий проверки',collapsible:!teacherComment,open:true,meta:target.checked_at?new Date(target.checked_at).toLocaleString('ru-RU'):''}));
        if(record.criteria?.length && (!teacher || record.check_type===combinedCheck))body.append(criterionTable(record.criteria,true));
        if(options.snapshotOf?.can_grade && !ownMode)body.append(legacyGrading(options.snapshotOf,{comment:teacherComment?comment:''}));
        else if(teacher && !ownMode && !sourceOnly && terminal)body.append(inlineGrading({key:id,comment:teacherComment?comment:'',mark:record.points,onSkip:skipReview(record),maximum:record.maximum_points,ungraded:record.state==='awaiting_review',onSave:async(points,comment)=>{
            const savedGeneration=generation;
            if(record.state!=='awaiting_review' || record.check_type!=='manual')await request(`/insider/code-submissions/${id}/manual-review`,'POST',{});
            await request(`/insider/code-submissions/${id}/grade`,'PUT',{points,comment});if(savedGeneration!==generation)return false;await show(id,options);if(current?.id!==id)return false;await loadHistory();
            notice.className='text-muted small';notice.textContent='Оценка и комментарий сохранены';return true;
        }}));
        if (menu && record.runs.length) {
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
                        if(value.review_comment)result.append(node('p',{class:'gc-help-text'},'Комментарий к перепроверке: '+value.review_comment));
                        if(value.comment)result.append(feedbackPanel(value.comment,{title:'Комментарий проверки'}));
                        for(const file of value.files || [])result.append(node('p',{},download(file.download_url,file.name)));
                        if(value.report)renderReport(value.report,result,id);
                        if(value.check_type===combinedCheck && value.criteria?.length)result.append(criterionTable(value.criteria,true));
                        if(value.text_report_available)result.append(textReport(value));
                        loaded=true;
                    }catch(e){result.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку проверки',safe(load)));}
                    finally{loading=false;}
                };
                detail.addEventListener('toggle',() => {if(detail.open)load();});
                runs.append(detail);
            });
            menu.add(button('Журнал проверок этой сдачи',()=>{runs.open=true;openDialog('Проверки этой сдачи',runs);}));
        }
        if(terminal && (!teacher || ownMode) && !root.hasAttribute('data-history-only')){
            const complete=record.state==='completed' && record.maximum_points>0 && record.points>=record.maximum_points;
            const canRevise = !complete && !record.source_version_unknown && !config?.requires_conversion && root.dataset.blocked !== 'true';
            const controls = actions();
            if (canRevise) controls.append(button('Исправить решение', () => editDraft(record.id), 'primary'));
            if (complete && root.dataset.blocked !== 'true' && !config?.requires_conversion) {
                controls.append(button('Ещё одно решение', () => editDraft(), 'outline-secondary'));
            }
            if(complete && root.dataset.continueUrl)controls.append(node('a',{href:root.dataset.continueUrl,'data-workbook-tab-target':new URL(root.dataset.continueUrl).hash || null,class:'btn btn-primary'},root.dataset.continueLabel || 'Продолжить обучение'));
            if (controls.childElementCount) body.append(controls);
        }
        arrangeResult(body);finishResult(body);
        if (!['completed','error','awaiting_review','imported_pending'].includes(record.state)) {
            const progressBar=node('progress',{class:'gc-execution-progress','aria-label':'Ход проверки'});
            const phaseTitle=node('strong'),phaseHint=node('span',{class:'text-muted small'},'Результат появится здесь автоматически.');
            const stages = ['preparing', ...(record.check_type==='gpt'?[]:[...(record.language==='cpp'?['compiling']:[]),'testing']),
                ...(usesAi(record.check_type)?['grading']:[]),'publishing'];
            const phaseSteps=node('div',{class:'gc-execution-phases'},stages.map(phase=>node('span',{'data-phase':phase},phases[phase])));
            status.replaceChildren(node('i',{class:'fas fa-gear gc-execution-gear','aria-hidden':'true'}),node('div',{},phaseTitle,phaseHint),progressBar,phaseSteps);
            let loadingReport = false;
            const update=event=>{
                const phase=executionPhase(event);phaseTitle.textContent=phase.label;
                if(phase.total){progressBar.max=phase.total;progressBar.value=phase.completed;}else progressBar.removeAttribute('value');
                phaseSteps.querySelectorAll('[data-phase]').forEach(step=>step.classList.toggle('is-current',step.dataset.phase===event.phase));
                if(stagedReport?.hidden && !loadingReport && ['grading','publishing'].includes(event.phase)){
                    loadingReport=true;
                    request(`/insider/code-submissions/${id}/runs/${record.execution_id}?test_data=metadata`).then(value=>{
                        if(selected!==generation || !value.report)return;
                        renderReport(value.report,stagedReport,id);
                        stagedReport.hidden=false;
                    }).catch(()=>{}).finally(()=>{loadingReport=false;});
                }
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
        if (solutionsStatus && !append && !attemptItems.length) {
            taskAttemptList.replaceChildren();
            moreTaskAttempts.hidden = true;
            solutionsStatus.textContent = 'Загружаем сдачи…';
            solutionsStatus.hidden = false;
        }
        if(!append && !attemptItems.length)history.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем сдачи…'));
        let value;
        try {value = await request(base+'/code-submissions?'+query);} catch(e) {
            if(requestGeneration!==historyGeneration)return [];
            if(!append && !attemptItems.length)history.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить загрузку списка',safe(() => loadHistory())));
            if (solutionsStatus && !append) {
                solutionsStatus.replaceChildren(e.message, ' ', button('Повторить', safe(() => loadHistory())));
                solutionsStatus.hidden = false;
            }
            throw e;
        }
        if(requestGeneration!==historyGeneration)return [];
        if (taskOutcome && value.task_outcome) {
            const {tone, label, score} = value.task_outcome;
            const tab = document.getElementById('tasks-tab'+root.dataset.taskId)?.querySelector('[data-workbook-outcome]');
            if (tab && tone !== 'empty' && root.dataset.blocked !== 'true') {
                tab.className = 'gc-task-state gc-task-state--compact gc-task-state--'+value.task_outcome.tab_tone;
                tab.title = tab.querySelector('span').textContent = label;
                tab.querySelector('i').className = 'fas fa-'+value.task_outcome.icon;
            }
            taskOutcome.dataset.tone = tone;
            taskOutcome.querySelector('span').textContent = label;
            const points = taskOutcome.querySelector('small');
            points.textContent = score ?? '';
            points.hidden = score === null;
        }
        page=requestedPage;
        history.dataset.totalAttempts=String(value.total_attempts ?? value.total);
        const selectedSummary=attemptItems.find(item=>String(item.id)===String(activeAttempt));
        if(!append)attemptItems=[];
        const ids=new Set(attemptItems.map(item=>String(item.id)));attemptItems.push(...value.items.filter(item=>!ids.has(String(item.id))));
        if(selectedSummary&&!attemptItems.some(item=>String(item.id)===String(activeAttempt)))attemptItems.push(selectedSummary);
        attemptItems.sort((a,b)=>{const first=submittedOrder.indexOf(String(a.id)),second=submittedOrder.indexOf(String(b.id));return first>=0&&second>=0?first-second:first>=0?1:second>=0?-1:(Date.parse(b.created_at)||0)-(Date.parse(a.created_at)||0);});
        renderTaskHistory(value.has_more);
        if(teacher && !ownMode && !selectedUser?.name && value.items[0]?.user_name){selectedUser.name=value.items[0].user_name;chat?.setName(selectedUser.name);renderToolbar();}
        if(!attemptItems.length){
            history.replaceChildren();
            if(verdictHost && !teacher)verdictHost.replaceChildren(node('strong',{class:'gc-help-text'},'Решение не сдано'));
            return value.items;
        }
        const heading = node('div', {class:'gc-attempt-history__heading'});
        let picker;
        if (teacher && !ownWorkspace && !inlineReview) {
            heading.append(node('span', {}, ownMode ? 'Моё решение' : 'Сдача'));
            picker=select(activeAttempt || '',Object.fromEntries(attemptItems.map(item=>[String(item.id),choiceLabel(item)])),()=>{});
            picker.dataset.attemptPicker='';picker.setAttribute('aria-label','Выбрать попытку');
            picker.onchange=safe(()=>picker.value?openAttemptById(picker.value):showComposer());
            heading.append(attemptItems.length===1 ? node('strong',{},choiceLabel(attemptItems[0])) : picker);
        } else {
            heading.classList.add('gc-attempt-history__heading--steps');
            heading.append(node('div', {class:'gc-attempt-steps', role:'group', 'aria-label':inlineReview ? 'Сдачи ученика' : 'Мои сдачи'}, attemptItems.map(item => attemptStep(item))),
                node('time', {class:'gc-attempt-history__date', hidden:true}),
                node('span', {class:'gc-help-text gc-attempt-history__viewed', hidden:true}));
        }
        heading.append(node('span',{class:'gc-help-text gc-attempt-count'}));
        if(value.has_more)heading.append(button('Показать ещё сдачи',safe(()=>loadHistory(true))));
        const focused=history.contains(document.activeElement);
        const focusedId=focused ? document.activeElement.dataset.attemptId : null;
        history.replaceChildren(heading);markSelectedAttempt(activeAttempt);history.hidden=composerVisible;
        if(focused) {
            const steps = [...heading.querySelectorAll('.gc-attempt-step')];
            const targetId = focusedId ?? (append ? value.items[0]?.id : activeAttempt);
            const step = steps.find(item => item.dataset.attemptId === String(targetId)) || steps[0];
            (picker || step)?.focus({preventScroll:true});
            if(step)revealAttemptStep(step);
        } else revealAttemptStep();
        return value.items;
    }
    moreTaskAttempts?.addEventListener('click', safe(async () => {
        const previousCount = taskAttemptList.children.length;
        moreTaskAttempts.disabled = true;
        try {
            await loadHistory(true);
            taskAttemptList.children[previousCount]?.firstElementChild.focus();
        } finally {
            moreTaskAttempts.disabled = false;
        }
    }));
    const activate=()=>{if(ownMode || selectedUser || !teacher)writeContext(activeAttempt);};
    const controller={selectStudent,startOwnSubmission,openAttemptById,activate,openEditor:()=>{
        if (!teacher && root.dataset.blocked !== 'true' && (legacyForm || draft && !config?.requires_conversion)) openEditor();
        else if (!teacher && root.dataset.blocked !== 'true') notice.scrollIntoView({block:'center'});
    },context:async attempt=>{if(attempt && activeAttempt!==attempt)await openAttemptById(attempt);else activate();}};
    async function initializeStudent(){
        chat?.select(root.dataset.viewerId,'',!!legacyForm && root.dataset.blocked!=='true');
        if (legacyForm) {
            loadHistory().then(records => {
                if (activeAttempt) return;
                const initial = root.dataset.submission || records?.[0]?.id;
                if (initial) return openAttemptById(initial);
            }).catch(initializationError);
            return;
        }
        let records;
        if(root.hasAttribute('data-history-only'))records=await loadHistory();
        else{
            [,records]=await Promise.all([settings(),loadHistory()]);
            if(root.dataset.blocked!=='true' && !config.requires_conversion){
                if(!draft){setDraft(await request(draftUrl()));renderComposer();}
            }else if(!content.children.length)content.append(node('p',{class:'alert alert-warning'},config.requires_conversion ? 'Преподавателю нужно настроить проверку перед сдачей.' : 'Новые сдачи этой задачи заблокированы.'));
        }
        if(root.dataset.submission || activeAttempt)await openAttemptById(activeAttempt || root.dataset.submission);
        else if(records?.length)await openAttempt(records[0]);
        if (draft && root.dataset.blocked !== 'true' && !config?.requires_conversion) chat?.setCanSubmit(true);
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
            renderToolbar();content.hidden=true;
            if (!ownWorkspace && root.dataset.user) {
                if (teacherOwnChat) await startOwnSubmission({attempt:root.dataset.submission});
                else await selectStudent(selectedUser,{attempt:root.dataset.submission});
            }
        }else await initializeStudent();
    } catch(e){initializationError(e);}
    return controller;
}
