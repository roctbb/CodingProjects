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
