// Executed by Playwright CLI. All application data comes from router.php fixtures.
export default async function suite(page, config) {
    const results = [], errors = [], browser = page.context().browser();
    const assert = (value, message) => { if (!value) throw new Error(message); };
    const screens = [
        ['courses-student', '/insider/courses', 'student'], ['courses-teacher', '/insider/courses', 'teacher'],
        ['pulse-student', '/insider/pulse', 'student'], ['pulse-teacher', '/insider/pulse', 'teacher'],
        ['course-student', '/insider/courses/1', 'student'], ['course-teacher', '/insider/courses/1', 'teacher'],
        ['step-student', '/insider/courses/1/steps/1', 'student', 'reading'], ['step-teacher', '/insider/courses/1/steps/1', 'teacher', 'reading'],
        ['notebook', '/insider/courses/1/steps/1', 'student', 'notebook'], ['profile', '/insider/profile/1', 'student'],
        ['community', '/insider/community', 'student'], ['market', '/insider/market#market-digital', 'student'],
        ['purchases', '/insider/market#market-purchases', 'student'], ['orders', '/insider/market#market-orders', 'teacher'],
        ['planner', '/insider/courses/1/planner', 'teacher'], ['journal', '/insider/courses/1/assessments', 'teacher'],
        ['report', '/insider/courses/1/report', 'teacher'], ['reviews', '/insider/reviews', 'teacher'],
        ['course-edit', '/insider/courses/1/edit', 'teacher'], ['task-edit', '/insider/courses/1/tasks/11/edit', 'teacher'],
        ['ai-settings', '/insider/settings/ai', 'admin'], ['login', '/login', 'student'],
    ];
    const urlFor = (path, actor, scenario = 'base') => {
        const url = new URL(path, config.url);
        url.searchParams.set('actor', actor); url.searchParams.set('scenario', scenario);
        if (config.palette) url.searchParams.set('palette', config.palette);
        return url.href;
    };
    for (const width of config.widths) {
        const context = await browser.newContext({ viewport: { width, height: 950 }, hasTouch: width < 768, isMobile: width < 768, reducedMotion: 'reduce' });
        if (config.css) await context.route('**/build/css/app.css*', route => route.fulfill({ path: config.css, contentType: 'text/css' }));
        const target = await context.newPage();
        target.setDefaultTimeout(5000); target.setDefaultNavigationTimeout(15000);
        target.on('pageerror', error => errors.push({ width, error: error.message }));
        for (const [name, path, actor, scenario] of screens) {
            try {
                const response = await target.goto(urlFor(path, actor, scenario));
                assert(!response || response.ok(), `${name}: HTTP ${response?.status()}`);
                await target.waitForLoadState('networkidle');
                await target.evaluate(async () => { await window.MathJax?.startup?.promise; await document.fonts.ready; });
                if (name === 'planner') await target.locator('#planner-save-state').filter({ hasText: 'Сохранено' }).waitFor();
                await target.addStyleTag({ content: '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important;}' });
                if (name === 'login') await target.addStyleTag({ content: '.auth-shell-background{background-image:url("/images/bg/1.jpg")!important;}' });
                await target.mouse.move(1, 1);
                for (const theme of ['light', 'dark']) {
                    await target.evaluate(theme => { document.documentElement.setAttribute('data-bs-theme', theme); window.scrollTo(0, 0); }, theme);
                    const metrics = await target.evaluate(() => ({
                        overflow: document.documentElement.scrollWidth > innerWidth + 1,
                        styles: [...document.querySelectorAll('h1,.gc-page-header,.gc-card,.btn,input.form-control,select.form-select,.markdown pre,.markdown code,.step-media-figure,.planner-session,.gc-multiselect__toggle')].slice(0, 120).map(el => {
                            const s = getComputedStyle(el), r = el.getBoundingClientRect();
                            return { tag: el.tagName, classes: el.className, box: [r.x, r.y, r.width, r.height].map(v => Math.round(v * 10) / 10), color: s.color, background: s.backgroundColor, font: s.fontSize, padding: s.padding, display: s.display };
                        }),
                    }));
                    assert(!metrics.overflow, `${name}: document overflow at ${width}`);
                    await target.screenshot({ path: `${config.output}/${name}-${width}-${theme}.png`, animations: 'disabled' });
                    results.push({ name, width, theme, passed: true, ...metrics });
                }
            } catch (error) { errors.push({ name, width, error: error.message }); }
        }
        // Force both module-load orders. Only notebook cells may have Prism tokens.
        for (const delayed of ['vendor.js', 'notebook-render.js']) {
            await context.route(`**/build/js/${delayed}*`, async route => { await new Promise(resolve => setTimeout(resolve, 250)); await route.continue(); });
            for (const scenario of ['reading', 'notebook']) {
                try {
                    await target.goto(urlFor('/insider/courses/1/steps/1', 'student', scenario));
                    await target.waitForLoadState('networkidle');
                    const code = target.locator(scenario === 'reading' ? '.step-content pre code' : '.notebook-container pre code').first();
                    await code.waitFor();
                    if (scenario === 'reading') {
                        assert(await code.evaluate(el => el.classList.contains('hljs') && !el.querySelector('.token')), 'Reading highlighter changed with module order');
                        assert(await target.locator('[data-copy-code]').count() > 0, 'Missing code copy action');
                    } else {
                        assert(await code.locator('.token').count() > 0, 'Missing notebook highlighting');
                        assert(!await code.evaluate(el => el.classList.contains('hljs')), 'Notebook was highlighted twice');
                        assert(await target.locator('.gc-notebook').count() === 1, 'Notebook rendered twice');
                        assert(await target.locator('[data-step-reading-toc] a').count() === 2, 'Notebook outline not refreshed');
                    }
                    results.push({ name: `${scenario}-load-order-${delayed}`, width, passed: true });
                } catch (error) { errors.push({ scenario, delayed, width, error: error.message }); }
            }
            await context.unroute(`**/build/js/${delayed}*`);
        }
        await context.close();
    }
    return { results, errors };
}
