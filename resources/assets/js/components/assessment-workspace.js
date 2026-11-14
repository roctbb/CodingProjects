import initTableNavigation, { revealTableRow } from './table-navigation';

export default function initAssessmentWorkspaces() {
    document.querySelectorAll('[data-assessment-workspace]').forEach(root => {
        const rows = [...root.querySelectorAll('[data-assessment-row]')];
        const input = root.querySelector('[data-assessment-student-search]');
        const clear = root.querySelector('[data-assessment-student-clear]');
        const count = root.querySelector('[data-assessment-student-count]');
        const filters = [...root.querySelectorAll('[data-assessment-state-filter]')];
        const statePicker = root.querySelector('[data-assessment-state-picker]');
        const detail = root.querySelector('[data-assessment-detail-content]');
        const picker = root.querySelector('[data-assessment-lesson-picker]');
        const overview = root.querySelector('[data-assessment-overview]');
        const detailSection = root.querySelector('[data-assessment-detail]');
        const back = root.querySelector('[data-assessment-back]');
        const templates = new Map([...root.querySelectorAll('[data-assessment-detail-template]')].map(template => [template.dataset.assessmentDetailTemplate, template]));
        let state = 'all';
        let lessonId;
        let focusedStudentId = null;
        const rememberSelection = () => {
            if (lessonId) history.replaceState(null, '', `${location.pathname}${location.search}#lesson-${lessonId}${focusedStudentId ? `-student-${focusedStudentId}` : ''}`);
        };
        const syncStudentFocus = () => {
            root.querySelectorAll('[data-assessment-row], [data-assessment-detail-row]').forEach(row => {
                row.classList.toggle('is-current', (row.dataset.assessmentRow || row.dataset.assessmentDetailRow) === focusedStudentId);
            });
            const focus = root.querySelector('[data-assessment-student-focus]');
            if (!focus) return;
            focus.hidden = !focusedStudentId;
            const student = rows.find(row => row.dataset.assessmentRow === focusedStudentId);
            root.querySelector('[data-assessment-selected-student]').textContent = student ? `Выбран: ${student.querySelector('th a').textContent.trim()}` : '';
        };

        const filter = () => {
            const query = input?.value.trim().toLocaleLowerCase('ru') || '';
            const visibleIds = new Set();
            const counts = {};
            rows.forEach(row => {
                const matchesName = row.dataset.assessmentStudentName.toLocaleLowerCase('ru').includes(query);
                const states = new Set(row.dataset.assessmentStates.split(' '));
                if (matchesName) states.forEach(value => counts[value] = (counts[value] || 0) + 1);
                row.hidden = !(matchesName && (state === 'all' || states.has(state)));
                if (!row.hidden) visibleIds.add(row.dataset.assessmentRow);
            });
            root.querySelectorAll('[data-assessment-detail-row]').forEach(row => row.hidden = !visibleIds.has(row.dataset.assessmentDetailRow));
            root.querySelectorAll('[data-assessment-empty], [data-assessment-detail-empty]').forEach(row => row.hidden = visibleIds.size > 0);
            filters.forEach(button => {
                const value = button.dataset.assessmentStateFilter;
                button.setAttribute('aria-pressed', String(value === state));
                button.disabled = value !== 'all' && !counts[value] && value !== state;
                const badge = button.querySelector('[data-assessment-state-count]');
                if (badge) badge.textContent = counts[value] || 0;
            });
            if (statePicker) {
                statePicker.value = state;
                [...statePicker.options].forEach(option => {
                    option.textContent = option.dataset.label + (option.value === 'all' || window.matchMedia('(max-width: 575.98px)').matches ? '' : ` (${counts[option.value] || 0})`);
                    option.disabled = option.value !== 'all' && !counts[option.value] && option.value !== state;
                });
            }
            if (count) count.textContent = `${visibleIds.size} из ${rows.length}`;
            if (clear) clear.classList.toggle('d-none', !query);
            if (focusedStudentId && !visibleIds.has(focusedStudentId)) {
                focusedStudentId = null;
                rememberSelection();
            }
            syncStudentFocus();
        };
        const selectLesson = (id, remember = true, navigate = false) => {
            const template = templates.get(id);
            if (!template || !detail) return;
            lessonId = id;
            detail.replaceChildren(template.content.cloneNode(true));
            root.querySelector('[data-assessment-detail-title]').textContent = template.dataset.lessonName;
            root.querySelectorAll('[data-assessment-lesson-select]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.assessmentLessonSelect === id)));
            root.querySelectorAll('[data-assessment-column]').forEach(cell => cell.classList.toggle('is-selected', cell.dataset.assessmentColumn === id));
            if (picker) picker.value = id;
            initTableNavigation(detail);
            const column = root.querySelector(`th[data-assessment-column="${id}"]`);
            root.querySelector('#assessment-table')?.dispatchEvent(new CustomEvent('gc:reveal-column', { detail: column }));
            filter();
            if (remember) rememberSelection();
            requestAnimationFrame(() => revealTableRow([...detail.querySelectorAll('[data-assessment-detail-row]')].find(row => row.dataset.assessmentDetailRow === focusedStudentId)));
            if (navigate && detailSection) {
                if (window.matchMedia('(max-width: 767.98px)').matches && overview) overview.open = false;
                requestAnimationFrame(() => {
                    root.querySelector('[data-assessment-detail-focus]')?.focus({ preventScroll: true });
                    detailSection.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
                });
            }
        };
        root.addEventListener('click', event => {
            const button = event.target.closest('[data-assessment-lesson-select]');
            if (button) {
                const row = button.closest('[data-assessment-row]');
                if (row) focusedStudentId = row.dataset.assessmentRow;
                selectLesson(button.dataset.assessmentLessonSelect, true, true);
            }
        });
        root.querySelector('[data-assessment-open-detail]')?.addEventListener('click', () => selectLesson(lessonId, true, true));
        root.querySelector('[data-assessment-clear-student]')?.addEventListener('click', () => {
            focusedStudentId = null;
            syncStudentFocus();
            rememberSelection();
            root.querySelector('[data-assessment-detail-focus]')?.focus({ preventScroll: true });
        });
        overview?.addEventListener('toggle', () => {
            if (back) back.hidden = overview.open;
            if (overview.open) requestAnimationFrame(() => root.querySelector('#assessment-table')?.dispatchEvent(new CustomEvent('gc:reveal-column', { detail: root.querySelector(`th[data-assessment-column="${lessonId}"]`) })));
        });
        back?.addEventListener('click', () => {
            overview.open = true;
            overview.querySelector('summary').focus({ preventScroll: true });
            overview.scrollIntoView({ block: 'start' });
            requestAnimationFrame(() => revealTableRow(rows.find(row => row.dataset.assessmentRow === focusedStudentId)));
        });
        picker?.addEventListener('change', () => selectLesson(picker.value));
        input?.addEventListener('input', filter);
        window.addEventListener('resize', filter);
        statePicker?.addEventListener('change', () => { state = statePicker.value; filter(); });
        clear?.addEventListener('click', () => { input.value = ''; input.focus(); filter(); });
        filters.forEach(button => button.addEventListener('click', () => { state = button.dataset.assessmentStateFilter; filter(); }));
        const savedSelection = location.hash.match(/^#lesson-(\d+)(?:-student-(\d+))?$/);
        const savedLesson = savedSelection?.[1];
        if (rows.some(row => row.dataset.assessmentRow === savedSelection?.[2])) focusedStudentId = savedSelection[2];
        initTableNavigation(root);
        selectLesson(templates.has(savedLesson) ? savedLesson : templates.keys().next().value, false, templates.has(savedLesson));
        filter();
    });
}
