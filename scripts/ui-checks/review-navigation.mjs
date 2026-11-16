export default async function reviewNavigation(controlPage, {url,output,widths=[1440,390]}) {
    const results=[],errors=[];
    const check=(name,passed,width)=>{results.push({name,passed,width});if(!passed)throw new Error(name);};
    for(const width of widths) {
        const context=await controlPage.context().browser().newContext({viewport:{width,height:1000},reducedMotion:'reduce'});
        const page=await context.newPage();page.on('pageerror',error=>errors.push({width,message:error.message}));
        const listURL=`${url}/insider/courses/1/steps/1?actor=teacher&scenario=submissions&task=13#task13`;
        const root=page.locator('#execution-workspace-13');
        const list=page.locator('#student-solutions-13 [data-execution-student-list]');
        const student=n=>list.locator(`[data-student-id="${n}"]`);
        const heading=n=>root.getByRole('heading',{name:`Ученик ${n}`,exact:true});
        const loaded=async n=>{await heading(n).waitFor();await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});};
        const back=()=>root.getByRole('button',{name:'К списку работ',exact:true}).click();
        try {
            await page.goto(listURL);await list.waitFor();
            await list.getByRole('searchbox',{name:'Найти ученика',exact:true}).fill('Ученик');
            await student(1).click();await loaded(1);
            const length=await page.evaluate(()=>history.length);
            await root.getByRole('button',{name:'Следующая работа',exact:true}).click();await loaded(3);
            check('review-next-opens-the-next-student-work',new URL(page.url()).searchParams.get('student')==='3',width);
            await root.getByRole('button',{name:'Предыдущая работа',exact:true}).click();await loaded(1);
            check('review-arrows-do-not-add-history-entries',await page.evaluate(()=>history.length)===length,width);
            await root.getByRole('button',{name:'Следующая работа',exact:true}).click();await loaded(3);
            await back();await list.waitFor();
            check('review-back-always-returns-to-list',await root.isHidden()&&!new URL(page.url()).searchParams.has('student')&&!new URL(page.url()).searchParams.has('attempt'),width);
            check('review-back-preserves-search-and-task',await list.getByRole('searchbox',{name:'Найти ученика',exact:true}).inputValue()==='Ученик'&&new URL(page.url()).searchParams.get('task')==='13',width);
            await page.reload();await list.waitFor();
            check('review-list-survives-reload',await root.isHidden(),width);
            await student(1).click();await loaded(1);
            await root.getByRole('button',{name:'Следующая работа',exact:true}).click();await loaded(3);
            await page.goBack();await list.waitFor();
            check('review-browser-back-restores-list',await root.isHidden(),width);
            await page.goForward();await loaded(3);
            check('review-browser-forward-restores-current-work',await list.locator('[aria-current="true"]').count()===1&&await student(3).getAttribute('aria-current')==='true',width);
            await root.getByRole('button',{name:'Предыдущая работа',exact:true}).click();await loaded(1);
            await page.route('**/tasks/13/code-submissions?**',async route=>{
                if(new URL(route.request().url()).searchParams.get('user_id')==='3')await route.fulfill({status:503,json:{message:'Тест: сервер занят'}});
                else await route.continue();
            });
            await root.getByRole('button',{name:'Следующая работа',exact:true}).click();
            await root.getByText('Тест: сервер занят',{exact:true}).waitFor();
            check('review-error-does-not-show-previous-student-work',await root.locator('.gc-inline-grade,.gc-submitted-work,.gc-result-score').count()===0,width);
            await page.screenshot({path:`${output}/review-navigation-error-${width}.png`,fullPage:true});
            await back();await list.waitFor();
            check('review-error-keeps-a-working-return-to-list',await root.isHidden(),width);
            await page.unroute('**/tasks/13/code-submissions?**');
            await page.goto(listURL.replace('#task13','&student=1&attempt=10000000-0000-4000-8000-000000000013#task13'));await loaded(1);
            await back();await list.waitFor();
            check('review-deep-link-return-stays-on-the-task-list',await root.isHidden()&&new URL(page.url()).pathname==='/insider/courses/1/steps/1'&&!new URL(page.url()).searchParams.has('student'),width);
            await list.getByRole('searchbox',{name:'Найти ученика',exact:true}).fill('Ученик 1');
            await student(1).click();await loaded(1);
            check('review-navigation-respects-the-list-filter',await root.getByRole('button',{name:'Следующая работа',exact:true}).count()===0,width);
        } finally {await context.close();}
    }
    return {results,errors};
}
