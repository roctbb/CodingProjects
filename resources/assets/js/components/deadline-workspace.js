export default function initDeadlineWorkspaces() {
    document.querySelectorAll('[data-deadline-workspace]').forEach(workspace => {
        const search = workspace.querySelector('[data-deadline-search]');
        if (!search) return;
        const chapter = workspace.querySelector('[data-deadline-chapter]');
        const clear = workspace.querySelector('[data-deadline-clear]');
        const rows = [...workspace.querySelectorAll('[data-deadline-row]')];
        const panel = workspace.querySelector('[data-deadline-search-panel]');
        const list = workspace.querySelector('[data-deadline-list]');
        const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е').trim();
        const searchText = new Map(rows.map(row => [row, normalize(row.dataset.deadlineSearchText)]));
        const restore = () => {
            const params = new URL(location.href).searchParams;
            search.value = (params.get('q') || '').slice(0, 120);
            const saved = params.get('deadline_chapter');
            chapter.value = [...chapter.options].some(option => option.value === saved) ? saved : 'all';
            panel.open = Boolean(search.value || chapter.value !== 'all');
        };
        const filter = (remember = true) => {
            const query = normalize(search.value);
            let count = 0;
            rows.forEach(row => {
                const inScope = searchText.get(row).includes(query) && (chapter.value === 'all' || row.dataset.deadlineChapterId === chapter.value);
                row.hidden = !inScope;
                if (!row.hidden) count++;
            });
            workspace.querySelector('[data-deadline-count]').textContent = `Показано ${count} из ${rows.length}`;
            workspace.querySelector('[data-deadline-empty]').hidden = count !== 0;
            workspace.querySelector('[data-deadline-agenda]').hidden = count === 0;
            clear.classList.toggle('d-none', !search.value);
            if (remember) {
                const url = new URL(location.href);
                url.searchParams.delete('deadline_state');
                for (const [key, value, empty] of [['q', search.value.trim(), ''], ['deadline_chapter', chapter.value, 'all']]) {
                    if (value === empty) url.searchParams.delete(key); else url.searchParams.set(key, value);
                }
                history.replaceState(null, '', url);
            }
        };
        const sort = () => {
            const key = row => [row.dataset.deadlineNextCutoff ? 0 : 1,
                row.dataset.deadlineNextCutoff ? Number(row.dataset.deadlineNextCutoff) : -Number(row.dataset.deadlineLast), Number(row.dataset.deadlineTaskId)];
            rows.sort((a, b) => { const aa = key(a), bb = key(b); for (let i = 0; i < aa.length; i++) if (aa[i] !== bb[i]) return aa[i] - bb[i]; return 0; });
            rows.forEach(row => list.appendChild(row));
        };
        search.addEventListener('input', () => filter());
        chapter.addEventListener('change', () => filter());
        clear.addEventListener('click', () => { search.value = ''; filter(); search.focus(); });
        workspace.querySelector('[data-deadline-reset]').addEventListener('click', () => {
            search.value = ''; chapter.value = 'all'; filter(); panel.open = false; panel.querySelector('summary').focus();
        });
        workspace.addEventListener('gc:deadline-change', event => {
            const row = event.target.closest('[data-deadline-row]');
            if (!row) return;
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
