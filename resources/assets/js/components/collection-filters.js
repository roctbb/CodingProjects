export default function initCollectionFilters() {
    document.querySelectorAll('.gc-filters--scroll').forEach(strip => {
        const revealSelected = () => {
            if (!strip.getClientRects().length) return;
            const current = strip.querySelector('[aria-current="page"], .is-active');
            if (!current) return;
            const bounds = strip.getBoundingClientRect(), item = current.getBoundingClientRect();
            if (item.left < bounds.left || item.right > bounds.right) {
                strip.scrollLeft += item.left - bounds.left - (bounds.width - item.width) / 2;
            }
        };
        new ResizeObserver(revealSelected).observe(strip);
        document.fonts?.ready.then(revealSelected);
    });
    document.querySelectorAll('[data-navigate-select]').forEach(function (select) { select.addEventListener('change', function () { if (select.value) window.location.assign(select.value); }); });
    document.querySelectorAll('[data-blocked-search]').forEach(function (input) {
        var table = document.querySelector(input.dataset.blockedTable);
        if (!table) return;

        var wrapper = input.closest('.blocked-toolbar') || input.closest('.input-group');
        var rows = Array.from(table.querySelectorAll('[data-blocked-row]'));
        var counter = wrapper ? wrapper.querySelector('[data-blocked-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-blocked-clear]') : null;
        var tbody = table.querySelector('tbody');
        var emptyRow = document.createElement('tr');
        var firstRow = rows[0];
        var columnCount = firstRow ? firstRow.children.length : 1;

        emptyRow.className = 'blocked-empty-row d-none';
        emptyRow.innerHTML = '<td colspan="' + columnCount + '" class="text-center text-muted py-4">Ничего не найдено</td>';
        if (tbody) tbody.appendChild(emptyRow);

        var applyBlockedFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            rows.forEach(function (row) {
                var haystack = (row.dataset.blockedSearchText || row.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                row.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyRow.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + rows.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyBlockedFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyBlockedFilter();
            });
        }
    });

    document.querySelectorAll('[data-step-progress-search]').forEach(function (input) {
        var grid = document.querySelector(input.dataset.stepProgressGrid);
        if (!grid) return;

        var wrapper = input.closest('.step-progress-card') || input.closest('.input-group');
        var cards = Array.from(grid.querySelectorAll('[data-step-progress-card]'));
        var counter = wrapper ? wrapper.querySelector('[data-step-progress-count]') : null;
        var emptyState = wrapper ? wrapper.querySelector('[data-step-progress-empty]') : null;

        var applyStepProgressFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            cards.forEach(function (card) {
                var haystack = (card.dataset.stepProgressText || card.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                card.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            if (counter) counter.textContent = visibleCount + ' из ' + cards.length;
            if (emptyState) emptyState.classList.toggle('d-none', visibleCount !== 0);
        };

        input.addEventListener('input', applyStepProgressFilter);
        applyStepProgressFilter();
    });

}
