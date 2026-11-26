function sortDate(row) {
    const { deadlineNextCutoff, deadlineLast } = row.dataset;
    return deadlineNextCutoff ? Number(deadlineNextCutoff) : -Number(deadlineLast);
}

export default function initDeadlineWorkspaces() {
    document.querySelectorAll('[data-deadline-workspace]').forEach(workspace => {
        const upcoming = workspace.querySelector('[data-deadline-agenda="upcoming"]');
        const missed = workspace.querySelector('[data-deadline-agenda="missed"]');
        if (!upcoming || !missed) return;
        const empty = workspace.querySelector('[data-deadline-empty]');
        const student = workspace.hasAttribute('data-deadline-student');

        workspace.addEventListener('gc:deadline-change', event => {
            const row = event.target.closest('[data-deadline-row]');
            if (!row) return;

            const next = String(event.detail.next?.cutoff ?? '');
            const hidden = student && event.detail.fullyPenalized;
            if (next === row.dataset.deadlineNextCutoff && hidden === row.hidden) return;
            if (hidden) row.querySelector('[popover]:popover-open')?.hidePopover();
            row.hidden = hidden;
            row.dataset.deadlineNextCutoff = next;
            row.querySelector('[data-deadline-last-label]').hidden = next !== '';

            const targetList = (next ? upcoming : missed).querySelector('[data-deadline-list]');
            const previousList = row.parentElement;
            const before = [...targetList.children].find(other => other !== row
                && (sortDate(other) - sortDate(row) || Number(other.dataset.deadlineTaskId) - Number(row.dataset.deadlineTaskId)) > 0) ?? null;
            let focused;

            if (targetList !== previousList || row.nextElementSibling !== before) {
                if (!hidden && row.contains(document.activeElement)) focused = document.activeElement;
                targetList.insertBefore(row, before);
            }

            upcoming.hidden = !upcoming.querySelector('[data-deadline-row]:not([hidden])');
            missed.hidden = !missed.querySelector('[data-deadline-row]:not([hidden])');
            empty.hidden = !upcoming.hidden || !missed.hidden;
            focused?.focus({ preventScroll: true });
        });
    });
}
