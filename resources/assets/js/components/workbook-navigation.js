import * as bootstrap from 'bootstrap';

export const chatDesktop = () => matchMedia('(min-width: 992px)');

function readingHeading(condition, desktop) {
    if (desktop) condition.tabIndex = 0;
    else condition.removeAttribute('tabindex');
    if (condition instanceof HTMLDetailsElement) {
        if (desktop) condition.setAttribute('aria-label', 'Условие задачи');
        else condition.removeAttribute('aria-label');
    }
    condition.classList.toggle('gc-task-condition--reading', desktop);
}

// Use the same bottom edge in the roster, student review and personal chat.
function fitDesktopPanes(workbook, desktop) {
    const main = workbook.querySelector('.step-main');
    const sheets = [...main.querySelectorAll('.gc-chat-sheet:not(.gc-task-sheet--tutor)')];
    if (!sheets.length) return;
    function measure() {
        if (!desktop.matches) return;
        const available = document.documentElement.clientHeight - parseFloat(getComputedStyle(main).paddingBottom);
        for (const sheet of sheets) {
            const condition = sheet.querySelector(':scope > [data-task-condition]');
            if (!condition?.getClientRects().length) continue;
            const top = condition.getBoundingClientRect().top + window.scrollY;
            const height = Math.max(320, Math.floor(available - top));
            sheet.style.setProperty('--gc-pane-height', height + 'px');
            const modeBar = sheet.querySelector('.gc-task-review-controls');
            if (modeBar) {
                const paneHeight = height - modeBar.offsetHeight;
                modeBar.nextElementSibling.style.setProperty('--gc-pane-height', paneHeight + 'px');
            }
        }
    }
    let frame;
    function schedule() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(measure);
    }
    const observer = new ResizeObserver(schedule);
    for (const element of workbook.querySelectorAll('.gc-workbook-header, .step-section-toolbar, .gc-chat-sheet, .gc-task-title-row, .gc-task-review-controls')) {
        observer.observe(element);
    }
    window.addEventListener('resize', schedule);
    workbook.addEventListener('shown.bs.tab', schedule);
    measure();
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
                const sheet = condition.closest('.gc-chat-sheet');
                if (sheet) {
                    if (condition instanceof HTMLDetailsElement && (desktop.matches || !sheet.classList.contains('gc-task-sheet--tutor'))) {
                        condition.open = desktop.matches || !(selected || condition.hasAttribute('data-submitted'));
                    }
                    readingHeading(condition, desktop.matches);
                    const submitSlot = condition.querySelector('[data-task-submit-slot]');
                    if (submitSlot) {
                        const action = sheet.querySelector('.gc-task-submit-action');
                        if (desktop.matches) submitSlot.append(action);
                        else condition.after(action);
                    }
                } else {
                    condition.open = wide.matches || !(selected || (phone.matches && condition.hasAttribute('data-submitted')));
                }
            }
        }
        applyConditionLayout();
        fitDesktopPanes(workbook, desktop);
        desktop.addEventListener('change', applyConditionLayout);
        phone.addEventListener('change', applyConditionLayout);
        wide.addEventListener('change', applyConditionLayout);

        function recordSubmission(event) {
            const condition = [...conditions].find(el => el.dataset.taskCondition === String(event.detail?.taskId));
            if (!(condition instanceof HTMLDetailsElement) || condition.hasAttribute('data-submitted')) return;
            condition.setAttribute('data-submitted', '');
            if (condition.closest('.gc-task-sheet--tutor')) return;
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
