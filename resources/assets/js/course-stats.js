document.addEventListener('DOMContentLoaded', function () {
    const panels = document.querySelectorAll('[data-course-stats-panel]');
    const buttons = document.querySelectorAll('[data-course-stats-toggle]');
    const summaries = document.querySelectorAll('[data-course-stats-summary]');

    function setSummaryState(target, isOpen) {
        summaries.forEach(function (summary) {
            if (summary.dataset.courseStatsSummary === target) {
                summary.classList.toggle('d-none', isOpen);
            }
        });
    }
    function closePanel(panel) { panel.classList.add('d-none'); setSummaryState('#' + panel.id, false); }
    function setButtonState(button, isOpen) {
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        var label = button.querySelector('[data-course-stats-label]');
        if (label) label.textContent = isOpen ? 'Скрыть статистику' : 'Статистика';
    }

    const requests = new WeakMap();
    async function load(panel, page = 1) {
        requests.get(panel)?.abort();
        const request = new AbortController();
        requests.set(panel, request);
        panel.setAttribute('aria-busy', 'true');
        if (!panel.children.length) panel.textContent = 'Загрузка статистики…';
        try {
            const url = new URL(panel.dataset.courseStatsUrl);
            url.searchParams.set('page', page);
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok) throw new Error('Статистика недоступна');
            const data = await response.json();
            if (request.signal.aborted) return;
            panel.innerHTML = data.html;
            panel.dataset.courseStatsLoaded = 'true';
            panel.querySelectorAll('[data-progress-width]').forEach(bar => {
                bar.style.width = Math.max(0, Math.min(100, parseFloat(bar.dataset.progressWidth) || 0)) + '%';
            });
        } catch (error) {
            if (error.name === 'AbortError') return;
            panel.replaceChildren();
            const message = document.createElement('p');
            message.className = 'gc-help-text';
            message.textContent = 'Не удалось загрузить статистику.';
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-outline-secondary btn-sm';
            retry.textContent = 'Повторить';
            retry.dataset.courseStatsPage = page;
            panel.append(message, retry);
        } finally {
            if (requests.get(panel) === request) panel.removeAttribute('aria-busy');
        }
    }
    panels.forEach(panel => panel.addEventListener('click', event => {
        const button = event.target.closest('[data-course-stats-page]');
        if (button && !button.disabled) load(panel, Number(button.dataset.courseStatsPage));
    }));

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.querySelector(button.dataset.courseStatsTarget);
            if (!panel) return;
            var shouldOpen = panel.classList.contains('d-none');
            panels.forEach(closePanel);
            buttons.forEach(function (b) { setButtonState(b, false); });
            if (shouldOpen) {
                panel.classList.remove('d-none');
                if (!panel.dataset.courseStatsLoaded) load(panel);
                setSummaryState(button.dataset.courseStatsTarget, true);
                setButtonState(button, true);
            }
        });
    });
});
