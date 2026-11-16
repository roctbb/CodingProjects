export default function initDeadlineWorkspaces() {
    document.querySelectorAll('[data-deadline-workspace]').forEach(workspace => {
        const search = workspace.querySelector('[data-deadline-search]');
        if (!search) return;
        const chapter = workspace.querySelector('[data-deadline-chapter]');
        const clear = workspace.querySelector('[data-deadline-clear]');
        const rows = [...workspace.querySelectorAll('[data-deadline-row]')];
        const filters = [...workspace.querySelectorAll('[data-deadline-filter]')];
        const list = workspace.querySelector('[data-deadline-list]');
        const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е').trim();
        const searchText = new Map(rows.map(row => [row, normalize(row.dataset.deadlineSearchText)]));
        let state = 'all';
        const matches = (row, selected) => {
            const done = row.dataset.deadlineDone === 'true';
            return selected === 'all' || selected === 'pending' && !done || selected === 'done' && done
                || selected === 'penalty' && !done && Number(row.dataset.deadlinePenalty) > 0
                || selected === 'upcoming' && row.dataset.deadlineNextCutoff !== '';
        };
        const restore = () => {
            const params = new URL(location.href).searchParams;
            search.value = (params.get('q') || '').slice(0, 120);
            const saved = params.get('deadline_chapter');
            chapter.value = [...chapter.options].some(option => option.value === saved) ? saved : 'all';
            state = filters.some(button => button.dataset.deadlineFilter === params.get('deadline_state')) ? params.get('deadline_state') : 'all';
        };
        const filter = (remember = true) => {
            const query = normalize(search.value);
            let count = 0;
            const counts = Object.fromEntries(filters.map(button => [button.dataset.deadlineFilter, 0]));
            rows.forEach(row => {
                const inScope = searchText.get(row).includes(query) && (chapter.value === 'all' || row.dataset.deadlineChapterId === chapter.value);
                if (inScope) for (const key of Object.keys(counts)) if (matches(row, key)) counts[key]++;
                row.hidden = !inScope || !matches(row, state);
                if (!row.hidden) count++;
            });
            workspace.querySelector('[data-deadline-count]').textContent = `Показано ${count} из ${rows.length}`;
            workspace.querySelector('[data-deadline-empty]').hidden = count !== 0;
            workspace.querySelector('[data-deadline-agenda]').hidden = count === 0;
            clear.classList.toggle('d-none', !search.value);
            filters.forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.deadlineFilter === state));
                button.querySelector('[data-deadline-filter-count]').textContent = counts[button.dataset.deadlineFilter];
            });
            if (remember) {
                const url = new URL(location.href);
                for (const [key, value, empty] of [['q', search.value.trim(), ''], ['deadline_state', state, 'all'], ['deadline_chapter', chapter.value, 'all']]) {
                    if (value === empty) url.searchParams.delete(key); else url.searchParams.set(key, value);
                }
                history.replaceState(null, '', url);
            }
        };
        const sort = () => {
            const key = row => [row.dataset.deadlineDone === 'true' ? 1 : 0, row.dataset.deadlineNextCutoff ? 0 : 1,
                row.dataset.deadlineNextCutoff ? Number(row.dataset.deadlineNextCutoff) : -Number(row.dataset.deadlineLast), Number(row.dataset.deadlineTaskId)];
            rows.sort((a, b) => { const aa = key(a), bb = key(b); for (let i = 0; i < aa.length; i++) if (aa[i] !== bb[i]) return aa[i] - bb[i]; return 0; });
            rows.forEach(row => list.appendChild(row));
        };
        filters.forEach(button => button.addEventListener('click', () => { state = button.dataset.deadlineFilter; filter(); }));
        search.addEventListener('input', () => filter());
        chapter.addEventListener('change', () => filter());
        clear.addEventListener('click', () => { search.value = ''; filter(); search.focus(); });
        workspace.querySelector('[data-deadline-reset]').addEventListener('click', () => {
            search.value = ''; state = 'all'; chapter.value = 'all'; filter(); search.focus();
        });
        workspace.addEventListener('gc:deadline-change', event => {
            const row = event.target.closest('[data-deadline-row]');
            if (!row) return;
            row.dataset.deadlinePenalty = event.detail.penalty;
            const next = event.detail.next?.cutoff ?? '';
            const moved = String(next) !== row.dataset.deadlineNextCutoff;
            row.dataset.deadlineNextCutoff = next;
            const last = row.querySelector('[data-deadline-last-label]');
            if (last) last.hidden = next !== '';
            if (moved) sort();
            filter(false);
        });
        window.addEventListener('popstate', () => { restore(); filter(false); });
        restore(); filter(false);
    });
}
