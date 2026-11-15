export default async function lessonStatistics(controlPage, {url, output, widths = [1440,390], palette = ''}) {
    const results = [], errors = [];
    const check = (name, passed, width, details = {}) => {
        results.push({name, width, passed, ...details});
        if (!passed) throw new Error(name + ': ' + JSON.stringify(details));
    };
    for (const width of widths) {
        const context = await controlPage.context().browser().newContext({viewport:{width,height:1000},isMobile:width<600,hasTouch:width<600,reducedMotion:'reduce'});
        const page = await context.newPage();
        page.on('pageerror', error => errors.push({name:'lesson-statistics-js',width,message:error.message}));
        const query = new URLSearchParams({actor:'teacher',scenario:width>600?'statistics-desktop':'statistics-phone'});
        if (palette) query.set('palette',palette);
        try {
            await page.goto(`${url}/insider/courses/1?${query}`);
            await page.locator('[data-course-stats-target="#marks2"]').click();
            const panel = page.locator('#marks2');
            const rows = panel.locator('.course-stat-row');
            check('lesson-stats-first-page',await rows.count()===25 && (await panel.innerText()).includes('1–25 из 70'),width);
            const geometry = await rows.first().evaluate(el => {
                const name=el.querySelector('.course-stat-name'), style=getComputedStyle(name);
                return {height:el.getBoundingClientRect().height,overflow:document.documentElement.scrollWidth>innerWidth,wrap:style.whiteSpace,nameFits:name.scrollWidth<=name.clientWidth+1};
            });
            check('lesson-stats-full-names-and-touch-target',geometry.height>=44 && geometry.wrap!=='nowrap' && geometry.nameFits && !geometry.overflow,width,geometry);
            const accent = await panel.locator('.course-stat-reward').first().evaluate(el=>getComputedStyle(el).color);
            check('lesson-stats-orange-reward',palette ? !!accent : accent==='rgb(255, 161, 23)',width,{accent});
            await page.screenshot({path:`${output}/lesson-statistics-${width}.png`,fullPage:true});
            await panel.getByRole('button',{name:'Следующие ученики',exact:true}).click();
            await panel.getByText('26–50 из 70',{exact:true}).waitFor();
            check('lesson-stats-second-page',await rows.count()===25,width);
            await panel.getByRole('button',{name:'Следующие ученики',exact:true}).click();
            await panel.getByText('51–70 из 70',{exact:true}).waitFor();
            check('lesson-stats-last-page',await rows.count()===20 && await panel.getByRole('button',{name:'Следующие ученики',exact:true}).isDisabled(),width);
            await panel.getByRole('button',{name:'Предыдущие ученики',exact:true}).click();
            await panel.getByText('26–50 из 70',{exact:true}).waitFor();
            await panel.getByRole('button',{name:'Предыдущие ученики',exact:true}).click();
            await panel.getByText('1–25 из 70',{exact:true}).waitFor();
            const target = rows.filter({hasText:'Александров-Рождественский Александр Константинович'});
            const href = new URL(await target.getAttribute('href'));
            check('lesson-stats-context-link',href.searchParams.get('student')==='101' && href.searchParams.get('lesson')==='2',width);
            await target.click();
            await page.locator('#student101.active #student101marks2.show').waitFor();
            check('lesson-stats-opens-exact-report',await page.locator('#student101.active').count()===1 && await page.locator('#student101marks2 [data-report-task-template]').count()===0,width);
            check('lesson-stats-report-no-overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),width);
            await page.screenshot({path:`${output}/lesson-statistics-report-${width}.png`,fullPage:true});
            await page.reload();
            await page.locator('#student101.active #student101marks2.show').waitFor();
            check('lesson-stats-report-context-survives-reload',true,width);
            if (width < 600) await page.locator('.gc-report-student-picker').click();
            await page.locator('#student100-tab').click();
            await page.locator('#student100.active').waitFor();
            await page.reload();
            await page.locator('#student100.active #student100marks2.show').waitFor();
            check('lesson-stats-report-navigation-updates-context',new URL(page.url()).searchParams.get('student')==='100',width);
        } finally { await context.close(); }
    }
    return {results,errors};
}
