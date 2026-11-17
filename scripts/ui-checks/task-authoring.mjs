export default async function taskAuthoring(page,config){
    const results=[],errors=[];
    const assert=(value,message)=>{if(!value)throw new Error(message);};
    for(const width of config.widths){
        const context=await page.context().browser().newContext({viewport:{width,height:1000},reducedMotion:'reduce'});
        const p=await context.newPage();p.setDefaultTimeout(5000);p.setDefaultNavigationTimeout(15000);
        p.on('pageerror',error=>errors.push({width,error:error.message}));
        const go=async path=>{const url=new URL(path,config.url);url.searchParams.set('actor','teacher');url.searchParams.set('scenario',width<768?'submissions-phone':'submissions-desktop');await p.goto(url.href);};
        const test=async(name,run)=>{await run();results.push({name,width,passed:true});};
        try{
            await go('/insider/courses/1/tasks/13/edit');
            const editor=p.locator('[data-checker-editor]');
            const type=()=>editor.getByLabel('Тип проверки');
            await type().waitFor();
            await test('authoring-ai-hides-unrelated-fields',async()=>{
                assert(!await p.locator('[data-answer-mode]').isVisible(),'Single configured submission option is still shown');
                assert(!await p.locator('[data-field="answer"]').isVisible(),'Short answer is shown for AI');
                assert(!await p.locator('[data-field="ai_achievement_instruction"]').isVisible(),'Achievement instruction is shown while disabled');
                assert(await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).isVisible(),'AI rubric is missing');
                assert(await editor.getByRole('button',{name:'Добавить тест',exact:true}).count()===0,'Tests are shown for AI');
                await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).fill('Проверьте объяснение и сохраните критерии.');
                await editor.locator('summary').filter({hasText:'Модель и лимит попыток'}).click();
                await editor.getByLabel('Попыток в час',{exact:true}).fill('7');
            });
            await test('authoring-submission-fields-and-values',async()=>{
                await editor.locator('summary').filter({hasText:'Параметры сдачи'}).click();
                await editor.getByLabel(/^Расширения файлов/).fill('py, ipynb');
                await editor.getByLabel('Загрузить файлы',{exact:true}).uncheck();
                assert(!await editor.getByLabel(/^Расширения файлов/).isVisible(),'File extensions shown without upload');
                await editor.getByLabel('Несколько файлов кода',{exact:true}).uncheck();
                assert(!await editor.getByLabel('Точка входа проекта',{exact:false}).isVisible(),'Entrypoint shown for one code file');
                assert(!await editor.getByLabel('Максимум файлов',{exact:true}).isVisible(),'File limits shown for one code file');
                await editor.getByLabel('Загрузить файлы',{exact:true}).check();
                assert(await editor.getByLabel(/^Расширения файлов/).inputValue()==='py, ipynb','Hidden file extensions were lost');
            });
            await test('authoring-checker-switch-preserves-criteria-and-tests',async()=>{
                await type().selectOption('tests');
                assert(await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).count()===0,'AI criteria shown for test checker');
                await editor.getByRole('button',{name:'Добавить тест',exact:true}).click();
                await editor.getByLabel('Входные данные',{exact:true}).fill('1');
                await editor.getByLabel('Ожидаемый вывод',{exact:true}).fill('2');
                const limits=editor.locator('details').filter({has:p.locator('summary:text-is("Лимиты исполнения и точность чисел")')}).first();
                await limits.locator('> summary').click();
                assert(!await editor.getByLabel('Абсолютная погрешность',{exact:true}).isVisible(),'Tolerance shown for token comparison');
                await editor.getByLabel('Сравнение по умолчанию').selectOption('float');
                assert(await editor.getByLabel('Абсолютная погрешность',{exact:true}).isVisible(),'Tolerance missing for float comparison');
                await type().selectOption('gpt');
                assert(await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).inputValue()==='Проверьте объяснение и сохраните критерии.','AI criteria lost after switching');
                assert(!Object.hasOwn(JSON.parse(await p.locator('[data-checker-value]').inputValue()),'test_config'),'Non-test payload includes test configuration');
                await type().selectOption('tests');
                assert(await editor.getByLabel('Ожидаемый вывод',{exact:true}).inputValue()==='2','Test data lost after switching');
                await type().selectOption('manual');
                assert(await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).count()===0,'Manual checker shows AI fields');
                assert(await editor.getByRole('button',{name:'Добавить тест',exact:true}).count()===0,'Manual checker shows test fields');
                await type().selectOption('gpt');
            });
            await test('authoring-file-fields-follow-file-and-checker-type',async()=>{
                const files=editor.locator('details').filter({has:p.locator('summary:text-is("Материалы, шаблоны и приватные файлы")')}).first();
                if(!await files.evaluate(el=>el.open))await files.locator('> summary').click();
                assert(!await files.getByLabel('Имя в проекте',{exact:true}).isVisible(),'File metadata shown without a file');
                await files.getByLabel('Добавить файл',{exact:true}).setInputFiles({name:'criteria.txt',mimeType:'text/plain',buffer:Buffer.from('QA criteria')});
                assert(await files.getByLabel('Передать файл в проверку ИИ',{exact:true}).isVisible(),'AI file option is missing');
                assert(!await files.getByLabel('Шаблон нельзя изменять',{exact:true}).isVisible(),'Read-only option is shown for attachment');
                await files.getByLabel('Назначение').selectOption('starter');
                assert(await files.getByLabel('Шаблон нельзя изменять',{exact:true}).isVisible(),'Read-only option missing for starter');
                await type().selectOption('manual');
                assert(!await files.getByLabel('Передать файл в проверку ИИ',{exact:true}).isVisible(),'AI file option shown for manual checker');
                assert(await files.getByLabel('Добавить файл',{exact:true}).evaluate(el=>el.files.length)===1,'Selected file lost after changing checker');
                await type().selectOption('gpt');
                await files.getByLabel('Добавить файл',{exact:true}).setInputFiles([]);
                await files.locator('> summary').click();
            });
            await test('authoring-ai-form-saves-without-tests',async()=>{
                await p.locator('#name').fill('Проверка редактора '+width);
                await Promise.all([p.waitForURL(url=>url.pathname==='/insider/courses/1/steps/1'),p.getByRole('button',{name:'Сохранить задание',exact:true}).click()]);
                await go('/insider/courses/1/tasks/13/edit');await type().waitFor();
                assert(await p.locator('#name').inputValue()==='Проверка редактора '+width,'Task changes were not saved');
                assert(await editor.getByLabel('Закрытые критерии для ИИ',{exact:true}).inputValue()==='Проверьте объяснение и сохраните критерии.','AI criteria were not saved');
                const settings=JSON.parse(await p.locator('[data-checker-value]').inputValue());
                assert(settings.gpt_rate_limit===7 && settings.check_type==='gpt','AI settings were not saved');
                for(const theme of ['light','dark']){
                    await p.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);
                    assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Editor overflows');
                    await p.screenshot({path:config.output+'/task-authoring-'+theme+'-'+width+'.png',fullPage:true});
                }
            });
            await test('authoring-short-answer-and-achievement-reveal',async()=>{
                await go('/insider/courses/1/steps/1/task/create');
                await p.locator('[name="answer_mode"][value="short"]').check();
                await p.locator('#answer').fill('0');
                await p.locator('[name="answer_mode"][value="manual"]').check();
                assert(!await p.locator('[data-field="answer"]').isVisible(),'Short answer was not hidden');
                await p.locator('[name="answer_mode"][value="short"]').check();
                assert(await p.locator('#answer').inputValue()==='0','Short answer lost on mode change');
                await p.locator('[data-authoring-achievement]').check();
                await p.locator('#ai_achievement_instruction').fill('Аккуратный код');
                await p.locator('[data-authoring-achievement]').uncheck();
                assert(!await p.locator('[data-field="ai_achievement_instruction"]').isVisible(),'Achievement focus was not hidden');
                await p.locator('[data-authoring-achievement]').check();
                assert(await p.locator('#ai_achievement_instruction').inputValue()==='Аккуратный код','Achievement focus lost on toggle');
                const optional=p.locator('[data-authoring-optional]');
                assert(!await optional.evaluate(el=>el.open),'Empty author solution is expanded');
                await optional.locator('> summary').click();
                await optional.locator('.CodeMirror').click();await p.keyboard.insertText('Разбор решения преподавателем');
                await optional.locator('> summary').click();await optional.locator('> summary').click();
                assert((await p.locator('#solution').inputValue()).includes('Разбор решения преподавателем'),'Author solution was lost after collapsing');
            });
        }catch(error){errors.push({width,error:error.message});await p.screenshot({path:config.output+'/task-authoring-failure-'+width+'.png',fullPage:true});}
        finally{await context.close();}
    }
    return {results,errors};
}
