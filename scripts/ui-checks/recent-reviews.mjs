export default async function recentReviews(controlPage, { url, output, widths = [1440, 390] }) {
    const results = [], errors = [];
    const check = (name, passed, width, extra = {}) => {
        results.push({ name, passed, width, ...extra });
        if (!passed) throw new Error(`${name}: ${JSON.stringify(extra)}`);
    };
    for (const width of widths) {
        const context = await controlPage.context().browser().newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push({ width, message: error.message }));
        try {
            await page.goto(`${url}/insider/reviews?actor=teacher&scenario=submissions`);
            check('review-default-pending-queue', await page.locator('[data-review-queue-solution]').count() > 0 && await page.locator('[data-review-activity]').count() === 0, width);
            await page.getByRole('link', { name: 'Последние сдачи', exact: true }).click();
            const rows = page.locator('[data-review-activity]');
            const total = await rows.count();
            check('review-recent-has-graded-and-pending', total > 0 && await page.locator('.gc-review-activity__state.is-graded').count() > 0 && await page.locator('.gc-review-activity__state.is-pending').count() > 0, width);
            const native = page.locator('[data-review-activity="submission-10000000-0000-4000-8000-000000000013"] a');
            check('review-native-exact-attempt-once', await native.count() === 1 && (await native.getAttribute('href')).includes('attempt=10000000-0000-4000-8000-000000000013') && await page.locator('[data-review-activity="solution-113"]').count() === 0, width);
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                check('review-feed-no-overflow', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), width, { theme });
                await page.screenshot({ path: `${output}/recent-reviews-${theme}-${width}.png`, fullPage: true });
            }
            await page.locator('select[name="course"]').selectOption('1');
            await page.locator('select[name="activity_status"]').selectOption('graded');
            await page.getByRole('button', { name: 'Найти', exact: true }).click();
            check('review-course-and-state-filter', await rows.count() > 0 && await page.locator('.gc-review-activity__state:not(.is-graded)').count() === 0 && await rows.locator('small').filter({ hasText: 'Веб-мастерская' }).count() === 0, width);
            await page.reload();
            check('review-filters-survive-reload', await page.locator('select[name="course"]').inputValue() === '1' && await page.locator('select[name="activity_status"]').inputValue() === 'graded', width);
            await page.getByRole('searchbox', { name: 'Ученик или задание' }).fill('нет такой работы');
            await page.getByRole('button', { name: 'Найти', exact: true }).click();
            check('review-empty-search-state', await rows.count() === 0 && await page.getByRole('heading', { name: 'По этим фильтрам сдач нет' }).count() === 1, width);
            await page.getByRole('link', { name: 'Сбросить фильтры', exact: true }).click();
            check('review-reset-preserves-feed', await rows.count() === total && new URL(page.url()).searchParams.get('view') === 'recent', width);
            await page.getByRole('link', { name: /^Нужно проверить/ }).click();
            check('review-switch-restores-queue-actions', await page.locator('[data-review-queue-solution]').count() > 0 && await page.locator('button', { hasText: 'Пропустить список' }).count() === 1, width);
            const response = await page.goto(`${url}/insider/reviews?actor=student&view=recent`);
            check('review-student-forbidden', response.status() === 403, width);
        } finally { await context.close(); }
    }
    return { results, errors };
}
