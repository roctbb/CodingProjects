import * as bootstrap from 'bootstrap';

export default function initWorkbookNavigation() {
    const workbook = document.querySelector('[data-step-details-page]');
    if (workbook) {
        document.addEventListener('gc:task-checked', function (event) {
            workbook.querySelectorAll('[data-task-state]').forEach(function (state) {
                if (state.dataset.taskState !== String(event.detail.taskId) || state.classList.contains('gc-task-state--danger')) return;
                const complete = state.dataset.taskComplete === 'true' || Number(event.detail.mark) === Number(state.dataset.taskMaximum);
                const label = complete ? 'Выполнено' : 'Можно улучшить';
                state.dataset.taskComplete = String(complete);
                state.classList.remove('gc-task-state--pending', 'gc-task-state--complete', 'gc-task-state--neutral');
                state.classList.add(complete ? 'gc-task-state--complete' : 'gc-task-state--neutral');
                state.querySelector('i').className = 'fas fa-' + (complete ? 'circle-check' : 'circle-half-stroke');
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
