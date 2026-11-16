export default async function deadlineWorkspace(controlPage, { url, output, widths = [1440, 390] }) {
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
            for (const role of ['teacher', 'student']) {
                await page.goto(`${url}/insider/courses/1/deadlines?actor=${role}&scenario=base`);
                const rows = page.locator('[data-deadline-row]');
                const ids = await rows.evaluateAll(rows => rows.map(row => row.dataset.deadlineTaskId));
                check('deadlines-one-row-per-task', ids.length > 0 && new Set(ids).size === ids.length, width, { role });
                check('deadlines-controls-have-one-surface', await page.locator('.gc-deadline-controls [data-deadline-search]').count() === 1 && !(await page.locator('[data-deadline-workspace]').innerText()).includes('Срок прошёл'), width, { role });
                for (const theme of ['light', 'dark']) {
                    await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                    check('deadlines-no-horizontal-overflow', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), width, { role, theme });
                    const trigger = rows.first().locator('.gc-deadline-popover__trigger');
                    await trigger.click();
                    const panel = page.locator('[popover]:popover-open');
                    check('deadlines-all-stages-overlay', await panel.locator('[data-deadline-stage]').count() > 0 && await panel.evaluate(el => { const r = el.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1; }), width, { role, theme });
                    await page.keyboard.press('Escape');
                    check('deadlines-popover-restores-focus', await trigger.evaluate(el => document.activeElement === el), width, { role, theme });
                    await page.screenshot({ path: `${output}/deadlines-${role}-${theme}-${width}.png`, fullPage: true });
                }
                await page.locator('[data-deadline-search]').fill('нет такой задачи');
                await page.locator('[data-deadline-empty]:visible').waitFor();
                await page.reload();
                await page.locator('[data-deadline-empty]:visible').waitFor();
                check('deadlines-search-survives-reload', true, width, { role });
                await page.locator('[data-deadline-reset]').click();
                check('deadlines-reset-restores-rows', await page.locator('[data-deadline-row]:visible').count() === ids.length, width, { role });
                if (role === 'student') {
                    await page.locator('[data-deadline-filter="done"]').click();
                    check('deadlines-done-filter-contains-completed-only', await page.locator('[data-deadline-row]:visible').evaluateAll(rows => rows.length > 0 && rows.every(row => row.dataset.deadlineDone === 'true')), width);
                }
            }
            await page.clock.install();
            await page.goto(`${url}/insider/courses/1/deadlines?actor=teacher&scenario=base`);
            const rule = page.locator('[data-deadline-task-id="11"] [data-deadline-summary]');
            const schedule = await rule.evaluate(el => ({ now: Number(el.dataset.serverNow), cutoffs: [...el.querySelectorAll('[data-deadline-stage]')].map(stage => Number(stage.dataset.cutoff)) }));
            await page.locator('[data-deadline-filter="penalty"]').click();
            await page.clock.fastForward(schedule.cutoffs[0] - schedule.now + 10);
            check('deadlines-live-penalty-updates-filter', (await rule.locator('[data-deadline-current]').innerText()) === 'Сейчас −30%' && await rule.isVisible(), width);
            await page.clock.fastForward(schedule.cutoffs.at(-1) - schedule.cutoffs[0] + 10);
            check('deadlines-live-final-stage-shows-last-date', await rule.locator('[data-deadline-next]').isHidden() && await page.locator('[data-deadline-task-id="11"] [data-deadline-last-label]').isVisible(), width);
        } finally { await context.close(); }
    }
    return { results, errors };
}
