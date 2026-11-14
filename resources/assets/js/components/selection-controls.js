export default function initSelectionControls() {
    document.querySelectorAll('select[data-selected-count]').forEach(function (select) {
        var target = document.querySelector(select.dataset.selectedCount);
        if (!target) return;

        var updateSelectedCount = function () {
            var selectedCount = Array.from(select.selectedOptions || []).length;
            target.textContent = selectedCount + ' выбрано';
            target.classList.toggle('is-empty', selectedCount === 0);
        };

        select.addEventListener('change', updateSelectedCount);
        updateSelectedCount();
    });

    document.querySelectorAll('select[data-enhanced-multiselect][multiple]').forEach(function (select) {
        if (select.dataset.enhancedMultiselectReady === '1') return;
        select.dataset.enhancedMultiselectReady = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'gc-multiselect bootstrap-select show-tick dropdown';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gc-multiselect__toggle btn btn-light dropdown-toggle w-100';
        button.setAttribute('data-bs-toggle', 'dropdown');
        button.setAttribute('data-bs-auto-close', 'outside');
        button.setAttribute('aria-expanded', 'false');
        button.id = select.id + '-control';
        const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
        if (label) { label.id ||= select.id+'-label'; label.htmlFor=button.id; button.setAttribute('aria-labelledby',label.id+' '+select.id+'-value'); }

        var filterOption = document.createElement('span');
        filterOption.className = 'filter-option';

        var filterOptionInner = document.createElement('span');
        filterOptionInner.className = 'filter-option-inner';

        var buttonText = document.createElement('span');
        buttonText.id = select.id+'-value';
        buttonText.className = 'filter-option-inner-inner gc-multiselect__label text-truncate';
        filterOptionInner.appendChild(buttonText);
        filterOption.appendChild(filterOptionInner);
        button.appendChild(filterOption);

        var menu = document.createElement('div');
        menu.id = select.id+'-choices'; button.setAttribute('aria-controls', menu.id);
        menu.className = 'gc-multiselect__menu dropdown-menu w-100 p-2';

        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm gc-multiselect__search';
        search.placeholder = select.dataset.searchPlaceholder || 'Найти';
        search.autocomplete = 'off';
        search.setAttribute('aria-label',select.dataset.searchPlaceholder || 'Найти вариант');
        menu.appendChild(search);

        var actions = document.createElement('div');
        actions.className = 'gc-multiselect__actions';

        var selectVisible = document.createElement('button');
        selectVisible.type = 'button';
        selectVisible.className = 'btn btn-sm btn-link px-0';
        selectVisible.textContent = 'Выбрать все';

        var clearVisible = document.createElement('button');
        clearVisible.type = 'button';
        clearVisible.className = 'btn btn-sm btn-link px-0 text-muted';
        clearVisible.textContent = 'Снять';

        actions.appendChild(selectVisible);
        actions.appendChild(clearVisible);
        menu.appendChild(actions);

        var list = document.createElement('div');
        list.className = 'gc-multiselect__list';
        menu.appendChild(list);

        var empty = document.createElement('div');
        empty.className = 'gc-multiselect__empty text-muted small text-center py-3 d-none';
        empty.textContent = 'Ничего не найдено';
        menu.appendChild(empty);

        var options = Array.from(select.options || []).map(function (option) {
            var item = document.createElement('label');
            item.className = 'gc-multiselect__option dropdown-item';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.checked = option.selected;
            checkbox.disabled = option.disabled;

            var text = document.createElement('span');
            text.className = 'gc-multiselect__option-text text-truncate';
            text.textContent = option.textContent.trim();

            var check = document.createElement('span');
            check.className = 'gc-multiselect__check fas fa-check';
            check.setAttribute('aria-hidden', 'true');

            item.appendChild(checkbox);
            item.appendChild(text);
            item.appendChild(check);
            list.appendChild(item);

            checkbox.addEventListener('change', function () {
                option.selected = checkbox.checked;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            return {
                option: option,
                checkbox: checkbox,
                item: item,
                text: option.textContent.trim(),
                haystack: option.textContent.trim().toLocaleLowerCase('ru').replaceAll('ё','е'),
            };
        });

        var updateButton = function () {
            var selected = options.filter(function (entry) {
                return entry.option.selected;
            });

            if (selected.length === 0) {
                buttonText.textContent = select.dataset.placeholder || 'Ничего не выбрано';
                button.classList.add('is-empty');
            } else if (selected.length <= 2) {
                buttonText.textContent = selected.map(function (entry) {
                    return entry.text;
                }).join(', ');
                button.classList.remove('is-empty');
            } else {
                buttonText.textContent = selected.length + ' выбрано';
                button.classList.remove('is-empty');
            }

            options.forEach(function (entry) {
                entry.item.classList.toggle('selected', entry.option.selected);
                entry.item.classList.toggle('active', entry.option.selected);
            });
        };

        var applySearch = function () {
            var query = search.value.trim().toLocaleLowerCase('ru').replaceAll('ё','е');
            var visibleCount = 0;

            options.forEach(function (entry) {
                var isVisible = !entry.option.disabled && (query === '' || entry.haystack.indexOf(query) !== -1);
                entry.item.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            empty.classList.toggle('d-none', visibleCount !== 0);
            selectVisible.disabled = visibleCount === 0; clearVisible.disabled = visibleCount === 0;
            selectVisible.textContent = query ? 'Выбрать найденных' : 'Выбрать всех';
            clearVisible.textContent = query ? 'Снять найденных' : 'Снять выбор';
        };

        var setVisibleOptions = function (checked) {
            options.forEach(function (entry) {
                if (entry.option.disabled || entry.item.classList.contains('d-none')) return;
                entry.option.selected = checked;
                entry.checkbox.checked = checked;
            });

            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        wrapper.addEventListener('shown.bs.dropdown',()=>search.focus());
        search.addEventListener('keydown',event=>{if(event.key==='Enter')event.preventDefault();});
        search.addEventListener('input', applySearch);
        selectVisible.addEventListener('click', function () {
            setVisibleOptions(true);
        });
        clearVisible.addEventListener('click', function () {
            setVisibleOptions(false);
        });
        select.addEventListener('change', function () {
            options.forEach(function (entry) {
                entry.checkbox.checked = entry.option.selected;
            });
            updateButton();
        });

        wrapper.appendChild(button);
        wrapper.appendChild(menu);
        select.after(wrapper);
        select.classList.add('d-none');
        const source = select.dataset.membershipSource && document.querySelector(select.dataset.membershipSource);
        const updateEligible = () => {
            if(source){
                const eligible = new Set([...source.selectedOptions].map(option=>option.value));
                options.forEach(entry=>{entry.option.disabled=!eligible.has(entry.option.value);entry.checkbox.disabled=entry.option.disabled;if(entry.option.disabled){entry.option.selected=false;entry.checkbox.checked=false;}});
                select.dispatchEvent(new Event('change',{bubbles:true}));
            }
            applySearch();
        };
        source?.addEventListener('change', updateEligible);
        updateEligible(); updateButton();
    });
}
