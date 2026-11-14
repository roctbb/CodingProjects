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
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('[data-check-task]')) return;
        event.preventDefault();
        var taskId = form.dataset.taskId;
        var text = form.querySelector('[name=text]').value;
        window.axios.post(form.action, 'text=' + encodeURI(text))
            .then(function (response) {
                var taskScore = document.getElementById('TSK_' + taskId);
                var taskComment = document.getElementById('TSK_COM_' + taskId);
                var taskStatusRow = taskScore ? taskScore.closest('[data-task-status-row]') : null;
                if (taskStatusRow) taskStatusRow.classList.remove('d-none');
                if (taskScore) {
                    taskScore.textContent = response.data.mark + ' XP';
                    if (response.data.score_badge_class) {
                        taskScore.classList.remove('bg-body', 'bg-body-tertiary', 'solution-score-badge--special');
                        response.data.score_badge_class.split(' ').forEach(function (className) {
                            if (className) taskScore.classList.add(className);
                        });
                    }
                }
                if (taskComment) taskComment.textContent = response.data.comment || '';
                document.dispatchEvent(new CustomEvent('gc:task-checked', { detail: { taskId, mark: response.data.mark } }));
            });
    });
}
