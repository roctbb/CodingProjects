import { Tab } from 'bootstrap';

// Catalog interactions live here; order and wallet pagination remains server rendered.
export default function initMarketWorkspace() {
    const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е');
    const banner = document.querySelector('[data-market-promotion-banner]');
    const syncBanner = () => {
        if (banner) banner.hidden = !['market-goods', 'market-digital'].includes(document.querySelector('#marketTabs .nav-link.active')?.getAttribute('aria-controls'));
    };
    document.getElementById('marketTabs')?.addEventListener('shown.bs.tab', syncBanner);
    syncBanner();
    document.querySelectorAll('[data-market-title-input]').forEach(input => {
        const preview = input.closest('[data-market-good]')?.querySelector('[data-market-title-preview]');
        input.addEventListener('input', () => { if (preview) preview.textContent = input.value.trim() || 'Ваше звание'; });
    });
    document.querySelectorAll('[data-market-search]').forEach(function (input) {
        let grid = document.querySelector(input.dataset.marketGrid);
        if (!grid) return;

        let wrapper = input.closest('[data-market-controls]') || input.closest('.input-group');
        let goods = Array.from(grid.querySelectorAll('[data-market-good]'));
        let counter = wrapper ? wrapper.querySelector('[data-market-count]') : null;
        let clearButton = wrapper ? wrapper.querySelector('[data-market-clear]') : null;
        let availability = wrapper?.querySelector('[data-market-availability]');
        let promotionOnly = wrapper?.querySelector('[data-market-promotion-only]');
        let categoryButtons = Array.from(document.querySelectorAll('[data-market-category-filter]')).filter(function (button) {
            return button.dataset.marketGrid === input.dataset.marketGrid;
        });
        let emptyState = document.createElement('div');

        emptyState.className = 'gc-card market-search-empty text-center text-muted p-4 d-none';
        emptyState.innerHTML = '<div class="bg-body-tertiary text-muted rounded-circle d-inline-flex align-items-center justify-content-center fs-4 p-3 mb-3"><i class="fas fa-search"></i></div><h5>Ничего не найдено</h5><p class="mx-auto mb-0">Попробуйте изменить запрос или категорию.</p>';
        const reset = document.createElement('button');
        reset.type = 'button'; reset.className = 'btn btn-outline-primary btn-sm mt-3'; reset.textContent = 'Сбросить фильтры';
        emptyState.append(reset);
        grid.after(emptyState);

        let applyMarketFilter = function () {
            let query = normalize(input.value.trim());
            let activeCategory = categoryButtons.find(function (button) {
                return button.classList.contains('is-active');
            })?.dataset.marketCategoryFilter || 'all';
            let visibleCount = 0;

            goods.forEach(function (good) {
                let haystack = normalize(good.dataset.marketGoodText || good.textContent || '');
                let matchesQuery = !query || haystack.indexOf(query) !== -1;
                let matchesCategory = activeCategory === 'all' || good.dataset.marketGoodCategory === activeCategory;
                let matchesAvailability = !availability || availability.value !== 'available' || good.dataset.marketAvailable === 'true';
                let matchesPromotion = !promotionOnly?.checked || good.dataset.marketDiscounted === 'true';
                let isVisible = matchesQuery && matchesCategory && matchesAvailability && matchesPromotion;
                good.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyState.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + goods.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        const resetFilters = (discounted = false) => {
            input.value = '';
            if (availability) availability.value = 'all';
            if (promotionOnly) promotionOnly.checked = discounted;
            categoryButtons.forEach(button => {
                const active = button.dataset.marketCategoryFilter === 'all';
                button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', String(active));
            });
            applyMarketFilter();
        };
        reset.addEventListener('click', () => { resetFilters(); input.focus(); });
        document.querySelectorAll('[data-market-promotion-link]').forEach(link => {
            if (`#market-${link.dataset.marketPromotionLink}-grid` !== input.dataset.marketGrid) return;
            link.addEventListener('click', event => {
                event.preventDefault(); resetFilters(true);
                const tab = document.getElementById(`market-${link.dataset.marketPromotionLink}-tab`);
                if (tab) { Tab.getOrCreateInstance(tab).show(); tab.focus({preventScroll:true}); }
            });
        });
        input.addEventListener('input', applyMarketFilter);
        availability?.addEventListener('change', applyMarketFilter);
        promotionOnly?.addEventListener('change', applyMarketFilter);
        categoryButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                categoryButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                    item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
                });
                applyMarketFilter();
            });
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyMarketFilter();
            });
        }

        applyMarketFilter();
    });
}
