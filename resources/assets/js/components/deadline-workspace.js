function sortDate(row) {
    const { deadlineNextCutoff, deadlineLast } = row.dataset;
    return deadlineNextCutoff ? Number(deadlineNextCutoff) : -Number(deadlineLast);
}

export default function initDeadlineWorkspaces() {
    document.querySelectorAll('[data-deadline-workspace]').forEach(workspace => {
        const upcoming = workspace.querySelector('[data-deadline-agenda="upcoming"]');
        const missed = workspace.querySelector('[data-deadline-agenda="missed"]');
        if (!upcoming || !missed) return;

        workspace.addEventListener('gc:deadline-change', event => {
            const row = event.target.closest('[data-deadline-row]');
            if (!row) return;

            const next = String(event.detail.next?.cutoff ?? '');
            if (next === row.dataset.deadlineNextCutoff) return;
            row.dataset.deadlineNextCutoff = next;
            row.querySelector('[data-deadline-last-label]').hidden = next !== '';

            const group = next ? upcoming : missed;
            const targetList = group.querySelector('[data-deadline-list]');
            const previousList = row.parentElement;
            const before = [...targetList.children].find(other => other !== row
                && (sortDate(other) - sortDate(row) || Number(other.dataset.deadlineTaskId) - Number(row.dataset.deadlineTaskId)) > 0) ?? null;

            if (targetList !== previousList || row.nextElementSibling !== before) {
                const focused = row.contains(document.activeElement) ? document.activeElement : null;
                group.hidden = false;
                targetList.insertBefore(row, before);
                previousList.closest('[data-deadline-agenda]').hidden = !previousList.childElementCount;
                focused?.focus({ preventScroll: true });
            }
        });
    });
}
