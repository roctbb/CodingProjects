export function renderDeadlineStrip(deadlines, {escape, formatDate, lessonName, indexFor, openDates = new Set()}) {
    if (!deadlines.length) return '';
    const days = new Map();
    for (const deadline of deadlines) {
        if (!days.has(deadline.due_on)) days.set(deadline.due_on, []);
        days.get(deadline.due_on).push(deadline);
    }
    const pointsLabel = entries => {
        const units = new Map();
        const seen = new Set();
        for (const {points, resource_key, key} of entries) {
            if (seen.has(resource_key || key)) continue;
            seen.add(resource_key || key);
            if (!points?.unit || points.value === null || points.value === undefined || !Number.isFinite(Number(points.value))) continue;
            units.set(points.unit, (units.get(points.unit) || 0) + Number(points.value));
        }
        return [...units].map(([unit, value]) => `${value.toLocaleString('ru-RU')} ${unit === 'codingprojects.xp' ? 'XP' : unit}`).join(' · ');
    };
    const groups = [...days].sort(([a], [b]) => a.localeCompare(b)).map(([day, entries]) => {
        const names = entries.slice(0, 2).map(entry => entry.title).join(' · ');
        const totals = pointsLabel(entries);
        const rows = entries.map(entry => {
            const lesson = lessonName(entry.lesson_id);
            const points = pointsLabel([entry]);
            const stage = entry.time ? `${entry.time} · ${Math.round(entry.penalty * 100)}%` : '';
            return `<button type="button" class="planner-deadline-row" data-action="calendar-deadline" data-deadline="${indexFor(entry)}"><span>${escape(entry.title)}${lesson ? `<small>${escape(lesson)}</small>` : ''}${stage ? `<small>${escape(stage)}</small>` : ''}</span>${points ? `<span class="planner-deadline-points">${escape(points)}</span>` : ''}</button>`;
        }).join('');
        return `<details class="planner-deadline-group" data-deadline-date="${escape(day)}" ${openDates.has(day) ? 'open' : ''}><summary><time datetime="${escape(day)}">${escape(formatDate(day))}</time><span class="planner-deadline-preview">${escape(names)}${entries.length > 2 ? ` · ещё ${entries.length - 2}` : ''}</span><span class="planner-deadline-count">Работ: ${entries.length}${totals ? ` · ${escape(totals)}` : ''}</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary><div class="planner-deadline-items">${rows}</div></details>`;
    }).join('');
    return `<section class="planner-week-deadlines" aria-label="Сроки сдачи на этой неделе"><h3><i class="fas fa-flag" aria-hidden="true"></i> Сроки недели</h3><div class="planner-deadline-groups">${groups}</div></section>`;
}
