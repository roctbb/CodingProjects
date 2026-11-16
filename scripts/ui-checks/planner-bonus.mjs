export default async function plannerBonus(controlPage, { url, output, widths = [1440, 390] }) {
    const results = [], errors = [];
    const check = (name, passed, width) => { results.push({ name, passed, width }); if (!passed) throw new Error(name); };
    for (const width of widths) {
        const context = await controlPage.context().browser().newContext({ viewport: { width, height: 1000 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push({ width, message: error.message }));
        try {
            await page.goto(`${url}/insider/courses/1/planner?actor=teacher&scenario=base`);
            const session = page.locator('.planner-session[data-session="1"]');
            await session.waitFor();
            await session.locator('footer [data-action="session-materials"]').click();
            const catalog = page.locator('#planner-catalog');
            await catalog.locator('.planner-catalog-card').first().waitFor();
            await catalog.locator('#planner-search').fill('Python');
            await page.waitForTimeout(400);
            await catalog.locator('[data-action="catalog-children"]').first().click();
            await catalog.getByRole('button', { name: 'На уровень выше в каталоге материалов', exact: true }).waitFor();
            await catalog.getByRole('button', { name: 'На уровень выше в каталоге материалов', exact: true }).click();
            await page.waitForFunction(() => document.querySelector('#planner-search').value === 'Python' && !document.querySelector('#planner-catalog-list').hasAttribute('aria-busy'));
            check('catalog-bottom-back-restores-parent-and-search', await catalog.locator('#planner-type').inputValue() === 'lesson', width);
            await catalog.getByRole('button', { name: 'Закрыть выбор материалов', exact: true }).click();
            const before = await session.locator('.planner-entry').allTextContents();
            await session.locator('footer [data-action="session-bonus"]').click();
            const dialog = page.locator('#planner-dialog');
            await dialog.getByRole('heading', { name: 'Бонус всем задачам занятия', exact: true }).waitFor();
            check('planner-bonus-confirmation-is-purple', await dialog.locator('#planner-dialog-submit').evaluate(el => el.classList.contains('btn-coin')), width);
            await dialog.locator('[name="class_bonus_gc"]').fill('7');
            await page.screenshot({ path: `${output}/planner-session-bonus-${width}.png` });
            await dialog.locator('#planner-dialog-submit').click();
            await dialog.waitFor({ state: 'hidden' });
            const planEntries = session.locator('.planner-entry');
            check('planner-bonus-updates-every-material', (await planEntries.allTextContents()).every(text => text.includes('+7 GC за задачу')), width);
            await page.reload(); await session.waitFor();
            check('planner-bonus-survives-reload', (await session.locator('.planner-entry').allTextContents()).every(text => text.includes('+7 GC за задачу')), width);
            await page.locator('.planner-course-menu summary').click();
            await page.locator('[data-action="undo"]').click();
            await page.waitForFunction(() => document.querySelector('#planner-status').textContent.includes('отменено'));
            check('planner-bulk-bonus-undo-restores-individual-values', JSON.stringify(await session.locator('.planner-entry').allTextContents()) === JSON.stringify(before), width);
            await session.locator('.planner-session-menu summary').click();
            await session.locator('[data-action="edit-session"]').click();
            check('planner-ordinary-save-keeps-its-color', await dialog.locator('#planner-dialog-submit').evaluate(el => !el.classList.contains('btn-coin') && el.classList.contains('btn-primary')), width);
            await dialog.locator('footer [data-action="close-dialog"]').click();
            check('planner-no-horizontal-overflow', await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), width);
        } finally { await context.close(); }
    }
    return { results, errors };
}
