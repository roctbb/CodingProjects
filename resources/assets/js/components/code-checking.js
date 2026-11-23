import {chatDesktop, mountTaskChatPanel} from './workbook-navigation';

const reviewChoices = '[data-execution-student], [data-execution-own-submission]';
const reviewRoots = '[data-execution-inline-review], [data-execution-own-workspace]';
const reviewPanel = root => root.closest('.gc-task-own-workspace') || root;
const choiceTarget = link => link.dataset.executionStudent || link.dataset.executionOwnSubmission;

function revealAttempt(root, attempt) {
    if (!attempt || new URL(location.href).searchParams.get('attempt') !== attempt || !root.getClientRects().length) return;
    const heading = root.closest('.gc-student-attempt')?.querySelector('.gc-task-title-row, [data-attempt-verdict] > header');
    if (heading) {
        const bounds = heading.getBoundingClientRect();
        if (bounds.bottom <= 0 || bounds.top >= innerHeight) heading.scrollIntoView({block:'center',behavior:'instant'});
        return;
    }
    const status = root.querySelector('.gc-result-verdict__title, [role="alert"]') || root;
    const bounds = status.getBoundingClientRect();
    if (bounds.top < 60 || bounds.bottom > window.innerHeight - 32) {
        root.scrollIntoView({block: 'start', behavior: 'instant'});
    }
}

function showURLContext(initialContext) {
    const query = new URL(location.href).searchParams;
    const attempt = query.get('attempt');
    const task = query.get('task') || initialContext?.attemptTask;
    const student = query.get('student') || initialContext?.attemptAuthor;
    if(!/^\d+$/.test(task || ''))return;
    const roots=[...document.querySelectorAll('[data-execution-workspace]')].filter(root=>root.dataset.taskId===task);
    if(!roots.length)return;
    const taskTrigger=document.getElementById('tasks-tab'+task);
    if(taskTrigger)globalThis.bootstrap?.Tab.getOrCreateInstance(taskTrigger).show();
    if(!/^\d+$/.test(student || ''))return;
    const teacher=roots.some(root=>root.dataset.teacher==='true'),own=student===roots[0].dataset.viewerId;
    const bound = roots.find(root => root.dataset.user === student && root.dataset.submission === attempt);
    const root=bound || (teacher ? roots.find(root=>own ? root.hasAttribute('data-execution-own-workspace') : root.hasAttribute('data-execution-inline-review')) : roots[0]);
    if(!root)return;
    root.dataset.submission=attempt || '';
    const navigation = root.closest('[data-execution-review-navigation]');
    if (navigation) {
        const link = [...navigation.querySelectorAll(reviewChoices)]
            .find(link => choiceTarget(link) === '#' + root.id && link.dataset.studentId === student);
        root.dispatchEvent(new CustomEvent('gc:execution-open', {
            detail: {id: student, name: link?.dataset.studentName || '', own, attempt},
        }));
        return;
    }
    root.dispatchEvent(new CustomEvent('gc:execution-context',{detail:{attempt}}));
}

function activeReview(navigation) {
    return [...navigation.querySelectorAll(reviewRoots)].find(root => !reviewPanel(root).hidden);
}

function syncTeacherChat(navigation) {
    const list = navigation.querySelector('[data-execution-student-list]');
    const dialog = navigation.querySelector('.gc-teacher-chat-dialog');
    const desktop = chatDesktop().matches;
    const active = activeReview(navigation);
    list.hidden = desktop && !!active;
    if (!desktop && active && navigation.getClientRects().length) {
        if (!dialog.open) dialog.showModal();
    } else if (dialog.open) dialog.close();
}

function initTeacherChat(dialog) {
    dialog.dataset.chatMounted = '';
    const navigation = dialog.parentElement;
    const sheet = dialog.closest('.gc-task-sheet');
    const panel = navigation.querySelector('[data-teacher-chat-panel]');
    mountTaskChatPanel({sheet, dialog, panel, onPlace: () => syncTeacherChat(navigation)});
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        activeReview(navigation)?.dispatchEvent(new CustomEvent('gc:execution-close'));
    });
    dialog.addEventListener('close', () => {
        if (dialog.open || chatDesktop().matches) return;
        activeReview(navigation)?.dispatchEvent(new CustomEvent('gc:execution-close', {
            detail: {fromHistory: true, restoreFocus: false},
        }));
    });
}

