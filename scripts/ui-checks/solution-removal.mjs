export default async function solutionRemoval(controlPage, { url, output, widths = [1440, 390] }) {
    const results = [], errors = [];
    const id = task => `10000000-0000-4000-8000-${String(task).padStart(12, '0')}`;
    const check = (name, passed, width) => {results.push({name, passed, width}); if (!passed) throw new Error(name);};
    for (const width of widths) {
        const context = await controlPage.context().browser().newContext({viewport:{width,height:1000},reducedMotion:'reduce'});
        const page = await context.newPage();
        page.on('pageerror', error => errors.push({width,message:error.message}));
        const scenario = width > 600 ? 'submissions-desktop' : 'submissions-phone';
        const visit = async (task, attempt) => page.goto(`${url}/insider/courses/1/steps/1?actor=teacher&scenario=${scenario}&task=${task}&student=1&attempt=${attempt}#task${task}`);
        try {
            await visit(14,id(14));
            let root=page.locator('#execution-workspace-14');
            const open = async () => {await root.getByRole('button',{name:'Действия',exact:true}).click();await root.getByRole('button',{name:'Удалить решение…',exact:true}).click();};
            let deleted=0;page.on('request',request=>{if(request.method()==='DELETE'&&request.url().includes('/attempts/'))deleted++;});
            await open();
            let dialog=page.getByRole('dialog',{name:'Удалить решение?',exact:true});
            check('removal-confirmation-identifies-author-and-consequences',(await dialog.innerText()).includes('Ученик 1') && (await dialog.innerText()).includes('без возможности восстановления'),width);
            for(const theme of ['light','dark']) {
                await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);
                check('removal-confirmation-no-overflow-'+theme,await dialog.evaluate(el=>{const box=el.getBoundingClientRect();return box.left>=0&&box.right<=innerWidth+1;}),width);
                await page.screenshot({path:`${output}/solution-removal-${theme}-${width}.png`,fullPage:true});
            }
            await dialog.getByRole('button',{name:'Отмена',exact:true}).click();
            check('removal-cancel-does-not-send-request',deleted===0 && (await page.request.get(`${url}/insider/code-submissions/${id(14)}`)).status()===200,width);
            await open();dialog=page.getByRole('dialog',{name:'Удалить решение?',exact:true});
            const response=page.waitForResponse(response=>response.request().method()==='DELETE'&&response.url().includes('/attempts/'));
            await dialog.getByRole('button',{name:'Удалить решение',exact:true}).click();
            check('removal-native-delete-succeeds',(await response).status()===200,width);
            await page.waitForLoadState('domcontentloaded');
            check('removal-native-is-gone-other-work-remains',(await page.request.get(`${url}/insider/code-submissions/${id(14)}`)).status()===404 && (await page.request.get(`${url}/insider/code-submissions/${id(13)}`)).status()===200,width);
            await visit(15,'solution-115');root=page.locator('#execution-workspace-15');await open();
            const legacyResponse=page.waitForResponse(response=>response.request().method()==='DELETE'&&response.url().endsWith('/attempts/solution-115'));
            await page.getByRole('dialog',{name:'Удалить решение?',exact:true}).getByRole('button',{name:'Удалить решение',exact:true}).click();
            check('removal-historical-attempt-succeeds',(await legacyResponse).status()===200,width);
            await page.waitForLoadState('domcontentloaded');
            check('removal-historical-record-is-gone',(await page.request.get(`${url}/insider/courses/1/tasks/15/code-attempts/115`)).status()===404,width);
            await page.goto(`${url}/insider/reviews?actor=teacher&view=recent`);
            const row=page.locator('[data-review-activity^="solution-"]').first();
            const key=await row.getAttribute('data-review-activity');
            page.once('dialog',dialog=>dialog.dismiss());await row.getByRole('button',{name:/^Удалить решение/}).click();
            check('removal-feed-cancel-keeps-record',await page.locator(`[data-review-activity="${key}"]`).count()===1,width);
            page.once('dialog',dialog=>dialog.accept());
            await Promise.all([page.waitForURL(/\/insider\/reviews/),row.getByRole('button',{name:/^Удалить решение/}).click()]);
            await page.locator(`[data-review-activity="${key}"]`).waitFor({state:'detached'});
            check('removal-feed-action-removes-only-selected-row',await page.locator('[data-review-activity]').count()>0 && await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),width);
            await page.goto(`${url}/insider/courses/1/steps/1?actor=student&task=13&attempt=${id(13)}#task13`);
            await page.locator('#execution-workspace-13 .gc-execution-result').waitFor();
            check('removal-student-has-no-delete-control',await page.getByRole('button',{name:/^Удалить решение/}).count()===0,width);
        } finally {await context.close();}
    }
    return {results,errors};
}
