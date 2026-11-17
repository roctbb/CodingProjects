export function syncCourseChapter(root, chapter) {
    const value = /^[1-9]\d*$/.test(String(chapter)) ? String(chapter) : null;
    const context = root.querySelector('[data-course-context]');
    if (context) context.dataset.courseChapter = value ?? '';
    root.querySelectorAll('[data-course-navigation] a').forEach(link => {
        const url = new URL(link.href);
        if (value) url.searchParams.set('chapter', value);
        else url.searchParams.delete('chapter');
        link.href = url.href;
    });
}

export function withCourseScope(url) {
    const result = new URL(url, location.origin);
    const context = document.querySelector('[data-course-context]');
    const scope = context ? context.dataset.courseScope : new URL(location.href).searchParams.get('subgroup');
    if (scope && scope !== 'all') result.searchParams.set('subgroup', scope);
    return result.href;
}

export default function initCourseScope() {
    const context = document.querySelector('[data-course-context]');
    const scope = context ? context.dataset.courseScope : new URL(location.href).searchParams.get('subgroup');
    if (!scope || scope === 'all') return;
    const courseId = context?.dataset.courseId || location.pathname.match(/^\/insider\/courses\/(\d+)(?:\/|$)/)?.[1];
    if (!courseId) return;
    const prefix = `/insider/courses/${courseId}`;
    const belongs = url => url.origin === location.origin && (url.pathname === prefix || url.pathname.startsWith(prefix + '/'));
    const applyForm = form => {
        if (form.querySelector('[name="subgroup"]') || !belongs(new URL(form.action, location.origin))) return;
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'subgroup'; input.value = scope;
        form.append(input);
    };
    const apply = () => { document.querySelectorAll('form').forEach(applyForm); document.querySelectorAll('a[href]').forEach(link => {
        const url = new URL(link.href, location.origin);
        if (belongs(url) && !url.searchParams.has('subgroup')) link.href = withCourseScope(url.href);
    }); };
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form instanceof HTMLFormElement) applyForm(form);
    }, true);
    apply();
    document.addEventListener('gc:report-student-loaded', apply);
    document.addEventListener('gc:course-stats-loaded', apply);
}
