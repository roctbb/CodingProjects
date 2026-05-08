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

    buttons.forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.querySelector(button.dataset.courseStatsTarget);
            if (!panel) return;
            var shouldOpen = panel.classList.contains('d-none');
            panels.forEach(closePanel);
            buttons.forEach(function (b) { setButtonState(b, false); });
            if (shouldOpen) {
                panel.classList.remove('d-none');
                setSummaryState(button.dataset.courseStatsTarget, true);
                setButtonState(button, true);
            }
        });
    });
});
