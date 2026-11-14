function showURLContext(){
    const url=new URL(location.href),task=url.searchParams.get('task'),student=url.searchParams.get('student'),attempt=url.searchParams.get('attempt');
    if(!/^\d+$/.test(task || '') || !/^\d+$/.test(student || ''))return;
    const roots=[...document.querySelectorAll('[data-execution-workspace]')].filter(root=>root.dataset.taskId===task);
    if(!roots.length)return;
    const teacher=roots.some(root=>root.dataset.teacher==='true'),own=student===roots[0].dataset.viewerId;
    const root=teacher ? roots.find(root=>own ? root.hasAttribute('data-execution-tab-workspace') : root.hasAttribute('data-execution-inline-review')) : roots[0];
    if(!root)return;
    if(attempt)root.dataset.submission=attempt;
    const taskTrigger=document.getElementById('tasks-tab'+task);
    if(taskTrigger)globalThis.bootstrap?.Tab.getOrCreateInstance(taskTrigger).show();
    if(teacher){
        const tab=document.getElementById((own?'own-solution-tab-':'student-solutions-tab-')+task);
        if(tab)globalThis.bootstrap?.Tab.getOrCreateInstance(tab).show();
        if(!own){
            const link=[...document.querySelectorAll('[data-execution-student]')].find(link=>link.dataset.studentId===student && link.dataset.executionStudent==='#'+root.id);
            if(link)link.setAttribute('aria-current','true');
            root.dispatchEvent(new CustomEvent('gc:execution-open',{detail:{id:student,name:link?.dataset.studentName || '',attempt}}));return;
        }
    }
    root.dispatchEvent(new CustomEvent('gc:execution-context',{detail:{attempt}}));
}

export default function initCodeChecking() {
    document.querySelectorAll('[data-execution-workspace]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = 'true';
        let ready, pendingIntent, listScroll=0;
        const list=root.closest('[data-execution-review-navigation]')?.querySelector('[data-execution-student-list]');
        const load = () => ready ||= import('../code-checking/workspace').then(module => module.workspace(root)).catch(error => {
            ready = null;
            root.replaceChildren();
            const message = document.createElement('p'); message.textContent = 'Не удалось загрузить рабочую область.';
            const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary'; retry.textContent = 'Повторить';
            retry.addEventListener('click', () => open(pendingIntent)); root.append(message,retry);
            throw error;
        });
        const open = async intent => {
            pendingIntent = intent;
            root.hidden = false;
            if(list && !list.hidden){listScroll=window.scrollY;list.hidden=true;}
            try {
                const controller = await load();
                if (!pendingIntent || root.hidden) return;
                const selected = pendingIntent; pendingIntent = null;
                if (selected.own) await controller.startOwnSubmission();
                else await controller.selectStudent(selected,{attempt:selected.attempt});
                if(!root.hidden)root.scrollIntoView({block:'start',behavior:'instant'});
            } catch { /* The workspace displays its retry action. */ }
        };
        root.addEventListener('gc:execution-open',event => open(event.detail));
        root.addEventListener('gc:execution-activate',() => {
            if(root.hasAttribute('data-execution-inline-review') && root.hidden)return;
            load().then(controller=>controller.activate()).catch(()=>{});
        });
        root.addEventListener('gc:execution-context',event=>load().then(async controller=>{
            await controller.context(event.detail.attempt);
            if(event.detail.attempt)root.querySelector('.gc-execution-result')?.scrollIntoView({block:'start',behavior:'instant'});
        }).catch(()=>{}));
        root.addEventListener('gc:execution-close',() => {
            root.hidden = true;
            const url=new URL(location.href);for(const key of ['student','attempt','task'])url.searchParams.delete(key);
            if(globalThis.history.state?.gcTaskReview)globalThis.history.back();else globalThis.history.replaceState(null,'',url);
            if(list){list.hidden=false;requestAnimationFrame(()=>window.scrollTo({top:listScroll,behavior:'instant'}));}
            document.querySelectorAll('[data-execution-student]').forEach(link => {
                if (link.dataset.executionStudent === '#'+root.id && link.getAttribute('aria-current') === 'true') {link.removeAttribute('aria-current');link.focus({preventScroll:true});}
            });
        });
        if (root.hasAttribute('data-execution-inline-review')) return;
        if (root.hasAttribute('data-execution-tab-workspace')) {
            if(root.closest('.tab-pane')?.classList.contains('active'))load().catch(() => {});
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
            event.preventDefault();
            document.querySelectorAll('[data-execution-student]').forEach(link => {
                if (link.dataset.executionStudent === target) link.removeAttribute('aria-current');
            });
            if (action.dataset.executionStudent) action.setAttribute('aria-current','true');
            if(action.dataset.executionStudent){
                const url=new URL(location.href);url.searchParams.set('student',action.dataset.studentId);url.searchParams.set('task',root.dataset.taskId);url.searchParams.delete('attempt');url.hash='task'+root.dataset.taskId;
                globalThis.history.pushState({gcTaskReview:true},'',url);
            }
            root.dispatchEvent(new CustomEvent('gc:execution-open',{detail:action.dataset.executionOwnSubmission ? {own:true} : {id:action.dataset.studentId,name:action.dataset.studentName}}));
            requestAnimationFrame(() => root.scrollIntoView({block:'nearest',behavior:'instant'}));
        });
    }
    document.querySelectorAll('[data-checker-editor]').forEach(root => {
        import('../code-checking/authoring').then(module => module.authoring(root)).catch(() => {
            root.textContent = 'Не удалось загрузить настройки проверки. Обновите страницу.';
        });
    });
    showURLContext();
}
