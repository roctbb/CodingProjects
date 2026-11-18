export default function initFormActions() {
    function showFullscreenLoading(message) {
        if (document.querySelector('.gc-fullscreen-loading')) return;

        var overlay = document.createElement('div');
        overlay.className = 'gc-fullscreen-loading';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        var panel = document.createElement('div');
        var spinner = document.createElement('div');
        var title = document.createElement('div');
        var hint = document.createElement('div');
        panel.className = 'gc-fullscreen-loading__panel';
        spinner.className = 'gc-fullscreen-loading__spinner';
        spinner.setAttribute('aria-hidden', 'true');
        title.className = 'gc-fullscreen-loading__title';
        title.textContent = message || 'Идет обработка';
        hint.className = 'gc-fullscreen-loading__hint';
        hint.textContent = 'Это может занять немного времени. Страница откроется автоматически.';
        panel.appendChild(spinner);
        panel.appendChild(title);
        panel.appendChild(hint);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    }

    // Confirm dialogs
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-confirm], button[data-confirm]');
        if (link && !confirm(link.dataset.confirm)) event.preventDefault();
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-confirm], form[data-fullscreen-loading]')) return;

        if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }

        if (form.hasAttribute('data-fullscreen-loading')) {
            showFullscreenLoading(form.dataset.loadingMessage);
        }
    });

    // Check task forms
    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!form.matches('[data-check-task]')) return;
        event.preventDefault();
        const fields = form.querySelector('[data-quiz-fields]');
        if (fields.disabled) return;

        const attempts = form.querySelector('[data-quiz-attempts]');
        const message = form.querySelector('[data-quiz-error]');
        const taskId = form.dataset.taskId;
        const data = new URLSearchParams(new FormData(form));
        let exhausted = false;
        const updateAttempts = result => {
            if (result?.attempts_remaining == null) return;
            exhausted = result.attempts_remaining === 0;
            attempts.textContent = exhausted ? 'Попытки исчерпаны.' : `Осталось попыток: ${result.attempts_remaining} из ${result.max_attempts}.`;
        };

        fields.disabled = true;
        message.hidden = true;
        try {
            const response = await window.axios.post(form.action, data, { headers: { Accept: 'application/json' } });
            updateAttempts(response.data);
            const taskScore = document.getElementById('TSK_' + taskId);
            const taskComment = document.getElementById('TSK_COM_' + taskId);
            taskScore?.closest('[data-task-status-row]')?.classList.remove('d-none');
            if (taskScore) {
                taskScore.textContent = response.data.mark + ' XP';
                if (response.data.score_badge_class) {
                    taskScore.classList.remove('bg-body', 'bg-body-tertiary', 'solution-score-badge--special');
                    taskScore.classList.add(...response.data.score_badge_class.split(' ').filter(Boolean));
                }
            }
            if (taskComment) taskComment.textContent = response.data.comment || '';
            document.dispatchEvent(new CustomEvent('gc:task-checked', {
                detail: { taskId, mark: response.data.mark, correct: response.data.correct, exhausted },
            }));
        } catch (error) {
            const result = error.response?.data;
            updateAttempts(result);
            if (!exhausted) {
                message.textContent = Object.values(result?.errors ?? {})[0]?.[0] || result?.message || 'Не удалось отправить ответ. Ответ сохранён в форме.';
                message.hidden = false;
            }
        } finally {
            fields.disabled = exhausted;
        }
    });
}
