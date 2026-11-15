export default async function taskWorkspace(controlPage, {url, output, widths = [1440,390], palette = ''}) {
    const results=[],errors=[];
    const id=n=>`10000000-0000-4000-8000-${String(n).padStart(12,'0')}`;
    const check=(name,passed,width,details={})=>{results.push({name,width,passed,...details});if(!passed)throw new Error(name+': '+JSON.stringify(details));};
    for(const width of widths){
        const context=await controlPage.context().browser().newContext({viewport:{width,height:1000},isMobile:width<600,hasTouch:width<600});
        const page=await context.newPage();page.setDefaultTimeout(7000);
        page.on('pageerror',error=>errors.push({name:'task-workspace-js',width,message:error.message}));
        const scenario=width>600?'submissions-desktop':'submissions-phone';
        const visit=async(actor,task,attempt=null)=>{
            const query=new URLSearchParams({scenario,actor,task:String(task)});
            if(palette)query.set('palette',palette);
            if(attempt){query.set('student','1');query.set('attempt',attempt);}
            await page.goto(`${url}/insider/courses/1/steps/1?${query}#task${task}`);
        };
        try{
            await page.setViewportSize({width,height:1000});
            await visit('student',12,id(1));
            const root=page.locator('#execution-workspace-12');
            const worksheet=await root.evaluate(el=>{
                const sheet=el.closest('.gc-task-sheet'),context=sheet.querySelector('.gc-task-context');
                return {conditionAbove:context.getBoundingClientRect().bottom<=el.getBoundingClientRect().top+1,
                    reward:getComputedStyle(sheet.querySelector('.gc-task-reward i')).color,
                    paper:getComputedStyle(sheet).backgroundColor,ground:getComputedStyle(sheet.closest('.step-main')).backgroundColor};
            });
            check('task-worksheet-layout-and-two-colors',worksheet.conditionAbove && worksheet.paper===worksheet.ground && (palette || worksheet.reward==='rgb(255, 161, 23)'),width,worksheet);
            await root.getByRole('button',{name:'Исправить решение',exact:true}).click();
            await root.getByRole('button',{name:'Код',exact:true}).click();
            const code=root.locator('.cm-content');await code.fill('def largest(values):\n    return max(values)\n# saved draft');
            await root.locator('.gc-draft-state').filter({hasText:'Черновик сохранён'}).waitFor();
            const draft=await (await page.request.get(url+'/insider/courses/1/tasks/12/code-draft')).json();
            check('task-draft-autosave',draft.files[0].content.includes('# saved draft'),width);
            await root.getByRole('button',{name:'Вернуться к результату',exact:true}).click();
            await root.locator('.gc-execution-result').waitFor();
            await root.getByRole('button',{name:'Исправить решение',exact:true}).click();
            check('task-draft-survives-history',(await code.innerText()).includes('# saved draft'),width);
            await root.getByRole('button',{name:'Вернуться к результату',exact:true}).click();
            await root.getByRole('button',{name:'Данные теста 2',exact:true}).click();
            const purchase=root.getByRole('button',{name:'Открыть за 5 GC',exact:true});await purchase.waitFor();
            page.once('dialog',dialog=>dialog.accept());await purchase.click();
            await root.locator('.gc-test-detail:not([hidden]) .gc-test-data').filter({hasText:'-8 -2 -5'}).waitFor();
            check('task-purchased-test-stays-open',await root.getByRole('button',{name:'Данные теста 2',exact:true}).getAttribute('aria-expanded')==='true',width);
            await root.getByRole('button',{name:'Попросить перепроверку',exact:true}).click();
            await page.getByRole('textbox',{name:'Что преподавателю нужно проверить?',exact:true}).fill('Проверьте, пожалуйста, второй тест и моё объяснение.');
            await page.getByRole('button',{name:'Отправить запрос',exact:true}).click();
            await root.getByText('Запрос на перепроверку отправлен',{exact:true}).waitFor();
            check('task-student-recheck-request',true,width);
            const picker=root.getByRole('combobox',{name:'Выбрать попытку',exact:true});
            const before=(await picker.boundingBox()).y;
            await page.route('**/code-submissions/*?test_data=metadata',async route=>{await new Promise(resolve=>setTimeout(resolve,300));await route.continue().catch(()=>{});});
            await picker.selectOption(id(31));await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor();
            const during=(await picker.boundingBox()).y;
            await picker.selectOption(id(1));await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            check('task-attempt-no-scroll-jump',Math.abs(before-during)<1,width,{before,during});
            check('task-latest-selection-wins',(await picker.inputValue())===id(1),width);
            await page.unroute('**/code-submissions/*?test_data=metadata');
            await page.screenshot({path:`${output}/task-student-${width}.png`,fullPage:true});

            await visit('student',15,'solution-115');
            const archive=page.locator('#execution-workspace-15');
            await archive.getByRole('tab',{name:'src/helper.py',exact:true}).click();
            await archive.locator('pre').filter({hasText:'def answer()'}).waitFor();
            check('task-legacy-zip-source-and-xp',(await archive.getByRole('combobox',{name:'Выбрать попытку',exact:true}).inputValue())==='solution-115' && (await archive.locator('.gc-result-score > strong').innerText())==='6',width);

            await visit('student',14);
            const uploadRoot=page.locator('#execution-workspace-14');
            await uploadRoot.getByRole('button',{name:'Исправить решение',exact:true}).click();
            await uploadRoot.getByRole('textbox',{name:'Ответ',exact:true}).fill('Объясняю решение и прикладываю файлы.');
            await uploadRoot.locator('input[type="file"]').setInputFiles([
                {name:'a.bin',mimeType:'application/octet-stream',buffer:Buffer.from([0,255,1])},
                {name:'b.bin',mimeType:'application/octet-stream',buffer:Buffer.from([0,254,2])}
            ]);
            await uploadRoot.locator('.gc-draft-state').filter({hasText:'Черновик сохранён'}).waitFor();
            await page.route('**/tasks/14/code-draft',async route=>{
                if(route.request().method()==='GET'){await route.continue();return;}
                const response=await route.fetch();await new Promise(resolve=>setTimeout(resolve,600));await route.fulfill({response});
            });
            const saving=page.waitForRequest(request=>request.url().endsWith('/tasks/14/code-draft') && request.method()==='PUT');
            await uploadRoot.locator('.gc-upload-list li').filter({hasText:'a.bin'}).getByRole('button',{name:'Убрать',exact:true}).click();
            await saving;
            await uploadRoot.getByRole('textbox',{name:'Ответ',exact:true}).fill('Текст изменён во время сохранения вложений.');
            await uploadRoot.locator('.gc-draft-state').filter({hasText:'Черновик сохранён'}).waitFor();
            await page.unroute('**/tasks/14/code-draft');
            const fileDraft=await (await page.request.get(url+'/insider/courses/1/tasks/14/code-draft')).json();
            check('task-autosave-keeps-concurrent-edits',fileDraft.text==='Текст изменён во время сохранения вложений.' && fileDraft.files.length===1 && fileDraft.files[0].path==='b.bin',width);
            const bytes=await (await page.request.get(fileDraft.files[0].download_url)).body();
            check('task-binary-upload-preserved',bytes.equals(Buffer.from([0,254,2])),width);

            await visit('teacher',16,id(16));
            const paged=page.locator('#execution-workspace-16');
            await paged.getByRole('button',{name:'Страница тестов 2',exact:true}).waitFor();
            const testRows=paged.locator('.gc-test-table tbody > tr:not(.gc-test-detail)');
            check('task-test-page-size',await testRows.count()===10 && (await testRows.last().innerText()).includes('№ 10'),width);
            await paged.getByRole('button',{name:'Страница тестов 2',exact:true}).click();
            await paged.getByRole('button',{name:'Данные теста 11',exact:true}).click();
            await paged.locator('.gc-test-detail:not([hidden]) .gc-test-data strong').filter({hasText:'Ввод'}).waitFor();
            await paged.getByRole('button',{name:'Страница тестов 3',exact:true}).click();
            check('task-last-test-page',await testRows.count()===3 && await paged.getByRole('button',{name:'Следующая страница тестов',exact:true}).isDisabled(),width);
            await paged.getByRole('button',{name:'Страница тестов 2',exact:true}).click();
            check('task-test-page-keeps-details',await paged.getByRole('button',{name:'Данные теста 11',exact:true}).getAttribute('aria-expanded')==='true',width);
            await paged.getByRole('button',{name:'Действия',exact:true}).click();
            await paged.getByRole('button',{name:'Отметить просмотренным',exact:true}).click();
            await paged.getByRole('button',{name:'Страница тестов 2',exact:true}).waitFor();
            check('task-test-page-survives-refresh',await paged.getByRole('button',{name:'Страница тестов 2',exact:true}).getAttribute('aria-current')==='page',width);
            await page.screenshot({path:`${output}/task-test-pages-${width}.png`,fullPage:true});

            await visit('student',17,id(17));
            const notebook=page.locator('#execution-workspace-17');
            await notebook.getByRole('region',{name:'Тетрадка Jupyter',exact:true}).waitFor();
            check('task-notebook-cells-and-output',await notebook.locator('.gc-notebook__input').count()===2 && (await notebook.locator('.gc-notebook__outputs').first().innerText()).includes('accuracy: 0.83'),width);
            check('task-notebook-output-is-inert',await page.evaluate(()=>window.notebookExecuted===undefined),width);
            check('task-notebook-width-contained',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),width);
            await notebook.getByRole('button',{name:'Исходный JSON',exact:true}).click();
            await notebook.getByLabel('Исходный JSON тетрадки',{exact:true}).waitFor();
            await notebook.getByRole('button',{name:'Тетрадка',exact:true}).click();
            await page.screenshot({path:`${output}/task-notebook-${width}.png`,fullPage:true});
            await notebook.getByRole('combobox',{name:'Выбрать попытку',exact:true}).selectOption(id(18));
            await notebook.getByText('large-notebook.ipynb',{exact:true}).waitFor();
            check('task-large-notebook-preview',await notebook.locator('.gc-notebook__input').count()===2,width);

            await visit('teacher',13,id(13));
            const ai=page.locator('#execution-workspace-13');
            const warning=ai.getByRole('button',{name:'Антиплагиат и помощь ИИ: есть предупреждения',exact:true});
            await warning.click();
            await ai.getByRole('heading',{name:'Анализ авторства',exact:true}).waitFor();
            const match=ai.locator('.gc-authorship-match a');await match.waitFor();
            check('task-antiplagiarism-canonical-link',(await match.getAttribute('href')).includes('student=3') && (await match.getAttribute('href')).includes('attempt='+id(23)),width);
            check('task-authorship-report-reasons',await ai.getByText('Нужен устный разбор объяснения алгоритма.',{exact:true}).isVisible() && await ai.getByText(/Система сравнивает последовательности элементов кода/).isVisible(),width);
            await page.screenshot({path:`${output}/task-authorship-report-${width}.png`,fullPage:true});
            await page.keyboard.press('Escape');
            check('task-authorship-report-restores-focus',await warning.evaluate(el=>el===document.activeElement),width);
            const feedback=ai.getByRole('textbox',{name:'Комментарий ученику',exact:true});await feedback.fill('Нужно пояснить начальное значение.');
            await ai.getByRole('button',{name:'Следующий',exact:true}).click();
            await ai.getByRole('heading',{name:'Ученик 3',exact:true}).waitFor();
            await ai.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            await ai.getByRole('button',{name:'Предыдущий',exact:true}).click();
            await ai.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            await ai.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            await ai.getByRole('textbox',{name:'Комментарий ученику',exact:true}).waitFor();
            check('task-review-draft-survives-student-switch',(await ai.getByRole('textbox',{name:'Комментарий ученику',exact:true}).inputValue())==='Нужно пояснить начальное значение.',width);
            await page.screenshot({path:`${output}/task-teacher-ai-${width}.png`,fullPage:true});
            const recheckResponse=page.waitForResponse(response=>response.url().endsWith('/'+id(13)+'/recheck') && response.request().method()==='POST');
            await ai.getByRole('button',{name:'Действия',exact:true}).click();
            await ai.getByRole('button',{name:'Перепроверить усиленной моделью',exact:true}).click();
            await page.getByRole('textbox',{name:'Комментарий к перепроверке',exact:true}).fill('Проверьте второй критерий подробнее.');
            await page.getByRole('button',{name:'Запустить перепроверку',exact:true}).click();
            const enhanced=await recheckResponse;
            check('task-enhanced-ai-recheck',enhanced.status()===202 && JSON.parse(enhanced.request().postData()).stronger_ai===true,width);

            await visit('teacher',14,id(14));
            const manual=page.locator('#execution-workspace-14');
            await manual.getByRole('spinbutton',{name:'Баллы, из 20',exact:true}).fill('17');
            await manual.getByRole('textbox',{name:'Комментарий ученику',exact:true}).fill('Объяснение верное, добавьте пример.');
            await manual.getByRole('button',{name:'Сохранить оценку',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            await manual.getByRole('button',{name:'Следующий',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 3',exact:true}).waitFor();
            check('task-grade-then-next',true,width);
            check('task-skip-button-removed',await manual.getByRole('button',{name:'Пропустить работу',exact:true}).count()===0,width);
            await manual.getByRole('button',{name:'Следующий',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            check('task-arrow-navigation',true,width);
            await page.getByRole('tab',{name:'Моё решение',exact:true}).click();
            const own=page.locator('#execution-own-workspace-14');
            await own.getByRole('textbox',{name:'Ответ',exact:true}).fill('Собственная сдача преподавателя.');
            const ownResponse=page.waitForResponse(response=>response.url().endsWith('/tasks/14/code-submissions') && response.request().method()==='POST');
            await own.getByRole('button',{name:'Отправить решение',exact:true}).click();
            const response=await ownResponse;
            check('task-teacher-own-submission',response.status()===201 && (await response.json()).user_id===2,width);
            check('task-document-no-horizontal-overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),width);
        }catch(error){await page.screenshot({path:`${output}/task-failed-${width}.png`,fullPage:true});errors.push({name:'task-workspace',width,message:error.message,url:page.url()});}
        finally{await context.close();}
    }
    return {results,errors};
}
