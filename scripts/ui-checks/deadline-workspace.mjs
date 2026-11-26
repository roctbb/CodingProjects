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
                check('deadlines-show-only-populated-groups', await page.locator('[data-deadline-agenda="upcoming"] [data-deadline-row]:visible').count() === ids.length && await page.locator('[data-deadline-agenda="missed"]').isHidden(), width, { role });
                check('deadlines-no-search-or-reminders', await page.locator('[data-deadline-workspace] :is(input[type="search"], form[action$="/notifications/preference"])').count() === 0, width, { role });
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
                check('deadlines-date-on-each-card', await rows.locator('time[data-deadline-calendar]').count() === ids.length, width);
            }
            await page.clock.install();
            for (const role of ['teacher', 'student']) {
                await page.goto(`${url}/insider/courses/1?actor=${role}&scenario=base`);
                const calendar = page.locator('#lesson-1 [data-lesson-deadline-first]');
                const icon = calendar.locator('.gc-deadline-popover__trigger > .fa-calendar-days').first();
                await calendar.locator('[data-deadline-state]').first().waitFor({ state: 'attached' });
                const timing = await calendar.evaluate(el => ({
                    now: Number(el.dataset.deadlineServerNow),
                    cutoff: JSON.parse(el.dataset.lessonDeadlineFirst).cutoff,
                }));
                const color = token => icon.evaluate((el, token) => {
                    const canvas = document.createElement('canvas').getContext('2d');
                    canvas.fillStyle = getComputedStyle(el).color;
                    const actual = canvas.fillStyle;
                    canvas.fillStyle = getComputedStyle(el).getPropertyValue(token).trim();
                    return { actual, expected: canvas.fillStyle };
                }, token);
                await page.clock.fastForward(timing.cutoff - timing.now - 3600000);
                const today = await calendar.getAttribute('data-lesson-deadline-status');
                const yellow = await color('--gc-warning');
                check('lesson-calendar-is-yellow-on-the-first-deadline-day',
                    today === 'today' && yellow.actual === yellow.expected, width, { role, status: today, ...yellow });
                await page.clock.fastForward(3600010);
                const overdue = await calendar.getAttribute('data-lesson-deadline-status');
                const red = await color('--gc-danger');
                check('lesson-calendar-turns-red-when-the-first-deadline-passes',
                    overdue === 'overdue' && red.actual === red.expected, width, { role, status: overdue, ...red });
            }
            await page.goto(`${url}/insider/courses/1/deadlines?actor=teacher&scenario=base`);
            const rule = page.locator('[data-deadline-task-id="11"] [data-deadline-summary]');
            const schedule = await rule.evaluate(el => ({ now: Number(el.dataset.serverNow), cutoffs: [...el.querySelectorAll('[data-deadline-stage]')].map(stage => Number(stage.dataset.cutoff)) }));
            await page.clock.fastForward(schedule.cutoffs[0] - schedule.now + 10);
            check('deadlines-live-date-advances', (await rule.locator('[data-deadline-calendar]').getAttribute('datetime')) === await rule.locator('[data-deadline-stage]').last().getAttribute('data-datetime'), width);
            check('deadlines-stay-upcoming-with-next-cutoff', await page.locator('[data-deadline-agenda="upcoming"] [data-deadline-task-id="11"]').isVisible(), width);
            await page.clock.fastForward(schedule.cutoffs.at(-1) - schedule.cutoffs[0] + 10);
            const missed = page.locator('[data-deadline-agenda="missed"] [data-deadline-task-id="11"]');
            check('deadlines-live-final-stage-moves-to-missed', await missed.locator('[data-deadline-last-label]').isVisible() && await page.locator('[data-deadline-agenda="upcoming"]').isHidden(), width);

            await page.goto(`${url}/insider/courses/1/deadlines?actor=student&scenario=base`);
            const studentRow = page.locator('[data-deadline-task-id="12"]');
            const studentRule = studentRow.locator('[data-deadline-summary]');
            await studentRow.locator('[data-deadline-state]').waitFor({ state: 'visible' });
            const studentSchedule = await studentRule.evaluate(el => ({
                now: Number(el.dataset.serverNow),
                cutoffs: [...el.querySelectorAll('[data-deadline-stage]')].map(stage => Number(stage.dataset.cutoff)),
            }));
            await page.clock.fastForward(studentSchedule.cutoffs[0] - studentSchedule.now + 10);
            check('student-deadline-stays-visible-with-partial-penalty',
                await studentRow.isVisible() && await studentRule.locator('[data-deadline-current]').textContent() === 'Сейчас −30%', width);
            await page.clock.fastForward(studentSchedule.cutoffs.at(-1) - studentSchedule.cutoffs[0] + 10);
            check('student-deadline-hides-at-full-penalty',
                await studentRow.isHidden() && await page.locator('[data-deadline-agenda="upcoming"]').isHidden(), width);
            const anyVisibleRows = await page.locator('[data-deadline-row]:visible').count() > 0;
            check('student-deadline-empty-state-follows-remaining-work',
                await page.locator('[data-deadline-empty]').isHidden() === anyVisibleRows, width);
        } finally { await context.close(); }
    }
    return { results, errors };
}
