import {chatDesktop} from './workbook-navigation';

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
    const root=bound || (teacher ? roots.find(root=>own ? root.hasAttribute('data-execution-tab-workspace') : root.hasAttribute('data-execution-inline-review')) : roots[0]);
    if(!root)return;
    root.dataset.submission=attempt || '';
    if (attempt) {
        const condition = root.closest('.gc-task-sheet')?.querySelector('.gc-task-condition-disclosure');
        if (condition && !condition.closest('.gc-task-sheet--tutor')) {
            const sideBySide = condition.closest('.gc-chat-sheet') ? chatDesktop().matches : matchMedia('(min-width: 1200px)').matches;
            if (!sideBySide) condition.open = false;
        }
    }
    if(teacher && !bound){
        const tab=document.getElementById((own?'own-solution-tab-':'student-solutions-tab-')+task);
        if(tab)globalThis.bootstrap?.Tab.getOrCreateInstance(tab).show();
        if(!own){
            document.querySelectorAll('[data-execution-student]').forEach(link=>{if(link.dataset.executionStudent==='#'+root.id)link.removeAttribute('aria-current');});
            const link=[...document.querySelectorAll('[data-execution-student]')].find(link=>link.dataset.studentId===student && link.dataset.executionStudent==='#'+root.id);
            if(link)link.setAttribute('aria-current','true');
            root.dispatchEvent(new CustomEvent('gc:execution-open',{detail:{id:student,name:link?.dataset.studentName || '',attempt}}));return;
        }
    }
    root.dispatchEvent(new CustomEvent('gc:execution-context',{detail:{attempt}}));
}

export default function initCodeChecking() {
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
        const list=root.closest('[data-execution-review-navigation]')?.querySelector('[data-execution-student-list]');
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
            root.hidden = false;
            if(list && !list.hidden){listScroll=intent.fromList ? window.scrollY : null;list.hidden=true;}
            try {
                const controller = await load();
                if (sequence!==openSequence || root.hidden) return;
                const selected = intent;
                if (selected.own) await controller.startOwnSubmission();
                else await controller.selectStudent(selected,{attempt:selected.attempt});
                if (sequence === openSequence && !selected.fromList) revealAttempt(root, selected.attempt);
                if (sequence === openSequence && selected.fromList && !chatDesktop().matches) {
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
            if(root.hasAttribute('data-execution-inline-review') && root.hidden)return;
            load().then(controller=>controller.activate()).catch(()=>{});
        });
        root.addEventListener('gc:execution-context',event=>load().then(async controller=>{
            await controller.context(event.detail.attempt);
            revealAttempt(root, event.detail.attempt);
        }).catch(()=>{}));
        root.addEventListener('gc:execution-close',() => {
            openSequence++;root.hidden = true;
            const url=new URL(location.href);for(const key of ['student','attempt'])url.searchParams.delete(key);
            url.searchParams.set('task',root.dataset.taskId);url.hash='task'+root.dataset.taskId;
            globalThis.history.replaceState(null,'',url);
            if(list){list.hidden=false;requestAnimationFrame(()=>listScroll===null ? list.scrollIntoView({block:'start',behavior:'instant'}) : window.scrollTo({top:listScroll,behavior:'instant'}));}
            document.querySelectorAll('[data-execution-student]').forEach(link => {
                if (link.dataset.executionStudent === '#'+root.id && link.getAttribute('aria-current') === 'true') {link.removeAttribute('aria-current');link.focus({preventScroll:true});}
            });
        });
        if (root.hasAttribute('data-execution-inline-review')) return;
        if (root.hasAttribute('data-execution-tab-workspace')) {
            if(root.closest('.tab-pane')?.classList.contains('active'))load().catch(() => {});
            return;
        }
        if (root.closest('.gc-task-sheet--tutor')) {
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
            const target=event.target.dataset.executionTabTarget;
            if(target)document.querySelector(target)?.dispatchEvent(new CustomEvent('gc:execution-activate'));
            if (event.target.matches('.task-pill')) {
                const pane = document.getElementById(event.target.getAttribute('aria-controls'));
                pane?.querySelector('.gc-task-sheet--tutor [data-execution-workspace]')?.dispatchEvent(new CustomEvent('gc:execution-activate'));
            }
        });
        window.addEventListener('popstate',()=>{
            if(new URL(location.href).searchParams.has('student'))showURLContext();
            else{
                const task=location.hash.match(/^#task(\d+)$/)?.[1];
                const tab=document.getElementById('student-solutions-tab-'+task);
                if(tab)globalThis.bootstrap?.Tab.getOrCreateInstance(tab).show();
                const root=document.getElementById('execution-workspace-'+task);
                if(root?.hasAttribute('data-execution-inline-review') && !root.hidden)root.dispatchEvent(new CustomEvent('gc:execution-close'));
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
            const action = event.target.closest('[data-execution-student], [data-execution-own-submission]');
            if (!action || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
            const target = action.dataset.executionStudent || action.dataset.executionOwnSubmission;
            const root = document.querySelector(target);
            if (!root) return;
            const alreadyOpen=!root.hidden;
            event.preventDefault();
            document.querySelectorAll('[data-execution-student]').forEach(link => {
                if (link.dataset.executionStudent === target) link.removeAttribute('aria-current');
            });
            if (action.dataset.executionStudent) action.setAttribute('aria-current','true');
            if(action.dataset.executionStudent){
                const url=new URL(location.href);url.searchParams.set('student',action.dataset.studentId);url.searchParams.set('task',root.dataset.taskId);url.searchParams.delete('attempt');url.hash='task'+root.dataset.taskId;
                globalThis.history[alreadyOpen?'replaceState':'pushState']({gcTaskReview:true},'',url);
            }
            root.dispatchEvent(new CustomEvent('gc:execution-open',{detail:action.dataset.executionOwnSubmission ? {own:true} : {id:action.dataset.studentId,name:action.dataset.studentName,fromList:!alreadyOpen}}));

        });
    }
    document.querySelectorAll('[data-checker-editor]').forEach(root => {
        import('../code-checking/authoring').then(module => module.authoring(root)).catch(() => {
            root.textContent = 'Не удалось загрузить настройки проверки. Обновите страницу.';
        });
    });
    showURLContext(document.querySelector('[data-attempt-task]')?.dataset);
}
