export default function initDeadlineWorkspaces() {
    document.querySelectorAll('[data-deadline-workspace]').forEach(workspace => {
        const search = workspace.querySelector('[data-deadline-search]');
        if (!search) return;
        const rows = [...workspace.querySelectorAll('[data-deadline-row]')];
        const filters = [...workspace.querySelectorAll('[data-deadline-filter]')];
        const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е').trim();
        const searchText = new Map(rows.map(row => [row, normalize(row.dataset.deadlineSearchText)]));
        let state = 'all';
        const filter = () => {
            const query = normalize(search.value);
            let count = 0;
            rows.forEach(row => {
                const rowState = row.dataset.deadlineState;
                const matchesState = state === 'all' || rowState === state
                    || (state === 'pending' && rowState !== 'done')
                    || (state === 'upcoming' && ['today', 'soon', 'upcoming'].includes(rowState));
                row.hidden = !matchesState || !searchText.get(row).includes(query);
                if (!row.hidden) count++;
            });
            workspace.querySelectorAll('[data-deadline-lesson], [data-deadline-day], [data-deadline-month]').forEach(group => {
                const visible = group.querySelectorAll('[data-deadline-row]:not([hidden])').length;
                group.hidden = visible === 0;
                const countLabel = group.querySelector('[data-deadline-day-count]');
                if (group.hasAttribute('data-deadline-day') && countLabel) countLabel.textContent = String(visible);
            });
            workspace.querySelectorAll('[data-deadline-month-link]').forEach(link => {
                link.hidden = [...workspace.querySelectorAll('[data-deadline-month]')].find(month => month.dataset.deadlineMonth === link.dataset.deadlineMonthLink)?.hidden ?? true;
            });
            const monthLinks = workspace.querySelector('[data-deadline-month-links]');
            if (monthLinks) monthLinks.hidden = count === 0;
            workspace.querySelector('[data-deadline-count]').textContent = `Показано ${count} из ${rows.length}`;
            workspace.querySelector('[data-deadline-empty]').hidden = count !== 0;
            filters.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.deadlineFilter === state)));
        };
        filters.forEach(button => button.addEventListener('click', () => {
            state = button.dataset.deadlineFilter;
            filter();
        }));
        search.addEventListener('input', filter);
        workspace.querySelector('[data-deadline-reset]').addEventListener('click', () => {
            search.value = '';
            state = 'all';
            filter();
            search.focus();
        });
    });
}