export default function initCodeChecking() {
    for (const dialog of document.querySelectorAll('.gc-teacher-chat-dialog:not([data-chat-mounted])')) {
        initTeacherChat(dialog);
    }
    document.querySelectorAll('[data-quiz-student-for]').forEach(select=>{
        if(select.dataset.connected)return;
        select.dataset.connected='true';
        const root=document.getElementById(select.dataset.quizStudentFor);
        select.addEventListener('change',()=>root.dispatchEvent(select.value ?
            new CustomEvent('gc:execution-open',{detail:{id:select.value,name:select.selectedOptions[0].text}}) :
            new CustomEvent('gc:execution-close')));
        root.addEventListener('gc:execution-close',()=>{select.value='';});
        root.addEventListener('gc:execution-open',event=>{select.value=event.detail.id;select.closest('details').open=true;});
    });
    document.querySelectorAll('[data-execution-workspace]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = 'true';
        let ready, pendingIntent, listScroll=null, openSequence=0;
        const navigation = root.closest('[data-execution-review-navigation]');
        const list = navigation?.querySelector('[data-execution-student-list]');
        const panel = reviewPanel(root);
        const load = () => ready ||= import('../code-checking/workspace').then(module => module.workspace(root)).catch(error => {
            ready = null;
            root.replaceChildren();
            const message = document.createElement('p'); message.textContent = 'Не удалось загрузить рабочую область.';
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary'; retry.textContent = 'Повторить';
            retry.addEventListener('click', () => open(pendingIntent)); root.append(message,retry);
            if(list){const back=document.createElement('button');back.type='button';back.className='btn btn-outline-secondary';back.textContent='К списку работ';back.addEventListener('click',()=>root.dispatchEvent(new CustomEvent('gc:execution-close')));root.append(back);}
            throw error;
        });
        const submitButton = root.closest('.gc-task-sheet')?.querySelector('[data-task-compose="'+root.id+'"]');
        submitButton?.addEventListener('click', async () => {
            submitButton.disabled = true;
            try {
                const controller = await load();
                submitButton.disabled = false;
                submitButton.focus({preventScroll:true});
                controller.openEditor();
            }
            catch { root.scrollIntoView({block:'center'}); }
            finally { submitButton.disabled = false; }
        });
        const open = async intent => {
            pendingIntent = intent;const sequence=++openSequence;
            if (list) {
                for (const other of navigation.querySelectorAll(reviewRoots)) {
                    if (other !== root && !reviewPanel(other).hidden) {
                        other.dispatchEvent(new CustomEvent('gc:execution-close', {detail: {switching: true}}));
                    }
                }
                for (const link of list.querySelectorAll(reviewChoices)) {
                    if (choiceTarget(link) === '#' + root.id && link.dataset.studentId === intent.id) {
                        link.setAttribute('aria-current', 'true');
                    } else {
                        link.removeAttribute('aria-current');
                    }
                }
                if (intent.fromList) listScroll = window.scrollY;
            }
            panel.hidden = false;
            if (navigation) syncTeacherChat(navigation);
            try {
                const controller = await load();
                if (sequence !== openSequence || panel.hidden) return;
                const selected = intent;
                if (selected.own) await controller.startOwnSubmission({attempt: selected.attempt});
                else await controller.selectStudent(selected,{attempt:selected.attempt});
                if (sequence === openSequence && !selected.fromList) revealAttempt(root, selected.attempt);
                if (sequence === openSequence && selected.fromList && !navigation && !chatDesktop().matches) {
                    const heading = root.querySelector('.gc-chat-head') || root;
                    const bounds = heading.getBoundingClientRect();
                    if (bounds.top < 0 || bounds.bottom > innerHeight) heading.scrollIntoView({block:'start',behavior:'instant'});
                }
            } catch { /* The workspace displays its retry action. */ }
        };
        root.addEventListener('gc:execution-open',event => open(event.detail));
        root.addEventListener('gc:execution-navigate',event=>{
            const links=[...document.querySelectorAll('[data-execution-student]')].filter(link=>link.dataset.executionStudent==='#'+root.id && !link.classList.contains('d-none') && !link.hasAttribute('data-without-attempts'));
            const index=links.findIndex(link=>String(link.dataset.studentId)===String(pendingIntent?.id));
            if(links.length>1)links[index<0 ? (event.detail.direction<0?links.length-1:0) : (index+(event.detail.direction<0?links.length-1:1))%links.length].click();
        });
        root.addEventListener('gc:execution-activate',() => {
            if (navigation && panel.hidden) return;
            load().then(controller=>controller.activate()).catch(()=>{});
        });
        root.addEventListener('gc:execution-context',event=>load().then(async controller=>{
            await controller.context(event.detail.attempt);
            revealAttempt(root, event.detail.attempt);
        }).catch(()=>{}));
        root.addEventListener('gc:execution-close', event => {
            openSequence++;panel.hidden = true;
            if (event.detail?.switching) return;
            if (!event.detail?.fromHistory) {
                const url=new URL(location.href);for(const key of ['student','attempt'])url.searchParams.delete(key);
                url.searchParams.set('task',root.dataset.taskId);url.hash='task'+root.dataset.taskId;
                globalThis.history.replaceState(null,'',url);
            }
            if (list) {
                syncTeacherChat(navigation);
                const choice = list.querySelector('[aria-current="true"]');
                choice?.removeAttribute('aria-current');
                if (event.detail?.restoreFocus !== false) {
                    choice?.focus({preventScroll: listScroll !== null || chatDesktop().matches});
                    if (listScroll !== null) window.scrollTo({top: listScroll, behavior: 'instant'});
                }
                listScroll = null;
            }
        });
        if (root.hasAttribute('data-execution-inline-review')) return;
        if (root.hasAttribute('data-execution-own-workspace')) {
            if (!panel.hidden) open({own: true, id: root.dataset.viewerId});
            return;
        }
        if (root.closest('.gc-task-sheet--student')) {
            const pane = root.closest('.step-content > .tab-pane');
            if (!pane || pane.classList.contains('active')) load().catch(() => {});
            return;
        }
        if (!globalThis.IntersectionObserver) {load().catch(() => {}); return;}
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {observer.disconnect(); load().catch(() => {});}
        },{rootMargin:'200px'});
        observer.observe(root);
    });
    if (!document.documentElement.hasAttribute('data-execution-navigation-ready')) {
        document.documentElement.setAttribute('data-execution-navigation-ready','');
        document.addEventListener('shown.bs.tab',event => {
            if (event.target.matches('.task-pill')) {
                const pane = document.getElementById(event.target.getAttribute('aria-controls'));
                pane?.querySelector('.gc-task-sheet--student [data-execution-workspace]')?.dispatchEvent(new CustomEvent('gc:execution-activate'));
            }
        });
        window.addEventListener('popstate',()=>{
            showURLContext();
            if (!new URL(location.href).searchParams.has('student')) {
                const pane = document.getElementById(location.hash.slice(1));
                for (const root of pane?.querySelectorAll(reviewRoots) || []) {
                    if (!reviewPanel(root).hidden) {
                        root.dispatchEvent(new CustomEvent('gc:execution-close', {detail: {fromHistory: true}}));
                    }
                }
            }
        });
        document.addEventListener('click',event => {
            const taskTab=event.target.closest('.task-pill, #theory-tab');
            if(taskTab){const url=new URL(location.href);for(const key of ['student','attempt','task'])url.searchParams.delete(key);globalThis.history.replaceState(null,'',url);}
            const batch=event.target.closest('[data-execution-recheck-latest]');
            if(batch){
                event.preventDefault();
                const root=document.querySelector(batch.dataset.executionRecheckLatest);
                if(!root || !confirm('Перепроверить последние решения всех учеников с текущими настройками? Прежние оценки сохранятся до нового результата.'))return;
                const feedback=document.querySelector(`[data-execution-status-for="${root.id}"]`);
                batch.disabled=true;
                if(feedback){feedback.hidden=false;feedback.textContent='Запускаем проверки…';}
                import('../code-checking/ui').then(({request})=>request(root.dataset.endpoint+'/code-submissions/recheck-latest','POST',{latest_configuration:true})).then(value=>{
                    if(feedback)feedback.textContent=`Проверок запущено: ${value.execution_ids.length}. Уже выполняются: ${value.skipped_active.length}.`;
                }).catch(error=>{if(feedback)feedback.textContent=error.message;}).finally(()=>{batch.disabled=false;});
                return;
            }
            const action = event.target.closest(reviewChoices);
            if (!action || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            const root = document.querySelector(choiceTarget(action));
            if (!root) return;
            const alreadyOpen = !reviewPanel(root).hidden;
            event.preventDefault();
            const {studentId: id, studentName: name} = action.dataset;
            const url=new URL(location.href);url.searchParams.set('student',id);url.searchParams.set('task',root.dataset.taskId);url.searchParams.delete('attempt');url.hash='task'+root.dataset.taskId;
            globalThis.history[alreadyOpen?'replaceState':'pushState']({gcTaskReview:true},'',url);
            root.dispatchEvent(new CustomEvent('gc:execution-open', {
                detail: {id, name, own: action.hasAttribute('data-execution-own-submission'), fromList: !alreadyOpen},
            }));
        });
    }
    document.querySelectorAll('[data-checker-editor]').forEach(root => {
        import('../code-checking/authoring').then(module => module.authoring(root)).catch(() => {
            root.textContent = 'Не удалось загрузить настройки проверки. Обновите страницу.';
        });
    });
    showURLContext(document.querySelector('[data-attempt-task]')?.dataset);
}
