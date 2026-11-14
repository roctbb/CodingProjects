// Browser flows use only synthetic accounts and the disposable QA database.
export default async function interactions(page, config) {
    const results = [], errors = [];
    const assert = (condition, message) => { if (!condition) throw new Error(message); };
    for (const width of config.widths) {
        const context = await page.context().browser().newContext({ viewport: { width, height: 950 }, isMobile: width < 768, hasTouch: width < 768, reducedMotion: 'reduce' });
        if (config.css) await context.route('**/build/css/app.css*', route => route.fulfill({ path: config.css, contentType: 'text/css' }));
        const p = await context.newPage();
        p.setDefaultTimeout(5000); p.setDefaultNavigationTimeout(15000);
        p.on('pageerror', error => errors.push({ width, error: error.message }));
        const scenario = width < 768 ? 'catalog-phone' : 'catalog-desktop';
        const go = async (path, actor = 'teacher') => {
            const url = new URL(path, config.url);
            url.searchParams.set('actor', actor); url.searchParams.set('scenario', scenario);
            if (config.palette) url.searchParams.set('palette', config.palette);
            await p.goto(url.href); await p.waitForLoadState('networkidle');
        };
        const test = async (name, run) => {
            await run(); results.push({ name, width, passed: true });
        };
        const waitSaved = () => p.waitForFunction(() => !document.querySelector('#course-planner').hasAttribute('aria-busy'));
        const command = async (name, run) => {
            const [response] = await Promise.all([p.waitForResponse(response => response.url().includes('/commands/' + name) && response.request().method() === 'POST'), run()]);
            assert(response.ok(), `${name}: HTTP ${response.status()}: ${await response.text()}`);
            await waitSaved();
        };
        const submit = () => p.locator('#planner-dialog-submit').click();
        const menuAction = async (scope, name) => {
            const button = scope.locator(`[data-action="${name}"]`).first();
            const menu = scope.locator(`details:has([data-action="${name}"])`).last();
            if (!await menu.evaluate(el => el.open)) await menu.locator('summary').first().click();
            await button.click();
        };
        const catalogReady = () => p.waitForFunction(() => !document.querySelector('#planner-catalog-list').hasAttribute('aria-busy'));
        try {
            await go('/insider/courses/1/planner');
            await p.locator('#planner-save-state').filter({ hasText: 'Сохранено' }).waitFor();
            const first = p.locator('.planner-session[data-session]').first();
            const second = p.locator('.planner-session[data-session]').nth(1);
            await test('catalog-pagination-search-duplicates', async () => {
                await first.locator('[data-action="session-materials"]').click(); await catalogReady();
                const list = p.locator('#planner-catalog-list');
                assert(await list.locator('.planner-catalog-card').count() === 25, 'Catalog did not limit the page');
                await p.getByRole('button', { name: 'Следующая страница материалов' }).click(); await catalogReady();
                assert(await p.locator('#planner-page').innerText() === 'Страница 2', 'Catalog did not change page');
                await p.locator('#planner-search').fill('QA материал 03'); await p.locator('#planner-search').press('Enter'); await catalogReady();
                assert(await list.getByRole('checkbox').count() === 1, 'Catalog search did not narrow the results');
                await list.getByRole('checkbox').check();
                await command('attach_many', () => p.getByRole('button', { name: 'Добавить выбранные материалы на занятие' }).click());
                assert((await first.innerText()).includes('QA материал 03'), 'Attached material is missing');
                await first.locator('[data-action="session-materials"]').click(); await catalogReady();
                assert(await list.getByRole('checkbox').isDisabled(), 'Duplicate material is selectable');
                await p.keyboard.press('Escape');
                assert(await first.locator('[data-action="session-materials"]').evaluate(el => el === document.activeElement), 'Catalog did not restore focus');
            });
            await test('past-session-comment', async () => {
                await first.locator('[data-action="record"]:visible').click();
                await p.locator('#planner-session-comment').fill('QA комментарий <b>текст</b>');
                await command('comment', submit);
                assert((await first.innerText()).includes('QA комментарий <b>текст</b>'), 'Comment was not saved');
                assert(await first.locator('.planner-session-comment b').count() === 0, 'Comment was interpreted as HTML');
            });
            await test('material-edit-move-undo', async () => {
                await menuAction(first, 'add-text'); await p.locator('#planner-note-text').fill('QA заметка');
                await command('add_text', submit);
                let entry = first.locator('.planner-entry').filter({ hasText: 'QA заметка' });
                await menuAction(entry, 'edit-text'); await p.locator('#planner-note-text').fill('QA изменённая заметка');
                await command('update_entry', submit);
                entry = first.locator('.planner-entry').filter({ hasText: 'QA изменённая заметка' });
                if (width >= 768) {
                    await command('move_entry', () => entry.dragTo(second));
                } else {
                    await menuAction(entry, 'move-entry');
                    await p.locator('#planner-field-target_session_id').selectOption(await second.getAttribute('data-session'));
                    await command('move_entry', submit);
                }
                assert((await second.innerText()).includes('QA изменённая заметка'), 'Material was not moved');
                await command('undo', () => menuAction(p.locator('#course-planner'), 'undo'));
                assert((await first.innerText()).includes('QA изменённая заметка'), 'Undo did not restore material');
            });
            await test('revision-conflict-and-retry', async () => {
                await menuAction(first, 'add-text'); await p.locator('#planner-note-text').fill('QA повтор');
                await p.route('**/commands/add_text', route => route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ code: 'revision_conflict', message: 'QA конфликт ревизии' }) }), { times: 1 });
                await submit(); await p.locator('#planner-dialog-error').filter({ hasText: 'QA конфликт' }).waitFor(); await waitSaved();
                assert(await p.locator('#planner-dialog').evaluate(el => el.open), 'Conflict closed the form');
                assert(!await p.locator('#planner-dialog-submit').isDisabled(), 'Retry is disabled');
                await command('add_text', submit);
            });
            await test('providers-summary-period-navigation', async () => {
                await menuAction(p.locator('#course-planner'), 'providers');
                assert(await p.locator('[data-provider-settings]').count() > 0, 'Provider settings are empty');
                await command('providers', submit);
                await p.locator('#planner-view').selectOption('overview');
                assert(await p.locator('#planner-overview-panel').isVisible(), 'Overview is hidden');
                await p.locator('[data-action="summary-detail"]').first().click();
                await p.locator('#planner-dialog[open]').waitFor();
                await p.locator('#planner-dialog footer [data-action="close-dialog"]').click();
                await p.locator('#planner-view').selectOption('calendar');
                const period = await p.locator('#planner-period-label').innerText();
                await p.locator('[data-action="next-range"]').click();
                await p.waitForFunction(previous => document.querySelector('#planner-period-label').textContent !== previous, period);
                await p.locator('[data-action="today"]').click();
                await p.waitForFunction(previous => document.querySelector('#planner-period-label').textContent === previous, period);
            });
            await test('manual-date-exclusion-and-restoration', async () => {
                // The fixture deliberately has manual dates and no weekly schedule.
                await menuAction(second, 'exclude');
                await p.locator('#planner-field-reason').fill('QA каникулы');
                await command('add_exception', submit);
                assert((await p.locator('#planner-calendar').innerText()).includes('QA каникулы'), 'Excluded date is missing');
                await command('remove_exception', () => p.locator('[data-action="remove-exception"]').first().click());
                assert(!(await p.locator('#planner-calendar').innerText()).includes('QA каникулы'), 'Date was not restored');
            });
            await test('custom-frame-preview-and-focus', async () => {
                await go('/insider/market#market-digital', 'student');
                const opener = p.locator('[data-market-frame-modal-open]');
                await opener.click();
                await p.locator('#market-frame-type').selectOption('conic');
                await p.locator('#market-frame-motion').selectOption('pulse');
                await p.locator('[data-frame-width]').fill('12');
                const preview = p.locator('[data-market-frame-modal] [data-avatar-frame-preview]');
                assert((await preview.getAttribute('style')).includes('12px'), 'Frame preview did not update');
                await p.waitForFunction(() => getComputedStyle(document.querySelector('[data-market-frame-modal] [data-avatar-frame-preview]')).padding === '12px');
                const cost = await p.locator('[data-market-frame-modal] [data-frame-expected-cost]').inputValue();
                assert(await p.locator('[data-market-frame-modal] [data-frame-price]').innerText() === cost, 'Preview and purchase costs differ');
                for (const theme of ['light', 'dark']) {
                    await p.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
                    const styles = await preview.evaluate(el => {
                        const s = getComputedStyle(el), r = el.getBoundingClientRect();
                        return [{ box: [r.x, r.y, r.width, r.height], color: s.color, background: s.backgroundImage, shadow: s.boxShadow, radius: s.borderRadius, padding: s.padding }];
                    });
                    results.push({ name: 'frame-preview', width, theme, passed: true, styles });
                    await p.screenshot({ path: `${config.output}/frame-dialog-${width}-${theme}.png`, animations: 'disabled' });
                }
                await p.keyboard.press('Escape');
                assert(await opener.evaluate(el => el === document.activeElement), 'Frame dialog did not restore focus');
            });
            await test('purchase-confirmation-and-order-refund', async () => {
                await go('/insider/market', 'student');
                const purchase = p.locator('form[action$="/market/1/buy"]');
                const key = await purchase.locator('[name="purchase_key"]').inputValue();
                assert(key.length === 36 && await purchase.getAttribute('method') === 'POST', 'Purchase lacks POST or retry key');
                p.once('dialog', dialog => dialog.dismiss());
                await purchase.getByRole('button').click();
                assert(await purchase.isVisible(), 'Cancelled confirmation submitted a purchase');
                p.once('dialog', dialog => dialog.accept());
                const [response] = await Promise.all([
                    p.waitForResponse(r => r.url().endsWith('/market/1/buy') && r.request().method() === 'POST'),
                    purchase.getByRole('button').click(),
                ]);
                assert(response.status() === 302, `Purchase: HTTP ${response.status()}`);
                await p.waitForLoadState('networkidle');
                const alert = await p.locator('body').innerText();
                const order = alert.match(/Заказ №(\d+) доступен/);
                assert(order, 'Purchase did not return the created order');
                await go('/insider/market#market-orders', 'teacher');
                const cancel = p.locator(`form[action$="/market/cancel/${order[1]}"]`);
                assert(await cancel.count() === 1, 'New order is missing from the delivery queue');
                await p.screenshot({ path: `${config.output}/order-actions-${width}.png`, fullPage: true });
                p.once('dialog', dialog => dialog.accept());
                const [refund] = await Promise.all([
                    p.waitForResponse(r => r.url().endsWith(`/market/cancel/${order[1]}`) && r.request().method() === 'POST'),
                    cancel.getByRole('button').click(),
                ]);
                assert(refund.status() === 302, `Refund: HTTP ${refund.status()}`);
                await p.waitForLoadState('networkidle');
                assert(await p.locator(`form[action$="/market/cancel/${order[1]}"]`).count() === 0, 'Cancelled order remains in delivery queue');
                await go('/insider/market#market-purchases', 'student');
                const history = p.locator('.gc-purchase-row').filter({ hasText: `№${order[1]}` });
                assert((await history.innerText()).includes('Отменён · GC возвращены'), 'Refund erased order history');
            });
        } catch (error) {
            errors.push({ width, error: error.message });
            await p.screenshot({ path: `${config.output}/interaction-failure-${width}.png` });
        } finally { await context.close(); }
    }
    return { results, errors };
}
