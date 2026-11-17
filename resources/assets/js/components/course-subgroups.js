export default function initCourseSubgroups() {
    const create = document.querySelector('[data-subgroup-create]');
    const toggle = document.querySelector('[data-subgroup-create-toggle]');
    if (create && toggle) {
        const cancel = create.querySelector('[data-subgroup-create-cancel]');
        const empty = document.querySelector('[data-subgroup-empty]');
        const setOpen = open => {
            create.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
            if (empty) empty.hidden = open;
            if (open) create.querySelector('[name="name"]').focus();
        };
        const close = () => { create.reset(); create.querySelector('[name="name"]').value = ''; setOpen(false); toggle.focus(); };
        toggle.addEventListener('click', () => {
            if (create.hidden) setOpen(true);
            else close();
        });
        cancel.addEventListener('click', close);
        create.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            close();
        });
        toggle.hidden = false;
        cancel.hidden = false;
        setOpen(create.dataset.open === 'true');
    }
    document.querySelectorAll('[data-subgroup-editor]').forEach(row => {
        const display = row.querySelector('[data-subgroup-display]');
        const form = row.querySelector('[data-subgroup-edit-form]');
        const input = form.querySelector('[name="name"]');
        const edit = row.querySelector('[data-subgroup-edit]');
        const cancel = row.querySelector('[data-subgroup-edit-cancel]');
        const close = () => {
            form.reset();
            form.hidden = true;
            display.hidden = false;
            edit.focus();
        };
        edit.addEventListener('click', () => {
            display.hidden = true;
            form.hidden = false;
            input.focus();
            input.select();
        });
        cancel.addEventListener('click', close);
        form.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            event.preventDefault();
            close();
        });
        edit.hidden = false;
        cancel.hidden = false;
        form.hidden = true;
    });

    document.querySelectorAll('[data-subgroup-roster]').forEach(form => {
        const students = [...form.querySelectorAll('input[name="students[]"]')];
        const all = form.querySelector('[data-subgroup-select-all]');
        const unassigned = form.querySelector('[data-subgroup-select-unassigned]');
        const clear = form.querySelector('[data-subgroup-clear]');
        const count = form.querySelector('[data-subgroup-selected-count]');
        const submit = form.querySelector('[type="submit"]');
        const transfer = form.querySelector('[data-subgroup-transfer]');
        const toolbar = form.querySelector('.gc-subgroup-roster-toolbar');
        const search = form.querySelector('[data-subgroup-search]');
        const empty = form.querySelector('[data-subgroup-search-empty]');
        const freeCount = students.filter(student => !student.dataset.subgroup).length;
        const longRoster = students.length > 10;
        const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е').trim();
        const names = new Map(students.map(student => [student, normalize(student.closest('label').textContent)]));
        const visibleStudents = () => students.filter(student => !student.closest('tr').hidden);

        const update = () => {
            const selected = students.filter(student => student.checked).length;
            const visible = visibleStudents();
            const checked = visible.filter(student => student.checked).length;
            const hidden = selected - checked;
            all.checked = visible.length > 0 && checked === visible.length;
            all.indeterminate = checked > 0 && checked < visible.length;
            all.disabled = visible.length === 0;
            all.setAttribute('aria-label', search.value.trim() ? 'Выбрать найденных учеников' : 'Выбрать всех учеников');
            count.textContent = `Выбрано: ${selected}` + (hidden ? ` · вне поиска: ${hidden}` : '');
            count.hidden = selected === 0;
            clear.hidden = selected === 0;
            transfer.hidden = selected === 0;
            toolbar.hidden = selected === 0 && freeCount === 0 && !longRoster;
            clear.disabled = selected === 0;
            submit.disabled = selected === 0;
        };
        const filter = () => {
            const words = normalize(search.value).split(/\s+/).filter(Boolean);
            students.forEach(student => { student.closest('tr').hidden = !words.every(word => names.get(student).includes(word)); });
            empty.hidden = students.length === 0 || visibleStudents().length > 0;
            update();
        };
        const select = predicate => {
            students.forEach(student => { student.checked = predicate(student); });
            update();
        };
        all.addEventListener('change', () => {
            visibleStudents().forEach(student => { student.checked = all.checked; });
            update();
        });
        unassigned.addEventListener('click', () => { search.value = ''; filter(); select(student => !student.dataset.subgroup); });
        clear.addEventListener('click', () => { select(() => false); if (all.disabled) search.focus(); else all.focus(); });
        search.addEventListener('input', filter);
        search.addEventListener('keydown', event => {
            if (event.key === 'Enter') event.preventDefault();
            if (event.key === 'Escape') { event.preventDefault(); search.value = ''; filter(); }
        });
        form.querySelector('[data-subgroup-search-reset]').addEventListener('click', () => { search.value = ''; filter(); search.focus(); });
        students.forEach(student => student.addEventListener('change', update));
        window.addEventListener('pageshow', filter);
        form.querySelector('[data-subgroup-unassigned-count]').textContent = freeCount;
        unassigned.disabled = freeCount === 0;
        unassigned.hidden = freeCount === 0;
        form.querySelector('[data-subgroup-search-field]').hidden = !longRoster;
        form.dataset.longRoster = String(longRoster);
        all.hidden = false;
        form.querySelector('[data-subgroup-selection]').hidden = false;
        if (longRoster) {
            const topbar = document.querySelector('.gc-topbar');
            const offset = () => {
                form.style.setProperty('--gc-subgroup-top', (topbar?.getBoundingClientRect().height || 0) + 'px');
                form.style.setProperty('--gc-subgroup-toolbar-height', toolbar.getBoundingClientRect().height + 'px');
            };
            const observer = new ResizeObserver(offset);
            if (topbar) observer.observe(topbar);
            observer.observe(toolbar);
            offset();
        }
        update();
    });
}
