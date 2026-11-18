import { Dropdown } from 'bootstrap';

function initChapterPicker(root) {
    const picker = root.querySelector('[data-chapter-picker]');
    if (!picker) return;
    const trigger = picker.querySelector('[data-bs-toggle="dropdown"]');
    const menu = picker.querySelector('.dropdown-menu');
    const list = picker.querySelector('[data-chapter-list]');
    const search = picker.querySelector('[data-chapter-search]');
    const empty = picker.querySelector('[data-chapter-empty]');
    const choices = [...picker.querySelectorAll('[data-chapter-choice]')];
    const viewportPadding = () => {
        const bottomNav = document.querySelector('.gc-bottom-nav')?.getBoundingClientRect();
        return { top: 16, right: 16, left: 16, bottom: bottomNav?.height ? window.innerHeight - bottomNav.top + 16 : 16 };
    };
    const dropdown = Dropdown.getOrCreateInstance(trigger, {
        popperConfig: config => ({ ...config, modifiers: [...config.modifiers,
            { name: 'preventOverflow', options: { padding: viewportPadding() } },
            { name: 'flip', options: { padding: viewportPadding() } },
        ] }),
    });
    const normalize = text => text.toLocaleLowerCase('ru').replaceAll('ё', 'е');
    const filter = () => {
        const query = normalize(search?.value.trim() || '');
        choices.forEach(choice => choice.hidden = !normalize(choice.dataset.chapterName).includes(query));
        empty.hidden = choices.some(choice => !choice.hidden);
    };
    const fit = () => {
        const bounds = trigger.getBoundingClientRect();
        const padding = viewportPadding();
        menu.style.setProperty('--gc-chapter-menu-height', Math.max(120, Math.max(bounds.top - padding.top, window.innerHeight - bounds.bottom - padding.bottom) - 8) + 'px');
    };
    picker.addEventListener('show.bs.dropdown', () => {
        if (search) search.value = '';
        filter(); fit();
    });
    picker.addEventListener('shown.bs.dropdown', () => {
        const current = list.querySelector('[aria-current="page"]');
        if (current) {
            const bounds = list.getBoundingClientRect(), selected = current.getBoundingClientRect();
            list.scrollTop += selected.top - bounds.top - (bounds.height - selected.height) / 2;
        }
        const target = search && window.matchMedia('(pointer:fine)').matches ? search : current?.matches('a') ? current : list.querySelector('a');
        target?.focus({preventScroll:true});
    });
    search?.addEventListener('input', filter);
    search?.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            choices.find(choice => !choice.hidden && choice.querySelector('a'))?.querySelector('a').focus();
        }
    });
    window.addEventListener('pageshow', () => dropdown.hide());
    window.addEventListener('resize', () => {
        if (menu.classList.contains('show')) { fit(); dropdown.update(); }
    });
}

export default function initChapterTimelines() {
    document.querySelectorAll('[data-chapter-timeline]').forEach(root => {
        initChapterPicker(root);
        const range = root.querySelector('[data-chapter-range]');
        if (!range) return;
        const {items, selected: current, currentChapterId} = JSON.parse(root.querySelector('[data-chapter-options]').textContent);
        const available = items.map((item, index) => item.url ? index : null).filter(index => index !== null);
        const title = root.querySelector('[data-chapter-title]');
        const counter = root.querySelector('[data-chapter-counter]');
        const status = root.querySelector('[data-chapter-status]');
        let inputMethod = 'pointer';
        let navigating = false;

        const preview = index => {
            if (!range.disabled) {
                status.hidden = true;
                status.textContent = '';
            }
            range.value = index;
            root.classList.toggle('is-current-chapter', Number(items[index].id) === Number(currentChapterId));
            root.style.setProperty('--gc-timeline-position', `${index / (items.length - 1) * 100}%`);
            title.textContent = items[index].name;
            counter.textContent = `Глава ${index + 1} из ${items.length}`;
            range.setAttribute('aria-valuetext', `Глава ${index + 1} из ${items.length}: ${items[index].name}${items[index].url ? '' : ' · пока закрыта'}`);
        };
        const commit = () => {
            const index = Number(range.value);
            if (navigating || index === current) return;
            if (!items[index].url) {
                preview(current);
                status.textContent = 'Эта глава пока закрыта.';
                status.hidden = false;
                return;
            }
            navigating = true;
            window.location.assign(items[index].url);
        };

        range.hidden = false;
        range.addEventListener('pointerdown', () => { inputMethod = 'pointer'; });
        range.addEventListener('pointercancel', () => { preview(current); });
        range.addEventListener('input', () => {
            status.hidden = true;
            preview(Number(range.value));
        });
        range.addEventListener('change', () => { if (inputMethod !== 'keyboard') commit(); });
        range.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                preview(current);
                return;
            }
            if (event.key === 'Enter') {
                event.preventDefault();
                commit();
                return;
            }
            const keys = ['ArrowLeft', 'ArrowDown', 'ArrowRight', 'ArrowUp', 'Home', 'End', 'PageUp', 'PageDown'];
            if (!keys.includes(event.key) || !available.length) return;
            event.preventDefault();
            inputMethod = 'keyboard';
            const index = Number(range.value);
            let target;
            if (event.key === 'Home') target = available[0];
            else if (event.key === 'End') target = available.at(-1);
            else if (['ArrowLeft', 'ArrowDown', 'PageDown'].includes(event.key)) target = available.filter(value => value < index).at(-1);
            else target = available.find(value => value > index);
            if (target !== undefined) {
                status.hidden = true;
                preview(target);
            }
        });
        range.addEventListener('keyup', event => {
            if (['ArrowLeft', 'ArrowDown', 'ArrowRight', 'ArrowUp', 'Home', 'End', 'PageUp', 'PageDown'].includes(event.key)) commit();
        });
        window.addEventListener('pageshow', () => { navigating = false; inputMethod = 'pointer'; preview(current); });
    });
}
