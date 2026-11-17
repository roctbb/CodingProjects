// Uses synthetic lessons in a disposable SQLite database.
export default async function plannerCatalog(page, config) {
    const results = [], errors = [];
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    for (const width of config.widths) {
        const context = await page.context().browser().newContext({ viewport: { width, height: 950 }, reducedMotion: 'reduce' });
        const p = await context.newPage();
        p.setDefaultTimeout(5000);
        p.setDefaultNavigationTimeout(15000);
        p.on('pageerror', error => errors.push({ width, error: error.message }));
        const go = async chapter => {
            const url = new URL('/insider/courses/1/planner', config.url);
            url.searchParams.set('actor', 'teacher');
            url.searchParams.set('scenario', 'planner-chapters-' + (width < 768 ? 'phone' : 'desktop'));
            if (chapter != null) url.searchParams.set('chapter', chapter);
            await p.goto(url.href, { waitUntil: 'domcontentloaded' });
            await p.locator('#planner-save-state').filter({ hasText: 'Сохранено' }).waitFor();
        };
        const ready = () => p.waitForFunction(() => !document.querySelector('#planner-catalog-list').hasAttribute('aria-busy'));
        const open = async id => { await p.locator(`.planner-session[data-session="${id}"] [data-action="session-materials"]`).click(); await ready(); };
        const chooseChapter = async id => { await p.locator('#planner-chapter').selectOption(id); await ready(); };
        const test = async (name, run) => { await run(); results.push({ name, width, passed: true }); };
        try {
            await go(1);
            await test('planner-chapter-context-and-existing-session', async () => {
                await open(2);
                assert(await p.locator('#planner-chapter').inputValue() === '1', 'New session lost the chapter from the course URL');
                await p.keyboard.press('Escape');
                await go(501);
                await open(1);
                assert(await p.locator('#planner-chapter').inputValue() === '1', 'Existing session did not use its materials chapter');
                await p.keyboard.press('Escape');
                await go();
                await open(2);
                assert(await p.locator('#planner-chapter').inputValue() === '501', 'Direct visit did not use the current course lesson chapter');
                const names = await p.locator('#planner-catalog-list strong').allTextContents();
                assert(names.join('|') === 'Я — первый по программе|А — второй по программе', 'Lessons are not in program order');
            });
            await test('planner-selection-survives-chapters-pages-and-drilldown', async () => {
                const list = p.locator('#planner-catalog-list');
                await list.getByRole('checkbox', { name: 'Выбрать Я — первый по программе', exact: true }).check();
                await chooseChapter('1');
                await p.getByRole('button', { name: 'Следующая страница материалов' }).click(); await ready();
                assert(await p.locator('#planner-catalog-selected').innerText() === 'Выбрано: 1', 'Pagination lost selection');
                await p.getByRole('button', { name: 'Предыдущая страница материалов' }).click(); await ready();
                await list.getByRole('checkbox', { name: 'Выбрать Циклы и алгоритмы', exact: true }).check();
                await list.locator('.planner-catalog-card').filter({ hasText: 'Знакомство с Python' }).getByRole('button', { name: 'Открыть материалы' }).click(); await ready();
                assert((await p.locator('#planner-catalog-breadcrumb').innerText()).includes('Знакомство с Python'), 'Lesson drilldown is missing');
                await chooseChapter('501');
                assert(await p.locator('#planner-type').inputValue() === 'lesson', 'Chapter change kept the child type');
                assert(await p.locator('#planner-catalog-breadcrumb').innerText() === '', 'Chapter change kept an unrelated parent');
                assert(await list.getByRole('checkbox', { name: 'Выбрать Я — первый по программе', exact: true }).isChecked(), 'Selected checkbox was lost');
                await p.locator('#planner-catalog-selection summary').click();
                await p.getByRole('button', { name: 'Убрать из выбранного: Я — первый по программе', exact: true }).click();
                assert(!await list.getByRole('checkbox', { name: 'Выбрать Я — первый по программе', exact: true }).isChecked(), 'Removing selection did not update the checkbox');
                await list.getByRole('checkbox', { name: 'Выбрать Я — первый по программе', exact: true }).check();
                assert(await p.locator('#planner-catalog-selection-list li').count() === 2, 'Selected material summary is incomplete');
            });
            await test('planner-empty-chapter-and-all-chapters-search', async () => {
                await chooseChapter('502');
                assert(await p.locator('#planner-catalog-list .planner-catalog-card').count() === 0, 'Empty chapter contains materials');
                assert(await p.locator('#planner-catalog-selected').innerText() === 'Выбрано: 2', 'Empty chapter cleared selection');
                await chooseChapter('0');
                assert(await p.locator('#planner-catalog-list strong').innerText() === 'Материал без главы', 'Unassigned lesson is missing');
                await chooseChapter('');
                await p.locator('#planner-search').fill('программе'); await p.locator('#planner-search').press('Enter'); await ready();
                assert(await p.locator('#planner-catalog-list .planner-catalog-card').count() === 2, 'All-chapters search missed materials');
                await p.locator('#planner-search').fill(''); await p.locator('#planner-search').press('Enter'); await ready();
                await chooseChapter('501');
                for (const theme of ['light', 'dark']) {
                    await p.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1 && document.querySelector('#planner-catalog').scrollWidth <= document.querySelector('#planner-catalog').clientWidth + 1), 'Catalog overflows at ' + width);
                    await p.screenshot({ path: config.output + '/planner-catalog-' + theme + '-' + width + '.png' });
                }
                if (width < 768) {
                    await p.setViewportSize({ width: 320, height: 800 });
                    assert(await p.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1 && document.querySelector('#planner-catalog').scrollWidth <= document.querySelector('#planner-catalog').clientWidth + 1), 'Catalog overflows at 320');
                    await p.screenshot({ path: config.output + '/planner-catalog-dark-320.png' });
                    await p.setViewportSize({ width, height: 950 });
                }
                await p.evaluate(() => document.documentElement.dataset.bsTheme = 'light');
            });
            await test('planner-conflict-retains-selection-and-retry-attaches-both-chapters', async () => {
                const submit = p.getByRole('button', { name: 'Добавить выбранные материалы на занятие' });
                await p.route('**/commands/attach_many*', route => route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ code: 'revision_conflict', message: 'QA конфликт версии' }) }), { times: 1 });
                await submit.click();
                await p.locator('#planner-catalog-error').filter({ hasText: 'QA конфликт версии' }).waitFor();
                assert(await p.locator('#planner-catalog-selected').innerText() === 'Выбрано: 2', 'Conflict lost selection');
                const [response] = await Promise.all([p.waitForResponse(r => r.url().includes('/commands/attach_many') && r.request().method() === 'POST'), submit.click()]);
                assert(response.ok(), 'Could not retry attachment: ' + await response.text());
                await p.locator('#planner-catalog').waitFor({ state: 'hidden' });
                const session = p.locator('.planner-session[data-session="2"]');
                const text = await session.innerText();
                assert(text.includes('Я — первый по программе') && text.includes('Циклы и алгоритмы'), 'Materials from both chapters were not attached');
                await open(2);
                assert(await p.locator('#planner-chapter').inputValue() === '501', 'Manual chapter choice was forgotten');
                assert(await p.locator('#planner-catalog-list').getByRole('checkbox', { name: 'Выбрать Я — первый по программе', exact: true }).isDisabled(), 'Already added material can be selected again');
                await p.keyboard.press('Escape');
                assert(await session.locator('[data-action="session-materials"]').evaluate(el => document.activeElement === el), 'Catalog did not restore focus');
            });
        } catch (error) {
            errors.push({ width, error: error.message });
            await p.screenshot({ path: config.output + '/planner-catalog-failure-' + width + '.png' });
        } finally { await context.close(); }
    }
    return { results, errors };
}
