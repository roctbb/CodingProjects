import * as bootstrap from 'bootstrap';
import { syncCourseChapter, withCourseScope } from './course-context';

export default function initReportWorkspaces() {
    document.querySelectorAll('[data-report-workspace]').forEach(root => {
        const list = root.querySelector('#v-pills-tab');
        if (!list) return;
        const links = [...list.querySelectorAll('[data-report-student-name]')];
        const search = root.querySelector('[data-report-student-search]');
        const clear = root.querySelector('[data-report-student-clear]');
        const sort = root.querySelector('[data-report-student-sort]');
        const count = root.querySelector('[data-report-student-count]');
        const content = root.querySelector('#v-pills-tabContent');
        const empty = root.querySelector('[data-report-search-empty]');
        const roster = root.querySelector('#report-students-picker');
        const chapter = root.querySelector('[data-report-chapter-filter]');
        const lessonState = root.querySelector('[data-report-lesson-state]');
        const lessonSearch = root.querySelector('[data-report-lesson-search]');
        const lessonClear = root.querySelector('[data-report-lesson-clear]');
        const collator = new Intl.Collator('ru', { numeric: true, sensitivity: 'base' });
        const plurals = new Intl.PluralRules('ru');
        const normalize = value => value.toLocaleLowerCase('ru').replaceAll('ё', 'е').trim();
        const number = (link, key) => Number.isFinite(Number(link.dataset[key])) ? Number(link.dataset[key]) : -1;
        let visible = [];
        const activeLink = () => links.find(link => link.classList.contains('active'));
        const paneFor = link => link && root.querySelector(link.dataset.bsTarget);
        let pendingRequest;
        let navigation = 0;
        let restoringHistory = false;
        const openLinkedLesson = pane => {
            const lessonId = new URL(location.href).searchParams.get('lesson');
            const row = [...pane.querySelectorAll('[data-report-lesson-id]')].find(row => row.dataset.reportLessonId === lessonId);
            if (!row) return;
            const group = row.closest('[data-report-chapter]');
            if (group) chapter.value = group.dataset.reportChapter;
            lessonState.value = 'all';
            lessonSearch.value = '';
            filterLessons(false);
            const details = row.querySelector('.report-lesson-details');
            bootstrap.Collapse.getOrCreateInstance(details, { toggle: false }).show();
        };
        const loadStudent = async (link, remember = true) => {
            const pane = paneFor(link);
            if (!pane) return;
            const generation = ++navigation;
            pendingRequest?.abort();
            if (remember) {
                const url = new URL(location.href);
                url.searchParams.set('student', pane.dataset.reportStudentId);
                url.hash = pane.id;
                if (url.href !== location.href) history.pushState(null, '', url);
            }
            sync();
            if (pane.dataset.reportLoaded === 'true') { openLinkedLesson(pane); return; }
            const controller = new AbortController();
            pendingRequest = controller;
            pane.setAttribute('aria-busy', 'true');
            pane.innerHTML = '<div class="gc-card report-detail-status" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Загружаем отчёт ученика…</div>';
            try {
                const url = new URL(withCourseScope(root.dataset.reportDetailUrl), location.origin);
                url.searchParams.set('student', pane.dataset.reportStudentId);
                url.searchParams.set('report_detail', '1');
                const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Report request failed');
                const data = await response.json();
                if (generation !== navigation) return;
                if (String(data.student) !== pane.dataset.reportStudentId || typeof data.html !== 'string') throw new Error('Unexpected student');
                pane.innerHTML = data.html;
                pane.querySelectorAll('[data-progress-width]').forEach(bar => {
                    const width = Number.parseFloat(bar.dataset.progressWidth);
                    if (Number.isFinite(width)) bar.style.width = `${Math.max(0, Math.min(100, width))}%`;
                });
                pane.dataset.reportLoaded = 'true';
                sync();
                openLinkedLesson(pane);
                document.dispatchEvent(new CustomEvent('gc:report-student-loaded'));
            } catch (error) {
                if (error.name === 'AbortError' || generation !== navigation) return;
                pane.innerHTML = '<div class="gc-card report-detail-status" role="alert"><p class="mb-0">Не удалось загрузить отчёт ученика.</p><button class="btn btn-outline-secondary btn-sm" type="button" data-report-retry>Повторить</button></div>';
            } finally { pane.removeAttribute('aria-busy'); }
        };
        const sync = () => {
            const active = activeLink();
            root.querySelector('[data-report-selected-name]').textContent = visible.length ? active?.dataset.reportStudentLabel || 'Выберите ученика' : 'Ученики не найдены';
            const index = visible.indexOf(active);
            root.querySelector('[data-report-previous]').disabled = index <= 0;
            root.querySelector('[data-report-next]').disabled = index < 0 || index >= visible.length - 1;
            filterLessons(false);
        };
        const filter = () => {
            const query = normalize(search.value);
            const mode = sort.value;
            const metric = { percent: 'reportStudentSortPercent', pending: 'reportStudentSortPending', similarity: 'reportStudentSortSimilarity', llm: 'reportStudentSortLlm' }[mode];
            links.sort((a, b) => (metric ? number(b, metric) - number(a, metric) : 0)
                || (['similarity', 'llm'].includes(mode) ? number(b, 'reportStudentSortRisk') - number(a, 'reportStudentSortRisk') : 0)
                || collator.compare(a.dataset.reportStudentSortName, b.dataset.reportStudentSortName));
            visible = links.filter(link => {
                link.hidden = !normalize(link.dataset.reportStudentName).includes(query);
                list.appendChild(link);
                link.querySelectorAll('[data-report-sort-value]').forEach(label => label.classList.toggle('d-none', label.dataset.reportSortValue !== (['name', 'pending'].includes(mode) ? 'percent' : mode)));
                return !link.hidden;
            });
            content.hidden = !visible.length;
            empty.hidden = !!visible.length;
            list.hidden = !visible.length;
            root.querySelector('[data-report-roster-empty]').hidden = !!visible.length;
            count.textContent = `${visible.length} из ${links.length}`;
            clear.classList.toggle('d-none', !query);
            if (visible.length && !visible.includes(activeLink())) bootstrap.Tab.getOrCreateInstance(visible[0]).show();
            sync();
        };
        const reset = () => { search.value = ''; filter(); };
        search.addEventListener('input', filter);
        sort.addEventListener('change', filter);
        clear.addEventListener('click', () => { reset(); search.focus(); });
        root.querySelector('[data-report-reset-search]').addEventListener('click', () => {
            reset();
            const target = window.matchMedia('(max-width: 767.98px)').matches ? root.querySelector('.gc-report-student-picker') : search;
            target.focus();
        });
        ['previous', 'next'].forEach(direction => root.querySelector(`[data-report-${direction}]`).addEventListener('click', () => {
            const index = visible.indexOf(activeLink()) + (direction === 'previous' ? -1 : 1);
            if (visible[index]) bootstrap.Tab.getOrCreateInstance(visible[index]).show();
        }));
        list.addEventListener('shown.bs.tab', event => {
            loadStudent(event.target, !restoringHistory);
            restoringHistory = false;
        });
        list.addEventListener('click', event => {
            if (event.target.closest('[data-report-student-name]') && window.matchMedia('(max-width: 767.98px)').matches) bootstrap.Offcanvas.getInstance(roster)?.hide();
        });
        roster.addEventListener('shown.bs.offcanvas', () => search.focus());
        const filterLessons = (remember = true) => {
            const query = normalize(lessonSearch.value);
            syncCourseChapter(root, chapter.value);
            root.querySelector('[data-report-chapter-value]').value = chapter.value;
            root.querySelector('input[data-report-lesson-state-value]').value = lessonState.value;
            root.querySelector('[data-report-lesson-query-value]').value = lessonSearch.value;
            lessonClear.classList.toggle('d-none', !query);
            const counts = { all: 0, incomplete: 0, review: 0, complete: 0 };
            root.querySelectorAll('#v-pills-tabContent > .tab-pane.active').forEach(pane => {
                let total = 0, shown = 0, points=0, maximum=0;
                pane.querySelectorAll('[data-report-chapter]').forEach(group => {
                    const inChapter = chapter.value === 'all' || group.dataset.reportChapter === chapter.value;
                    let groupCount = 0;
                    group.querySelectorAll('[data-report-lesson-name]').forEach(row => {
                        const state = row.dataset.reportLessonStateValue;
                        const incomplete = state === 'incomplete' || state === 'review';
                        const matchesName = normalize(row.dataset.reportLessonName).includes(query);
                        if (inChapter) {total++; points+=Number(row.dataset.reportPoints || 0);maximum+=Number(row.dataset.reportMaximum || 0);}
                        if (pane.classList.contains('active') && inChapter && matchesName) {
                            counts.all++;
                            if (incomplete) counts.incomplete++;
                            if (state === 'review') counts.review++;
                            if (state === 'complete') counts.complete++;
                        }
                        row.hidden = !(inChapter && matchesName && (lessonState.value === 'all' || (lessonState.value === 'incomplete' ? incomplete : state === lessonState.value)));
                        if (!row.hidden) { shown++; groupCount++; }
                    });
                    group.hidden = !groupCount;
                });
                const form = plurals.select(total);
                const lessonCount = pane.querySelector('[data-report-lesson-count]');
                if (lessonCount) lessonCount.textContent = `${shown === total ? total : `${shown} из ${total}`} ${{ one: 'урок', few: 'урока', many: 'уроков', other: 'урока' }[form]}`;
                const lessonsEmpty = pane.querySelector('[data-report-lessons-empty]');
                if (lessonsEmpty) lessonsEmpty.hidden = shown > 0;
                const summary=pane.querySelector('[data-report-summary-value]');
                if(summary)summary.textContent=maximum>0?`${points} / ${maximum} XP · ${Math.min(100,Math.round(points/maximum*100))}%`:'Без оценки';
                const label=pane.querySelector('[data-report-summary-label]');if(label)label.textContent=chapter.value==='all'?'Во всех главах':'В этой главе';
            });
            [...lessonState.options].forEach(option => {
                option.textContent = window.matchMedia('(max-width: 575.98px)').matches ? option.dataset.label : `${option.dataset.label} (${counts[option.value] || 0})`;
                option.disabled = option.value !== 'all' && !counts[option.value] && option.value !== lessonState.value;
            });
            lessonSearch.placeholder = window.matchMedia('(max-width: 575.98px)').matches ? 'Название' : 'Найти урок';
            if (remember) {
                const url = new URL(location.href);
                url.searchParams.set('chapter', chapter.value);
                if (lessonState.value === 'all') url.searchParams.delete('lesson_state');
                else url.searchParams.set('lesson_state', lessonState.value);
                if (!query) url.searchParams.delete('lesson_q');
                else url.searchParams.set('lesson_q', lessonSearch.value.trim());
                history.replaceState(null, '', url);
            }
        };
        const parameters = new URL(location.href).searchParams;
        const savedChapter = parameters.get('chapter') ?? root.dataset.reportDefaultChapter;
        const chapterIds = [...chapter.options].map(option => option.value);
        chapter.value = chapterIds.includes(savedChapter) ? savedChapter : (chapterIds.includes(root.dataset.reportDefaultChapter) ? root.dataset.reportDefaultChapter : 'all');
        const savedState = parameters.get('lesson_state');
        if ([...lessonState.options].some(option => option.value === savedState)) lessonState.value = savedState;
        lessonSearch.value = (parameters.get('lesson_q') || '').slice(0, 120);
        chapter.addEventListener('change', () => filterLessons());
        lessonState.addEventListener('change', () => filterLessons());
        lessonSearch.addEventListener('input', () => filterLessons());
        window.addEventListener('resize', () => filterLessons(false));
        lessonClear.addEventListener('click', () => { lessonSearch.value = ''; filterLessons(); lessonSearch.focus(); });
        root.addEventListener('click', event => {
            if (event.target.closest('[data-report-retry]')) { loadStudent(activeLink(), false); return; }
            if (!event.target.closest('[data-report-reset-lessons]')) return;
            lessonSearch.value = '';
            lessonState.value = 'all';
            filterLessons();
            lessonSearch.focus();
        });
        root.addEventListener('show.bs.collapse', event => {
            const template = event.target.querySelector('[data-report-task-template]');
            if (template) { template.replaceWith(template.content.cloneNode(true)); }
        });
        filterLessons(false);
        filter();
        loadStudent(activeLink(), false);
        window.addEventListener('popstate', () => {
            const saved = new URL(location.href).searchParams;
            chapter.value = chapterIds.includes(saved.get('chapter')) ? saved.get('chapter') : root.dataset.reportDefaultChapter;
            lessonState.value = ['all', 'incomplete', 'review', 'complete'].includes(saved.get('lesson_state')) ? saved.get('lesson_state') : 'all';
            lessonSearch.value = (saved.get('lesson_q') || '').slice(0, 120);
            const link = links.find(item => item.id === `student${saved.get('student')}-tab`) || visible[0];
            if (!link) return;
            if (link === activeLink()) loadStudent(link, false);
            else { restoringHistory = true; bootstrap.Tab.getOrCreateInstance(link).show(); }
        });
    });
}
