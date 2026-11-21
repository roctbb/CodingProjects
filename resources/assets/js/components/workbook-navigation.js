import * as bootstrap from 'bootstrap';

export const chatDesktop = () => matchMedia('(min-width: 992px)');

function readingHeading(condition, desktop) {
    const summary = condition.querySelector(':scope > summary');
    const current = condition.querySelector(':scope > .gc-task-reading-head');
    const intro = summary.querySelector('.gc-task-intro');
    if (desktop && !current && intro) {
        const head = document.createElement('div');
        head.className = 'gc-task-reading-head';
        head.append(intro);
        summary.after(head);
    } else if (!desktop && current) {
        const intro = current.querySelector('.gc-task-intro');
        if (intro) summary.insertBefore(intro, summary.querySelector(':scope > i'));
        current.remove();
    }
    if(desktop){
        condition.tabIndex=0;
        condition.setAttribute('aria-label','Условие задачи');
    }else{
        condition.removeAttribute('tabindex');
        condition.removeAttribute('aria-label');
    }
    condition.classList.toggle('gc-task-condition--reading', desktop);
}

export default function initWorkbookNavigation() {
    const workbook = document.querySelector('[data-step-details-page]');
    if (workbook) {
        const phone = matchMedia('(max-width: 767.98px)');
        const wide = matchMedia('(min-width: 1200px)');
        const desktop = chatDesktop();
        const conditions = workbook.querySelectorAll('[data-task-condition]');
        function applyConditionLayout() {
            for (const condition of conditions) {
                const selected = workbook.dataset.attemptTask === condition.dataset.taskCondition;
                if (condition.closest('.gc-chat-sheet')) {
                    condition.open = desktop.matches || !(selected || condition.hasAttribute('data-submitted'));
                    readingHeading(condition, desktop.matches);
                    const submitSlot = condition.querySelector('[data-task-submit-slot]');
                    if (submitSlot) {
                        const action = condition.closest('.gc-chat-sheet').querySelector('.gc-task-submit-action');
                        if (desktop.matches) submitSlot.append(action);
                        else condition.after(action);
                    }
                } else {
                    condition.open = wide.matches || !(selected || (phone.matches && condition.hasAttribute('data-submitted')));
                }
            }
        }
        applyConditionLayout();
        desktop.addEventListener('change', applyConditionLayout);
        phone.addEventListener('change', applyConditionLayout);
        wide.addEventListener('change', applyConditionLayout);

        function recordSubmission(event) {
            const condition = [...conditions].find(el => el.dataset.taskCondition === String(event.detail?.taskId));
            if (!condition || condition.hasAttribute('data-submitted')) return;
            condition.setAttribute('data-submitted', '');
            if (condition.closest('.gc-chat-sheet') ? !desktop.matches : phone.matches) condition.open = false;
        }
        workbook.addEventListener('gc:attempt-submitted', recordSubmission);
        document.addEventListener('gc:task-checked', recordSubmission);
        document.addEventListener('gc:task-checked', function (event) {
            if (!event.detail?.taskId) return;
            workbook.querySelectorAll('[data-task-state]').forEach(function (state) {
                if (state.dataset.taskState !== String(event.detail.taskId) || state.classList.contains('gc-task-state--danger')) return;
                const complete = state.dataset.taskComplete === 'true' ||
                    (event.detail.correct !== false && Number(event.detail.mark) === Number(state.dataset.taskMaximum));
                const label = complete ? 'Выполнено' : event.detail.exhausted ? 'Попытки исчерпаны' : 'Можно улучшить';
                const icon = complete ? 'circle-check' : event.detail.exhausted ? 'circle-xmark' : 'circle-half-stroke';
                state.dataset.taskComplete = String(complete);
                state.classList.remove('gc-task-state--pending', 'gc-task-state--complete', 'gc-task-state--neutral');
                state.classList.add(complete ? 'gc-task-state--complete' : 'gc-task-state--neutral');
                state.querySelector('i').className = 'fas fa-' + icon;
                state.querySelector('span').textContent = label;
                state.title = label;
            });
        });
        workbook.addEventListener('click', function (event) {
            const action = event.target.closest('[data-workbook-tab-target]');
            if (!action) return;
            const trigger = Array.from(workbook.querySelectorAll('[data-bs-toggle="pill"]')).find(tab => tab.getAttribute('href') === action.dataset.workbookTabTarget);
            if (!trigger) return;
            bootstrap.Tab.getOrCreateInstance(trigger).show();
            trigger.focus({ preventScroll: true });
            workbook.querySelector('.step-section-toolbar')?.scrollIntoView({ block: 'start', behavior: 'instant' });
        });
    }

    document.querySelectorAll('[data-workbook-navigation]').forEach(details => {
        const wide = window.matchMedia('(min-width: 992px)');
        const list = details.querySelector('nav');
        const revealCurrent = () => {
            if (!details.open) return;
            const current = list.querySelector('[aria-current="page"]');
            if (!current) return;
            const bounds = list.getBoundingClientRect();
            const item = current.getBoundingClientRect();
            if (item.top < bounds.top) list.scrollTop += item.top - bounds.top;
            else if (item.bottom > bounds.bottom) list.scrollTop += item.bottom - bounds.bottom;
        };
        const sync = () => {
            details.open = wide.matches;
            requestAnimationFrame(revealCurrent);
        };
        wide.addEventListener('change', sync);
        if (window.ResizeObserver) new ResizeObserver(revealCurrent).observe(list);
        else window.addEventListener('resize', () => requestAnimationFrame(revealCurrent), {passive: true});
        document.fonts.ready.then(revealCurrent);
        details.addEventListener('toggle', () => requestAnimationFrame(revealCurrent));
        sync();
    });
}
