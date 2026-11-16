export default async function reportWorkspace(controlPage, { url, output, widths = [1440, 390] }) {
    const results = [], errors = [];
    const check = (name, passed, width, extra = {}) => {
        results.push({ name, passed, width, ...extra });
        if (!passed) throw new Error(`${name}: ${JSON.stringify(extra)}`);
    };
    for (const width of widths) {
        const context = await controlPage.context().browser().newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push({ width, message: error.message }));
        const loads = [];
        page.on('request', request => { if (request.url().includes('report_detail=1')) loads.push(new URL(request.url()).searchParams.get('student')); });
        const choose = async id => {
            if (width < 768) await page.locator('.gc-report-student-picker').click();
            await page.locator(`#student${id}-tab`).click();
        };
        const loaded = id => page.locator(`#student${id}[data-report-loaded="true"].active`).waitFor();
        try {
            await page.goto(`${url}/insider/courses/1/report?actor=teacher&scenario=statistics-desktop&student=101&lesson=2`);
            await page.locator('#student101marks2.show').waitFor();
            check('report-initial-detail-is-one-student', await page.locator('.report-student-card').count() === 1 && loads.length === 0, width);
            await choose(100); await loaded(100);
            check('report-loads-on-selection', loads.join(',') === '100' && new URL(page.url()).searchParams.get('student') === '100', width);
            check('report-loaded-progress-bars-have-width', await page.locator('#student100 .report-score-progress__bar').first().evaluate(bar => bar.style.width === '100%'), width);
            await choose(101); await loaded(101);
            await choose(100); await loaded(100);
            check('report-reuses-loaded-student', loads.join(',') === '100', width);
            await page.reload(); await loaded(100);
            await page.locator('#student100marks2.show').waitFor();
            check('report-selection-and-lesson-survive-reload', true, width);
            // A failed detail request keeps the roster usable and offers a working retry.
            let fail = true;
            const failing = async route => { if (fail) await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }); else await route.continue(); };
            await page.route(/report\?student=102&report_detail=1/, failing);
            await choose(102); await page.locator('#student102 [data-report-retry]').waitFor();
            fail = false; await page.locator('#student102 [data-report-retry]').click(); await loaded(102);
            check('report-failed-load-can-retry', true, width);
            await page.unroute(/report\?student=102&report_detail=1/, failing);
            // Delayed old responses cannot overwrite the latest selection.
            const slow = async route => { await new Promise(resolve => setTimeout(resolve, 500)); try { await route.continue(); } catch {} };
            await page.route(/report\?student=103&report_detail=1/, slow);
            await choose(103); await page.locator('#student103[aria-busy="true"]').waitFor();
            await choose(104); await loaded(104);
            await page.waitForTimeout(600);
            check('report-fast-switch-keeps-latest-student', await page.locator('.tab-pane.active').getAttribute('id') === 'student104' && await page.locator('#student104 #cardbody104').count() === 1, width);
            await page.goBack(); await loaded(103);
            await page.goForward(); await loaded(104);
            check('report-history-restores-student', new URL(page.url()).searchParams.get('student') === '104', width);
            await page.locator('[data-report-lesson-search]').fill('Нет такого урока');
            await page.locator('#student104 [data-report-lessons-empty]:visible').waitFor();
            await page.locator('#student104 [data-report-reset-lessons]').click();
            check('report-dynamic-detail-filters-reset', await page.locator('#student104 [data-report-lesson-id]:visible').count() === 4, width);
            await choose(1); await loaded(1);
            const activity = page.locator('#student1 [data-report-activity]');
            check('report-loaded-student-has-activity', await activity.count() === 1, width);
            if (await activity.count()) {
                check('report-chart-stays-lazy-after-detail-load', await page.locator('#student1 .js-plotly-plot').count() === 0, width);
                await activity.locator('summary').click();
                await page.locator('#student1 .js-plotly-plot').waitFor();
                check('report-dynamic-chart-renders', true, width);
            }
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                check('report-no-horizontal-overflow', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), width, { theme });
                await page.evaluate(() => window.scrollTo(0, 0));
                await page.screenshot({ path: `${output}/report-${theme}-${width}.png`, fullPage: true });
            }
        } finally { await context.close(); }
    }
    return { results, errors };
}
