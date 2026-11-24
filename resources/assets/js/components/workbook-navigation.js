export const chatDesktop = () => matchMedia('(min-width: 1200px)');

export function mountTaskChatPanel({sheet, opener, dialog, panel, onOpen, onPlace}) {
    const desktop = chatDesktop();
    function placePanel() {
        if (desktop.matches) {
            dialog.close();
            dialog.before(panel);
        } else {
            dialog.append(panel);
        }
        onPlace?.();
    }
    placePanel();
    desktop.addEventListener('change', placePanel);
    if (opener) {
        opener.addEventListener('click', () => {
            dialog.showModal();
            opener.setAttribute('aria-expanded', 'true');
            onOpen?.();
        });
        dialog.addEventListener('close', () => {
            if (!dialog.open) opener.setAttribute('aria-expanded', 'false');
        });
        opener.hidden = false;
    }
    const pane = sheet.closest('.step-content > .tab-pane');
    sheet.closest('.gc-workbook')?.addEventListener('hide.bs.tab', event => {
        if (pane && event.target.getAttribute('aria-controls') === pane.id) dialog.close();
    });
}

function readingHeading(condition, desktop) {
    if (desktop) condition.tabIndex = 0;
    else condition.removeAttribute('tabindex');
    if (condition instanceof HTMLDetailsElement) {
        if (desktop) condition.setAttribute('aria-label', 'Условие задачи');
        else condition.removeAttribute('aria-label');
    }
    condition.classList.toggle('gc-task-condition--reading', desktop);
}

function initChatControls(workbook, desktop) {
    const dividers = workbook.querySelectorAll('[data-task-chat-divider]');
    if (!dividers.length) return;
    const toggles = workbook.querySelectorAll('[data-task-chat-toggle]');

    const storageKey = 'geekclass.taskChatWidth';
    let preferredWidth, min, max, width;
    try { preferredWidth = Number(localStorage.getItem(storageKey)); } catch { /* Optional browser storage. */ }

    function renderWidth(requested) {
        const next = Math.round(Math.min(max, Math.max(min, requested)));
        if (width === next) return;
        width = next;
        workbook.style.setProperty('--gc-chat-panel-width', `${width}px`);
        for (const divider of dividers) divider.setAttribute('aria-valuenow', width);
    }

    function resize() {
        if (!desktop.matches) return;
        const rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
        min = Math.min(Math.round(20 * rem), Math.floor(innerWidth * .4));
        max = Math.floor(Math.min(56 * rem, innerWidth * .52));
        for (const divider of dividers) {
            divider.setAttribute('aria-valuemin', min);
            divider.setAttribute('aria-valuemax', max);
        }
        renderWidth(preferredWidth || Math.min(42 * rem, innerWidth * .38));
    }

    function saveWidth() {
        preferredWidth = width;
        try { localStorage.setItem(storageKey, width); } catch { /* Optional browser storage. */ }
    }

    for (const divider of dividers) {
        let drag;
        divider.addEventListener('pointerdown', event => {
            if (event.button !== 0 || !event.isPrimary) return;
            event.preventDefault();
            divider.setPointerCapture(event.pointerId);
            drag = {id: event.pointerId, x: event.clientX, width};
            divider.setAttribute('data-dragging', '');
        });
        divider.addEventListener('pointermove', event => {
            if (event.pointerId === drag?.id) renderWidth(drag.width + drag.x - event.clientX);
        });
        divider.addEventListener('lostpointercapture', event => {
            if (event.pointerId !== drag?.id) return;
            if (width !== drag.width) saveWidth();
            drag = null;
            divider.removeAttribute('data-dragging');
        });
        divider.addEventListener('keydown', event => {
            const values = {ArrowLeft: width + 24, ArrowRight: width - 24, Home: min, End: max};
            if (!Object.hasOwn(values, event.key)) return;
            event.preventDefault();
            const previous = width;
            renderWidth(values[event.key]);
            if (width !== previous) saveWidth();
        });
        divider.hidden = false;
    }
    for (const toggle of toggles) {
        toggle.addEventListener('click', () => {
            const collapsed = workbook.toggleAttribute('data-task-chat-collapsed');
            for (const button of toggles) {
                const label = `${collapsed ? 'Развернуть' : 'Свернуть'} ${button.dataset.taskChatToggle}`;
                button.setAttribute('aria-expanded', String(!collapsed));
                button.setAttribute('aria-label', label);
                button.title = label;
            }
        });
        toggle.hidden = false;
    }
    window.addEventListener('resize', resize);
    desktop.addEventListener('change', resize);
    resize();
}

export default function initWorkbookNavigation() {
    const workbook = document.querySelector('[data-step-details-page]');
    if (workbook) {
        const phone = matchMedia('(max-width: 767.98px)');
        const wide = matchMedia('(min-width: 1200px)');
        const desktop = chatDesktop();
        initChatControls(workbook, desktop);
        const conditions = workbook.querySelectorAll('[data-task-condition]');
        function applyConditionLayout() {
            for (const condition of conditions) {
                const selected = workbook.dataset.attemptTask === condition.dataset.taskCondition;
                const sheet = condition.closest('.gc-chat-sheet');
                if (sheet) {
                    if (condition instanceof HTMLDetailsElement && desktop.matches) condition.open = true;
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
        desktop.addEventListener('change', applyConditionLayout);
        phone.addEventListener('change', applyConditionLayout);
        wide.addEventListener('change', applyConditionLayout);

        function recordSubmission(event) {
            const condition = [...conditions].find(el => el.dataset.taskCondition === String(event.detail?.taskId));
            if (!(condition instanceof HTMLDetailsElement) || condition.hasAttribute('data-submitted')) return;
            condition.setAttribute('data-submitted', '');
            if (!condition.closest('.gc-chat-sheet') && phone.matches) condition.open = false;
        }
        workbook.addEventListener('gc:attempt-submitted', recordSubmission);
        document.addEventListener('gc:task-checked', recordSubmission);
        document.addEventListener('gc:task-checked', function (event) {
            if (!event.detail?.taskId) return;
            workbook.querySelectorAll('[data-task-state]').forEach(function (state) {
                if (state.hasAttribute('data-workbook-outcome')) return;
                if (state.dataset.taskState !== String(event.detail.taskId) || state.classList.contains('gc-task-state--danger')) return;
                const complete = state.dataset.taskComplete === 'true' ||
                    (event.detail.correct === true || (event.detail.correct !== false && Number(event.detail.mark) === Number(state.dataset.taskMaximum)));
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
            if (action instanceof HTMLAnchorElement && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0)) return;
            const trigger = Array.from(workbook.querySelectorAll('[data-bs-toggle="pill"]')).find(tab => tab.getAttribute('href') === action.dataset.workbookTabTarget);
            if (!trigger) return;
            event.preventDefault();
            action.closest('dialog[open]')?.close();
            const focusTarget = () => {
                const pane = document.getElementById(trigger.getAttribute('aria-controls'));
                const heading = action instanceof HTMLAnchorElement && pane?.querySelector('h2');
                if (heading) heading.tabIndex = -1;
                (heading || trigger).focus({preventScroll: true});
                workbook.querySelector('.step-section-toolbar')?.scrollIntoView({block: 'start', behavior: 'instant'});
            };
            if (trigger.classList.contains('active')) focusTarget();
            else {
                trigger.addEventListener('shown.bs.tab', focusTarget, {once: true});
                trigger.click();
            }
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
