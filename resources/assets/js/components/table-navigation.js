const initialized = new WeakSet();

export function revealTableRow(row) {
    const viewport = row?.closest('.table-responsive');
    if (!viewport) return;
    const bounds = viewport.getBoundingClientRect();
    const rect = row.getBoundingClientRect();
    const top = bounds.top + (row.closest('table').tHead?.getBoundingClientRect().height || 0);
    if (rect.top < top) viewport.scrollTop += rect.top - top;
    else if (rect.bottom > bounds.bottom) viewport.scrollTop += rect.bottom - bounds.bottom;
}

export default function initTableNavigation(root = document) {
    root.querySelectorAll('[data-table-navigation]').forEach(controls => {
        if (initialized.has(controls)) return;
        const table = root.querySelector(`#${controls.dataset.tableNavigation}`);
        const viewport = table?.closest('.table-responsive');
        if (!viewport) return;
        initialized.add(controls);
        const columns = [...table.querySelectorAll('thead [data-table-column]')];
        const previous = controls.querySelector('[data-table-previous]');
        const next = controls.querySelector('[data-table-next]');
        const visibleColumns = () => {
            const bounds = viewport.getBoundingClientRect();
            const left = bounds.left + (table.querySelector('thead .assessment-sticky-col')?.getBoundingClientRect().width || 0);
            return columns.filter(column => { const rect = column.getBoundingClientRect(); return rect.right > left + 2 && rect.left < bounds.right - 2; });
        };
        const update = () => {
            controls.hidden = viewport.scrollWidth <= viewport.clientWidth + 2;
            previous.disabled = viewport.scrollLeft <= 2;
            next.disabled = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
            const visible = visibleColumns();
            const first = columns.indexOf(visible[0]) + 1;
            const last = columns.indexOf(visible.at(-1)) + 1;
            controls.querySelector('[data-table-range]').textContent = visible.length ? `${first === last ? first : `${first}–${last}`} из ${columns.length}` : '';
        };
        const reveal = column => {
            if (!column) return;
            const rect = column.getBoundingClientRect();
            const bounds = viewport.getBoundingClientRect();
            const left = bounds.left + (table.querySelector('thead .assessment-sticky-col')?.getBoundingClientRect().width || 0);
            if (rect.left < left || rect.right > bounds.right) viewport.scrollLeft += rect.left - left;
            update();
        };
        previous.addEventListener('click', () => {
            const visible = visibleColumns();
            reveal(columns[Math.max(0, columns.indexOf(visible[0]) - Math.max(1, visible.length - 1))]);
        });
        next.addEventListener('click', () => {
            const visible = visibleColumns();
            const index = columns.indexOf(visible.at(-1)) + 1;
            if (index >= columns.length) viewport.scrollLeft = viewport.scrollWidth;
            else reveal(columns[index]);
            update();
        });
        viewport.addEventListener('scroll', update, { passive: true });
        table.addEventListener('gc:reveal-column', event => reveal(event.detail));
        if (window.ResizeObserver) new ResizeObserver(update).observe(viewport);
        document.fonts?.ready.then(update);
        update();
    });
}
