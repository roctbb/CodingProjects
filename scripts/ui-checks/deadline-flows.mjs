// Isolated fixtures only: these scenarios never send Telegram or paid AI requests.
export default async function deadlineFlows(page, config) {
    const results=[], errors=[], browser=page.context().browser();
    const assert=(ok,message)=>{if(!ok)throw new Error(message);};
    for(const width of config.widths) {
        const context=await browser.newContext({viewport:{width,height:950},hasTouch:width<768,isMobile:width<768,reducedMotion:'reduce'});
        const target=await context.newPage(); target.setDefaultTimeout(7000);
        target.on('pageerror',error=>errors.push({name:'deadline-flows',width,error:error.message}));
        const open=async(path,actor='teacher')=>{
            const url=new URL(path,config.url); url.searchParams.set('actor',actor);url.searchParams.set('scenario','base');
            const response=await target.goto(url.href); assert(response.ok(),`${path}: HTTP ${response.status()}`);await target.waitForLoadState('networkidle');
        };
        try {
            await open('/insider/courses/1');
            await target.locator('#lesson-1 .course-path-actions [data-bs-toggle="dropdown"]').click();
            await target.locator('[data-bs-target="#lesson-deadline-modal-1"]').click();
            const modal=target.locator('#lesson-deadline-modal-1'); await modal.waitFor({state:'visible'});
            assert(await modal.locator('[data-deadline-row]').count()===2,'lesson chain missing');
            assert(await modal.locator('[name="apply_to_tasks"]').isChecked(),'lesson deadline does not replace task deadlines by default');
            await modal.locator('[data-deadline-add]').click();
            const added=modal.locator('[data-deadline-row]').last();
            await added.locator('input[type="date"]').fill('2026-11-29');
            await added.locator('input[type="number"]').fill('25');
            for(const theme of ['light','dark']) {
                await target.evaluate(theme=>document.documentElement.setAttribute('data-bs-theme',theme),theme);
                assert(await modal.evaluate(el=>el.scrollWidth<=el.clientWidth+1),'deadline modal horizontal overflow');
                assert(await modal.locator('.modal-footer').evaluate(el=>el.getBoundingClientRect().bottom<=innerHeight && el.getBoundingClientRect().top>=0),'save buttons outside viewport');
                await target.screenshot({path:`${config.output}/deadline-editor-${width}-${theme}.png`});
                results.push({name:'deadline-editor',width,theme});
            }
            await modal.getByRole('button',{name:'Сохранить',exact:true}).click();await target.waitForLoadState('networkidle');
            await target.locator('#lesson-1 .course-path-actions [data-bs-toggle="dropdown"]').click();
            await target.locator('[data-bs-target="#lesson-deadline-modal-1"]').click();
            assert(await modal.locator('[data-deadline-row]').count()===3,'third stage not saved');
            await modal.locator('[data-deadline-row]').last().locator('[data-deadline-remove]').click();
            await modal.getByRole('button',{name:'Сохранить',exact:true}).click();await target.waitForLoadState('networkidle');
            await open('/insider/courses/1/deadlines','student');
            assert(await target.locator('[data-deadline-summary]').count()>0,'student conditions missing');
            assert(await target.locator('form[action$="/notifications/preference"]').count()===0,'reminder form is still on deadlines');
            assert(await target.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'student deadline overflow');
            results.push({name:'student-deadlines',width});
            await open('/insider/courses/1/notifications');
            assert(await target.getByText('Учебная группа Python',{exact:true}).count()===1,'course chat not visible');
            for(const theme of ['light','dark']) {
                await target.evaluate(theme=>document.documentElement.setAttribute('data-bs-theme',theme),theme);
                assert(await target.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'notification page overflow');
                await target.screenshot({path:`${config.output}/course-notifications-${width}-${theme}.png`,fullPage:true});
                results.push({name:'course-notifications',width,theme});
            }
            await open('/insider/settings/ai','admin');
            assert(await target.locator('[data-ai-provider]').count()===1,'more than one AI protocol');
            assert(await target.locator('input[name="settings[provider]"]').inputValue()==='openai','AI protocol incorrect');
            assert(await target.locator('input[name*="gateway"]').count()===0,'custom gateway still exposed');
            results.push({name:'ai-openai-only',width});
            await open('/insider/courses/1/planner');
            await target.locator('#planner-save-state').filter({hasText:'Сохранено'}).waitFor();
            const entry=target.locator('.planner-entry').filter({has:target.locator('[data-action="class-bonus"]')}).first();
            await entry.locator('.planner-entry-menu summary').click();
            await entry.locator('[data-action="class-bonus"]').click();
            const dialog=target.locator('#planner-dialog');
            await dialog.locator('[name="class_bonus_gc"]').fill('8');
            await dialog.locator('#planner-dialog-submit').click();await dialog.waitFor({state:'hidden'});
            assert(await target.getByText('+8 GC за задачу на занятии',{exact:true}).count()>0,'bonus not displayed');
            await target.screenshot({path:`${config.output}/planner-class-bonus-${width}.png`});
            results.push({name:'planner-class-bonus',width});
            await target.clock.install();
            await open('/insider/courses/1/deadlines');
            const summary=target.locator('[data-deadline-task-id="11"] [data-deadline-summary]');
            const schedule=await summary.evaluate(root=>({now:Number(root.dataset.serverNow),cutoffs:[...root.querySelectorAll('[data-deadline-stage]')].map(row=>Number(row.dataset.cutoff))}));
            const dates=summary.locator('[data-deadline-stage]');
            const calendar=summary.locator('[data-deadline-calendar]');
            assert(await calendar.getAttribute('datetime')===await dates.first().getAttribute('data-datetime'),'first date missing');
            assert(await summary.locator('[data-deadline-stage]:visible').count()===0,'full schedule must be collapsed');
            await target.clock.fastForward(schedule.cutoffs[0]-schedule.now+10);
            assert((await summary.locator('[data-deadline-current]').textContent())==='Сейчас −30%','current penalty did not advance automatically');
            assert(await calendar.getAttribute('datetime')===await dates.last().getAttribute('data-datetime'),'next deadline did not advance');
            await target.clock.fastForward(schedule.cutoffs.at(-1)-schedule.cutoffs[0]+10);
            assert(await summary.locator('[data-deadline-current]').textContent()==='Сейчас −100%','last penalty missing');
            assert(await target.locator('[data-deadline-task-id="11"] [data-deadline-last-label]').isVisible(),'last deadline is unlabelled');
            await summary.locator('.gc-deadline-popover__trigger').click();
            assert(await summary.locator('[data-deadline-stage]:visible').count()===2,'complete schedule missing');
            results.push({name:'deadline-current-penalty-and-live-rollover',width});
        } catch(error){errors.push({name:'deadline-flows',width,error:error.message});}
        await context.close();
    }
    return {results,errors};
}
