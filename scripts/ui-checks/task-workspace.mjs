export default async function taskWorkspace(controlPage, {url, output, widths = [1440,390], palette = ''}) {
    const results=[],errors=[];
    const id=n=>`10000000-0000-4000-8000-${String(n).padStart(12,'0')}`;
    const check=(name,passed,width,details={})=>{results.push({name,width,passed,...details});if(!passed)throw new Error(name+': '+JSON.stringify(details));};
    for(const width of widths){
        const context=await controlPage.context().browser().newContext({viewport:{width,height:1000},isMobile:width<600,hasTouch:width<600});
        const page=await context.newPage();page.setDefaultTimeout(7000);
        page.on('pageerror',error=>errors.push({name:'task-workspace-js',width,message:error.message}));
        const scenario=width>600?'submissions-desktop':'submissions-phone';
        const showStudentChat=async work=>{
            const sheet=work.locator('xpath=ancestor::div[contains(@class,"gc-task-sheet")][1]');
            const chat=work.locator('.gc-task-chat:visible');
            if (width<1200 && await sheet.locator('[data-task-chat-open]').count() && !await chat.isVisible()) {
                await sheet.getByRole('button',{name:'Чат задачи',exact:true}).click();
            }
        };
        const waitForStudentChatViewport = task => page.waitForFunction(task => {
            const chat = document.querySelector('#execution-workspace-' + task + ' .gc-task-chat:not([hidden])');
            if (!chat) return false;
            const viewport = window.visualViewport;
            const top = viewport?.offsetTop ?? 0;
            const bottom = top + (viewport?.height ?? innerHeight);
            const panel = chat.getBoundingClientRect();
            const input = chat.querySelector('textarea').getBoundingClientRect();
            const heading = document.querySelector('.gc-workbook-header').getBoundingClientRect();
            return Math.abs(bottom - panel.bottom) <= 2 && Math.abs(top - panel.top) <= 2
                && input.height > 0 && input.bottom <= bottom
                && (!matchMedia('(min-width: 1200px)').matches || heading.right <= panel.left + 2);
        }, task);
        const openWork=async(actor,task,attempt=null)=>{
            const chat=page.locator('#execution-workspace-'+task+' .gc-task-chat:visible');
            const work=page.locator('#execution-workspace-'+task);
            if (actor==='student') await showStudentChat(work);
            if(actor==='teacher'){
                await chat.locator('[data-kind="check"][data-attempt="'+attempt+'"]').getByRole('button',{name:'Перепроверить',exact:true}).click();
                await page.getByRole('dialog',{name:'Перепроверить решение'}).getByRole('button',{name:'Оценить вручную'}).click();
            }else if(attempt?.startsWith('solution-')){
                await chat.locator('[data-kind="grade"][data-attempt="'+attempt+'"]').getByRole('button',{name:'Посмотреть проверку'}).click();
            }else{
                await chat.getByRole('button',{name:'Отправить решение',exact:true}).click();
                await work.getByRole('button',{name:'Вернуться к результату',exact:true}).click();
                await work.locator('.gc-execution-result').waitFor();
                const choice = work.locator(`.gc-attempt-step[data-attempt-id="${attempt}"]`);
                if(attempt && await choice.getAttribute('aria-current') !== 'true') {
                    await choice.click();
                    await work.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
                }
            }
        };
        const visit=async(actor,task,attempt=null,{open=true}={})=>{
            const query=new URLSearchParams({scenario,actor,task:String(task)});
            if(palette)query.set('palette',palette);
            if(attempt){query.set('student','1');query.set('attempt',attempt);}
            await page.goto(`${url}/insider/courses/1/steps/1?${query}#task${task}`);
            if (actor === 'student' && task === 12) {
                const work=page.locator('#execution-workspace-12');
                const condition=page.locator('[data-task-condition="12"]');
                const history=page.getByRole('region',{name:'Отправленные решения'});
                const attemptRow=history.locator(`[data-attempt-id="${id(1)}"]`);
                await attemptRow.waitFor();
                const conditionToggle = condition.locator(':scope > summary');
                check('submitted-task-condition-is-visible-with-toggle-only-in-attempt-view',
                    await condition.locator('.gc-task-condition-content').isVisible()
                    && await conditionToggle.count()===(attempt ? 1 : 0), width);
                check('task-history-includes-attempt-result', /10\s*\/\s*20 баллов/.test(await attemptRow.innerText()), width);
                check('task-history-centers-row-contents', await attemptRow.evaluate(row => {
                    const center = ({top, bottom}) => (top + bottom) / 2;
                    const rowCenter = center(row.getBoundingClientRect());
                    return Array.from(row.children, child => child.getBoundingClientRect())
                        .filter(box => box.height > 0)
                        .every(box => Math.abs(center(box) - rowCenter) < 1);
                }), width);
                if (attempt && width < 1200) {
                    await conditionToggle.click();
                    check('task-attempts-stay-visible-after-collapsing-condition',
                        !await condition.locator('.gc-task-condition-content').isVisible() && await attemptRow.isVisible(), width);
                    await conditionToggle.click();
                }
                await attemptRow.click();
                const attemptDetails=page.getByRole('dialog',{name:'Работа с решением'});
                await attemptDetails.locator('.gc-submitted-work').waitFor();
                await attemptDetails.locator('.gc-test-report').waitFor();
                const choices = attemptDetails.getByRole('group',{name:'Мои сдачи',exact:true});
                check('task-history-opens-the-selected-source-and-result', await choices.locator('[aria-current="true"]').getAttribute('data-attempt-id')===id(1), width);
                check('student-result-has-one-circle-per-attempt', await choices.getByRole('button').count()===2
                    && await choices.locator('[data-tone="partial"]').count()>=1 && await choices.getByRole('button').first().getAttribute('aria-label')!==null, width);
                check('student-result-has-no-dropdowns-for-actions-or-attempts', await attemptDetails.locator('.gc-result-actions, [data-attempt-picker]').count()===0, width);
                check('student-result-summary-puts-attempt-date-and-score-in-one-row',
                    await attemptDetails.locator('.gc-result-summary').evaluate(summary => {
                        const fields = ['.gc-attempt-steps', '.gc-attempt-history__date', '.gc-result-score'];
                        const bounds = fields.map(field => summary.querySelector(field).getBoundingClientRect());
                        const center = box => (box.top + box.bottom) / 2;
                        const hidden = '.gc-attempt-history__viewed, .gc-attempt-count, .gc-result-verdict__title, .gc-result-verdict__icon';
                        return bounds.every(box => box.width > 0 && Math.abs(center(box) - center(bounds[0])) < 2)
                            && [...summary.querySelectorAll(hidden)].every(element => !element.getClientRects().length);
                    }), width);
                check('student-result-actions-use-theme-colors',
                    await attemptDetails.getByRole('button',{name:'Исправить решение',exact:true}).evaluate(button=>button.classList.contains('btn-primary'))
                    && await attemptDetails.getByRole('button',{name:'Попросить перепроверить',exact:true}).evaluate(button=>button.classList.contains('btn-outline-primary')), width);
                check('student-result-shows-source-before-tests', await attemptDetails.locator('.gc-execution-result').evaluate(el=>{
                    const source=el.querySelector('.gc-submitted-work'), tests=el.querySelector('.gc-test-report');
                    return !!(source.compareDocumentPosition(tests) & Node.DOCUMENT_POSITION_FOLLOWING)
                        && !Array.from(el.childNodes).some(child=>child.nodeType===Node.TEXT_NODE && child.textContent.trim()==='null');
                }), width);
                await attemptDetails.getByRole('button',{name:'Закрыть'}).click();
                check('task-history-keeps-focus-on-the-selected-row', await attemptRow.evaluate(el=>el===document.activeElement), width);
                await page.getByRole('button',{name:'Сдать решение',exact:true}).click();
                await page.locator('dialog[open] .gc-execution-composer').waitFor();
                await page.keyboard.press('Escape');
                check('start-solution-below-statement',true,width);
                if (width >= 1200) await page.setViewportSize({width:1280,height:580});
                const opener=page.getByRole('button',{name:'Чат задачи',exact:true});
                const chat=work.locator('.gc-task-chat');
                if (width<1200) {
                    check('mobile-tutor-opens-from-task', !await chat.isVisible(), width);
                    await showStudentChat(work);
                    const draft=chat.getByRole('textbox');
                    await draft.fill('Черновик вопроса');
                    await chat.getByRole('button',{name:'К задаче',exact:true}).click();
                    check('mobile-tutor-returns-to-task', !await chat.isVisible() && await opener.evaluate(el=>el===document.activeElement), width);
                    await opener.click();
                    check('mobile-tutor-preserves-question-draft', await draft.inputValue()==='Черновик вопроса', width);
                    await page.keyboard.press('Escape');
                    await chat.waitFor({state:'hidden'});
                    await opener.click();
                }
                await waitForStudentChatViewport(12);
                check('chat-fills-viewport-and-keeps-composer-visible', true, width);
                await page.screenshot({path:`${output}/tutor-panel-${width}.png`});
                const ask = chat.getByRole('button', {name:'Спросить преподавателя',exact:true});
                const draft = chat.getByRole('textbox', {name:'Сообщение в чат задачи'});
                const note = 'Можно уточнить условие?';
                await draft.fill(note);
                await ask.click();
                await ask.click();
                check('teacher-shortcut-preserves-draft-and-focus', await draft.inputValue() === '@преподаватель '+note
                    && await draft.evaluate(el=>el===document.activeElement), width);
                await chat.getByRole('button', {name:'Отправить сообщение',exact:true}).click();
                await chat.getByRole('log').getByText('@преподаватель '+note,{exact:true}).waitFor();
                check('teacher-mention-skips-typing-indicator',await chat.locator('.gc-chat-typing').count()===0,width);
                const question = 'Как вывести <header> через `print`?\n\n```python\nprint("<header>")\n```';
                await chat.getByRole('textbox', {name:'Сообщение в чат задачи'}).fill(question);
                await chat.getByRole('button', {name:'Отправить сообщение', exact:true}).click();
                const message = chat.locator('[data-kind="message"]').filter({hasText:'Как вывести <header>'}).last();
                await message.locator('pre code').waitFor();
                const typing = chat.getByRole('log').locator('.gc-chat-typing:last-child');
                await typing.waitFor();
                const placement = await typing.evaluate(el => ({
                    followsQuestion: !!(el.parentElement.querySelector('[data-kind="message"]').compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING),
                    bottom: el.getBoundingClientRect().bottom,
                    composerTop: el.closest('.gc-task-chat').querySelector('form').getBoundingClientRect().top,
                }));
                check('tutor-typing-follows-question-in-log',placement.followsQuestion && placement.bottom <= placement.composerTop+1,width,placement);
                check('chat-renders-inline-and-block-code-and-keeps-literal-html',
                    await message.locator('p code').innerText() === 'print'
                    && (await message.locator('pre code').innerText()).trim() === 'print("<header>")'
                    && await message.locator('header').count() === 0, width);
                const chatUrl = '**/tasks/12/chat/1';
                await page.route(chatUrl, async route=>{
                    if (route.request().method()==='POST' && route.request().postDataJSON().attempt==='solution-41') {
                        return route.fulfill({json:{saved:true}});
                    }
                    if (route.request().method() !== 'GET') return route.continue();
                    const response = await route.fetch();
                    const data = await response.json();
                    const text = ['Начало длинного ответа.', ...Array(24).fill('Сравни ожидаемый результат с результатом на одном примере.')].join('\n\n');
                    const at = data.events.map(event=>event.at).sort().at(-1);
                    data.events.push({id:'tutor-ui-reply', type:'tutor', at, text, tutor:{
                        recheck:{claim:'Проверка границ',evidence:'index',focus:'Проверить первый элемент.',
                            location:{file:null,line:1},status:'unavailable'},
                        choices:[{at:'2026-11-11 10:00',legacy_solution:41}],choice_question:'Почему 26/35?'
                    }});
                    await route.fulfill({response,json:{...data,awaiting_tutor:false}});
                });
                await draft.fill('Как проверить результат на примере?');
                await chat.getByRole('button',{name:'Отправить сообщение',exact:true}).click();
                const answer=chat.locator('[data-event="tutor-ui-reply"]');
                await answer.waitFor();
                const replyBounds=await answer.evaluate(el=>{
                    const log=el.closest('.gc-chat-log').getBoundingClientRect();
                    const box=el.getBoundingClientRect();
                    return {replyHeight:box.height,logHeight:log.height,offset:box.top-log.top};
                });
                check('tutor-long-reply-opens-at-its-start',replyBounds.replyHeight>replyBounds.logHeight && Math.abs(replyBounds.offset)<=2,width,replyBounds);
                await page.screenshot({path:`${output}/tutor-long-reply-${width}.png`});
                check('tutor-explains-unavailable-recheck',await answer.getByText('Автоматическая перепроверка недоступна.',{exact:false}).isVisible(),width);
                await draft.fill('Мой незаконченный вопрос');
                const clickPost = page.waitForRequest(req=>req.method()==='POST' && req.url().endsWith('/tasks/12/chat/1'));
                await answer.getByRole('button',{name:'Проверка 2026-11-11 10:00 · № 41'}).click();
                const selected = (await clickPost).postDataJSON();
                check('choosing-score-binds-attempt-without-erasing-draft',selected.attempt==='solution-41'
                    && selected.body==='Почему 26/35?' && await draft.inputValue()==='Мой незаконченный вопрос',width);
                await page.unroute(chatUrl);
                if (width >= 1200) await page.setViewportSize({width,height:1000});
            }
            if(open)await openWork(actor,task,attempt);
        };
        const writeComment=async(root,text)=>{
            await root.locator('.gc-comment-editor .CodeMirror').waitFor();
            const field=root.getByRole('textbox',{name:'Комментарий ученику',exact:true});
            await field.click();await field.press('ControlOrMeta+A');await page.keyboard.insertText(text);
            await page.waitForFunction(({id,text})=>document.querySelector('#'+id+' .gc-comment-editor .CodeMirror')?.CodeMirror.getValue()===text,{id:await root.getAttribute('id'),text});
        };
        try{
            await page.setViewportSize({width,height:1000});
            if (width >= 1200) {
                await visit('teacher',13,null,{open:false});
                await context.grantPermissions(['clipboard-read','clipboard-write'],{origin:url});
                const sample = page.locator('#task13 .gc-task-condition-content');
                for (const [label,expected] of [['ввод примера 1','2\n4 7'],['вывод примера 1','7'],['ввод примера 2','1\n0']]) {
                    await sample.getByRole('button',{name:'Скопировать '+label}).click();
                    check('copy-'+label,await page.evaluate(()=>navigator.clipboard.readText())===expected,width);
                }
                const list = page.locator('#student-solutions-13 [data-execution-student-list]');
                const geometry = element => element.evaluate(el => ({
                    bottom: el.getBoundingClientRect().bottom,
                    pageY: window.scrollY,
                }));
                await list.waitFor();
                const title = page.locator('#task13 > .gc-chat-sheet > .gc-task-title-row');
                const ownChoice = list.getByRole('link',{name:'Мои решения',exact:true});
                check('teacher-right-pane-spans-the-viewport', await list.evaluate(roster => {
                    const pane = roster.closest('.gc-task-layout').getBoundingClientRect();
                    const heading = document.querySelector('.gc-workbook-header').getBoundingClientRect();
                    return Math.abs(pane.top) <= 1 && Math.abs(pane.bottom - innerHeight) <= 1
                        && Math.abs(pane.right - document.documentElement.clientWidth) <= 1 && heading.right <= pane.left + 1;
                }), width);
                check('teacher-chooses-own-or-student-work-first',
                    await ownChoice.isVisible() && await list.locator('[data-execution-student]').first().isVisible()
                    && await page.locator('#task13 .gc-task-chat:visible').count()===0
                    && await title.getByRole('button',{name:/Действия с задачей/}).isVisible(),width);
                check('teacher-uses-the-student-task-header-and-condition-styles',await title.evaluate(heading =>
                    heading.querySelector('.gc-task-title-facts .gc-task-reward') &&
                    heading.nextElementSibling.matches('section.gc-task-condition') &&
                    getComputedStyle(heading).borderBottomWidth==='0px'),width);
                check('no-step-footer-in-task', !await page.locator('.step-page-nav').isVisible(),width);
                const original = await geometry(list);
                await list.locator('[data-student-id="1"]').click();
                const chat = page.locator('#execution-workspace-13 .gc-task-chat:visible');
                await chat.locator('.gc-chat-file').first().waitFor();
                check('teacher-feedback-has-no-mention-shortcut',await chat.getByRole('button',{name:'Спросить преподавателя'}).count()===0,width);
                const draft = chat.getByRole('textbox',{name:'Сообщение в чат задачи'});
                await draft.fill('Черновик комментария');
                await chat.getByRole('button',{name:'Список решений',exact:true}).click();
                const submissions = chat.getByRole('region',{name:'Список решений',exact:true});
                await submissions.locator('[data-attempt-id]').first().waitFor();
                check('teacher-solutions-view-shows-submissions-without-the-composer',await draft.isHidden(),width);
                await chat.getByRole('button',{name:'Чат',exact:true}).click();
                check('teacher-chat-switch-keeps-the-draft',await draft.inputValue()==='Черновик комментария',width);
                const selected = await geometry(chat);
                await chat.getByRole('button',{name:'К списку учеников'}).click();
                await list.waitFor();
                const restored = await geometry(list);
                check('teacher-roster-and-chat-keep-the-page-still',
                    [selected,restored].every(view => Math.abs(view.pageY-original.pageY)<=1
                        && Math.abs(view.bottom-original.bottom)<=2),width,{original,selected,restored});
                await ownChoice.click();
                const ownChat = page.locator('#execution-own-workspace-13 .gc-task-chat');
                await ownChat.locator('.gc-chat-head').waitFor();
                check('teacher-own-chat-has-a-direct-link',await list.isHidden() &&
                    new URL(page.url()).searchParams.get('student')==='2',width);
                await page.goBack();
                await list.waitFor();
                await page.goForward();
                await ownChat.locator('.gc-chat-head').waitFor();
                await ownChat.getByRole('button',{name:'К списку учеников'}).click();
                await list.waitFor();
                check('teacher-own-chat-returns-to-the-choice-list',
                    await ownChat.isHidden() && await ownChoice.evaluate(link => document.activeElement===link),width);
            } else {
                await visit('teacher',13,null,{open:false});
                const list = page.locator('#student-solutions-13 [data-execution-student-list]');
                const panel = page.getByRole('dialog',{name:'Чат задачи',exact:true});
                const student = list.locator('[data-execution-student][data-student-id="1"]');
                check('teacher-mobile-roster-sits-below-the-condition',await list.isVisible() && await panel.isHidden() &&
                    await list.evaluate(el => {
                        const condition = el.closest('.gc-task-sheet').querySelector('[data-task-condition]');
                        return el.getBoundingClientRect().top >= condition.getBoundingClientRect().bottom;
                    }),width);
                await student.click();
                await panel.getByRole('button',{name:'Чат',exact:true}).waitFor();
                check('teacher-mobile-selection-opens-chat-directly',
                    await panel.getByRole('button',{name:'Чат',exact:true}).getAttribute('aria-pressed')==='true',width);
                await panel.getByRole('button',{name:'Список решений',exact:true}).click();
                await panel.getByRole('region',{name:'Список решений',exact:true}).locator('[data-attempt-id]').first().waitFor();
                await panel.getByRole('button',{name:'К списку учеников',exact:true}).click();
                check('teacher-mobile-chat-restores-focus-to-the-selected-student',await panel.isHidden() &&
                    await student.evaluate(el=>el===document.activeElement),width);
                const ownChoice = list.getByRole('link',{name:'Мои решения',exact:true});
                await ownChoice.click();
                await panel.locator('#execution-own-workspace-13 .gc-chat-head').waitFor();
                await page.keyboard.press('Escape');
                await panel.waitFor({state:'hidden'});
                check('teacher-mobile-escape-returns-to-own-solutions-choice',
                    await ownChoice.evaluate(el=>el===document.activeElement) && !new URL(page.url()).searchParams.has('student'),width);
            }
            await page.goto(`${url}/insider/courses/1/steps/1?actor=student&scenario=submissions&task=12#task12`);
            const plainWork = page.locator('#execution-workspace-12');
            const plainChat = plainWork.locator('.gc-task-chat');
            const plainOpener = page.getByRole('button',{name:'Чат задачи',exact:true});
            if (width < 1200) {
                await plainOpener.waitFor();
                check('student-mobile-chat-without-tutor-opens-from-the-task',await plainChat.isHidden(),width);
                await plainOpener.click();
            }
            await waitForStudentChatViewport(12);
            check('student-chat-without-tutor-fills-the-viewport',
                await plainWork.getAttribute('data-chat-tutor') === null &&
                await plainChat.getByRole('button',{name:'Спросить преподавателя',exact:true}).count() === 0,width);
            if (width < 1200) {
                await plainChat.getByRole('button',{name:'К задаче',exact:true}).click();
                check('student-mobile-chat-without-tutor-returns-to-the-task',
                    await plainChat.isHidden() && await plainOpener.evaluate(el=>el===document.activeElement),width);
            }
            const plainHistory = page.getByRole('region',{name:'Отправленные решения'});
            await plainHistory.locator(`[data-attempt-id="${id(1)}"]`).first().waitFor();
            const tabState = task => page.locator('#tasks-tab'+task+' [data-task-state]');
            check('late-full-mark-closes-the-task', await tabState(11).getAttribute('title') === 'Решение принято',width);
            check('completed-native-runs-have-a-partial-result',
                await tabState(12).getAttribute('title') === 'Требует доработки' &&
                await page.locator('#task12 [data-task-outcome] small').innerText() === '10 / 20',width);
            if (width === 1440) {
                await page.setViewportSize({width:1024,height:768});
                await page.locator('[data-task-condition="12"] .gc-task-condition-content').evaluate(el=>el.style.minHeight='2200px');
                const refreshed = page.waitForResponse(res=>res.request().method()==='GET' && res.url().endsWith('/tasks/12/chat/1'));
                await plainOpener.click();
                await refreshed;
                check('tablet-opens-chat-above-long-condition', await plainChat.isVisible() &&
                    await plainWork.evaluate(el=>el.getBoundingClientRect().top > innerHeight),1024);
                const chatUrl = '**/tasks/12/chat/1';
                await page.route(chatUrl, async route=>{
                    const response = await route.fetch();
                    const data = await response.json();
                    data.events.push({id:'teacher-offscreen-reply',type:'message',author:2,name:'Преподаватель',
                        at:'2026-11-11T12:00:00Z',text:'Посмотрите второй пример.'});
                    await route.fulfill({response,json:data});
                });
                await plainChat.locator('[data-event="teacher-offscreen-reply"]').waitFor({timeout:16000});
                check('open-chat-receives-reply-while-workspace-is-offscreen', true,1024);
                await page.unroute(chatUrl);
                await page.setViewportSize({width,height:1000});
            }
            await page.goto(url+'/insider/courses/1/steps/1#task13');
            await page.locator('#task13 [data-task-attempt-history] [data-attempt-id="'+id(33)+'"]').first().click();
            const nextTask = page.getByRole('dialog',{name:'Работа с решением'}).getByRole('link',{name:'Следующая задача'});
            await nextTask.click();
            await page.locator('#task14.active.show').waitFor();
            check('full-result-opens-next-task-and-closes-dialog',new URL(page.url()).hash === '#task14' &&
                await page.locator('dialog[open]').count() === 0 && await page.locator('#task14 h2').first().evaluate(el=>el===document.activeElement),width);
            await visit('student',12,id(1));
            const root=page.locator('#execution-workspace-12');
            const worksheet=await root.evaluate(el=>{
                const sheet=el.closest('.gc-task-sheet'),condition=sheet.querySelector('[data-task-condition]');
                const left=condition.getBoundingClientRect(),right=el.querySelector('.gc-task-chat').getBoundingClientRect();
                const desktop=matchMedia('(min-width: 1200px)').matches;
                return {layout:left.height>0 && (desktop ? left.right<=right.left : left.bottom<=el.getBoundingClientRect().top),

                    reward:getComputedStyle(sheet.querySelector('.gc-task-title-row .gc-task-reward i')).color,
                    paper:getComputedStyle(sheet).backgroundColor,ground:getComputedStyle(sheet.closest('.step-main')).backgroundColor};
            });
            check('task-worksheet-layout-and-two-colors',worksheet.layout && worksheet.paper===worksheet.ground && (palette || worksheet.reward==='rgb(255, 161, 23)'),width,worksheet);
            await root.getByRole('button',{name:'Исправить решение',exact:true}).click();
            const code=root.locator('.cm-content');
            await code.waitFor();
            check('revision-opens-the-selected-source', (await code.innerText()).includes('return max(values)'),width);
            await code.fill('def largest(values):\n    return max(values)\n# saved draft');
            await root.locator('.gc-draft-state').filter({hasText:'Черновик сохранён'}).waitFor();
            const draft=await (await page.request.get(url+'/insider/courses/1/tasks/12/code-draft?from_submission='+id(1))).json();
            check('task-draft-autosave',draft.files[0].content.includes('# saved draft'),width);
            const mainDraft=await (await page.request.get(url+'/insider/courses/1/tasks/12/code-draft')).json();
            check('correction-keeps-the-main-draft', !mainDraft.files.some(file => file.content?.includes('# saved draft')),width);
            await root.getByRole('button',{name:'Вернуться к результату',exact:true}).click();
            await root.locator('.gc-execution-result').waitFor();
            await root.getByRole('button',{name:'Исправить решение',exact:true}).click();
            check('task-draft-survives-history',(await code.innerText()).includes('# saved draft'),width);
            await root.getByRole('button',{name:'Вернуться к результату',exact:true}).click();
            await page.getByRole('dialog',{name:'Работа с решением'}).getByRole('button',{name:'Закрыть'}).click();
            const file=root.locator('[data-kind="file"][data-attempt="'+id(1)+'"] .gc-chat-file');
            await file.click();
            const source=page.getByRole('dialog',{name:'Отправленное решение'});
            await source.locator('.gc-submitted-work').waitFor();
            check('source-preview-opens-without-extra-details',await source.getByRole('button',{name:'Подробнее о посылке'}).count()===0,width);
            await source.getByRole('button',{name:'Закрыть'}).click();
            const card=root.locator('[data-kind="check"][data-attempt="'+id(1)+'"]');
            await card.getByRole('button',{name:'Подробнее',exact:true}).click();
            const report=page.getByRole('dialog',{name:'Журнал тестирования'});
            const purchase=report.getByRole('row').filter({hasText:'№ 2'}).getByRole('button',{name:'Открыть за 5 GC'});
            page.once('dialog',dialog=>dialog.dismiss());await purchase.click();
            check('cancel-keeps-hidden-test-locked',await purchase.isVisible(),width);
            page.once('dialog',dialog=>dialog.accept());await purchase.click();
            await report.locator('.gc-test-detail:not([hidden]) .gc-test-data').filter({hasText:'-8 -2 -5'}).waitFor();
            check('task-purchased-test-stays-open',await report.getByRole('button',{name:'Данные теста 2',exact:true}).getAttribute('aria-expanded')==='true',width);
            await report.getByRole('button',{name:'Закрыть'}).click();
            await card.getByRole('button',{name:'Попросить перепроверить',exact:true}).waitFor();
            await openWork('student',12,id(1));
            const workDialog = page.getByRole('dialog',{name:'Работа с решением'});
            const resultActions = workDialog.getByRole('group',{name:'Действия с решением'});
            const reviewButton = resultActions.getByRole('button',{name:'Попросить перепроверить',exact:true});
            await reviewButton.click();
            const requestDialog = page.getByRole('dialog',{name:'Запрос на перепроверку'});
            await requestDialog.getByRole('textbox',{name:'Что преподавателю нужно проверить?',exact:true}).fill('Проверьте, пожалуйста, второй тест и моё объяснение.');
            const sent=page.waitForResponse(res=>res.url().endsWith('/'+id(1)+'/review-request') && res.request().method()==='POST');
            await requestDialog.getByRole('button',{name:'Отправить запрос',exact:true}).click();
            await workDialog.getByText('Запрос на перепроверку отправлен',{exact:true}).waitFor();
            check('task-student-recheck-request-from-result',(await sent).ok() && await reviewButton.count()===0,width);
            await workDialog.getByRole('button',{name:'Закрыть'}).click();
            await card.getByText('Запрос на перепроверку отправлен',{exact:true}).waitFor();
            await openWork('student',12,id(1));
            await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            const picker=root.getByRole('group',{name:'Мои сдачи',exact:true});
            const otherAttempt = picker.locator(`[data-attempt-id="${id(31)}"]`);
            const firstAttempt = picker.locator(`[data-attempt-id="${id(1)}"]`);
            const before=(await picker.boundingBox()).y;
            await page.route('**/code-submissions/*?test_data=metadata',async route=>{await new Promise(resolve=>setTimeout(resolve,300));await route.continue().catch(()=>{});});
            await otherAttempt.click();await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor();
            const during=(await picker.boundingBox()).y;
            await firstAttempt.press('Enter');await root.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            check('task-attempt-no-scroll-jump',Math.abs(before-during)<1,width,{before,during});
            check('task-latest-selection-wins',await firstAttempt.getAttribute('aria-current')==='true',width);
            check('task-attempt-keyboard-focus-is-preserved',await firstAttempt.evaluate(el=>el===document.activeElement),width);
            await page.unroute('**/code-submissions/*?test_data=metadata');
            await page.screenshot({path:`${output}/task-student-${width}.png`,fullPage:true});

            await visit('student',15,'solution-115',{open:false});
            await page.getByRole('region',{name:'Отправленные решения'}).locator('[data-attempt-id="solution-115"]').click();
            const archive=page.locator('#execution-workspace-15');
            await archive.getByRole('tab',{name:'src/helper.py',exact:true}).click();
            await archive.locator('pre').filter({hasText:'def answer()'}).waitFor();
            check('task-legacy-zip-source-and-xp',await archive.locator('.gc-attempt-step[aria-current="true"]').getAttribute('data-attempt-id')==='solution-115' && (await archive.locator('.gc-result-score > strong').innerText())==='6',width);
            check('task-result-omits-the-historical-condition-link',
                await archive.locator('a[href*="/insider/material-history/"], a[href*="/insider/material-code-history/"]').count()===0,width);
            check('task-no-coin-button-without-an-action',await archive.locator('.gc-award .btn-coin').count()===0,width);

            await visit('student',13,id(13));
            const award=page.locator('#execution-workspace-13 .gc-award');
            const booster=award.getByRole('button',{name:/^Бустер · \d+ GC$/});
            await booster.waitFor();
            check('task-coin-action-is-a-direct-button',!(await award.innerText()).includes('возможности') && await booster.evaluate(el=>el.classList.contains('btn-coin')),width);
            for(const theme of ['light','dark']) {
                await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);
                const colors=await booster.evaluate(el=>{const probe=document.createElement('span');probe.style.color='var(--gc-coin)';document.body.append(probe);const value={actual:getComputedStyle(el).color,expected:getComputedStyle(probe).color};probe.remove();return value;});
                await page.waitForFunction(expected=>getComputedStyle(document.querySelector('#execution-workspace-13 .gc-award__action')).color===expected,colors.expected);
                colors.actual=await booster.evaluate(el=>getComputedStyle(el).color);
                check('task-coin-action-is-purple',colors.actual===colors.expected,width,{theme,...colors});
            }
            await booster.click();
            const boostDialog=page.getByRole('dialog',{name:'Применить бустер',exact:true});
            await boostDialog.waitFor();
            check('task-coin-action-keeps-price-and-confirmation',await boostDialog.getByText(/К списанию:|Подарок питомца/).count()===1 && await boostDialog.locator('button.btn-coin').count()===1,width);
            await boostDialog.getByRole('button',{name:'Отмена',exact:true}).click();
            check('task-coin-action-cancel-restores-focus',await booster.evaluate(el=>document.activeElement===el),width);
            await page.screenshot({path:`${output}/task-purple-coin-action-${width}.png`,fullPage:true});
            const unavailablePattern=`**/code-submissions/${id(13)}?test_data=metadata`;
            await page.route(unavailablePattern,async route=>{const response=await route.fetch();const value=await response.json();if(value.award_breakdown?.actions?.booster)value.award_breakdown.actions.booster.available=false;await route.fulfill({response,json:value});});
            await page.reload();await openWork('student',13);
            await page.locator('#execution-workspace-13 .gc-result-score > strong').waitFor();
            check('task-unavailable-coin-action-is-hidden',await page.locator('#execution-workspace-13 .gc-award button.btn-coin').count()===0,width);
            await page.unroute(unavailablePattern);

            await visit('student',14);
            const uploadRoot=page.locator('#execution-workspace-14');
            await uploadRoot.getByRole('button',{name:'Открыть основной черновик',exact:true}).click();
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
            check('teacher-review-menu-omits-viewed-action-on-an-ordinary-attempt',
                await paged.getByText(/^(Обновить результат|Отметить (не)?просмотренным)$/).count()===0,width);
            await paged.getByRole('group',{name:'Сдачи ученика',exact:true}).getByRole('button').first().click();
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
            await notebook.locator(`.gc-attempt-step[data-attempt-id="${id(18)}"]`).click();
            await notebook.getByText('large-notebook.ipynb',{exact:true}).waitFor();
            const notebookPreview = notebook.getByRole('region',{name:'large-notebook.ipynb',exact:true})
                .getByRole('region',{name:'Тетрадка Jupyter',exact:true});
            await notebookPreview.waitFor();
            await notebookPreview.locator('.gc-notebook__input').nth(1).waitFor({state:'attached'});
            check('task-large-notebook-preview',await notebookPreview.locator('.gc-notebook__input').count()===2,width);

            await visit('teacher',13,id(13));
            const ai=page.locator('#execution-workspace-13');
            const reviewHeader=ai.locator('.gc-result-summary');
            const steps=reviewHeader.getByRole('group',{name:'Сдачи ученика',exact:true});
            check('teacher-review-header-uses-circles-date-and-score',
                await steps.getByRole('button').count()>0 && await reviewHeader.locator('.gc-attempt-history__date').isVisible()
                && await reviewHeader.locator('.gc-result-score').isVisible()
                && await reviewHeader.locator('.gc-result-verdict__title').isHidden(),width);
            check('teacher-review-menu-only-offers-viewed-action-for-review-requests',
                await ai.getByText(/^Отметить (не)?просмотренным$/).count()===1
                && await ai.getByText('Обновить результат',{exact:true}).count()===0,width);
            const warning=ai.getByRole('button',{name:'Антиплагиат и помощь ИИ: есть предупреждения',exact:true});
            await warning.click();
            await ai.getByRole('heading',{name:'Анализ авторства',exact:true}).waitFor();
            const match=ai.locator('.gc-authorship-match a');await match.waitFor();
            check('task-antiplagiarism-canonical-link',(await match.getAttribute('href')).includes('student=3') && (await match.getAttribute('href')).includes('attempt='+id(23)),width);
            check('task-authorship-report-reasons',await ai.getByText('Нужен устный разбор объяснения алгоритма.',{exact:true}).isVisible() && await ai.getByText(/Система сравнивает последовательности элементов кода/).isVisible(),width);
            await page.screenshot({path:`${output}/task-authorship-report-${width}.png`,fullPage:true});
            await page.keyboard.press('Escape');
            check('task-authorship-report-restores-focus',await warning.evaluate(el=>el===document.activeElement),width);
            await writeComment(ai,'Нужно пояснить начальное значение.');
            await ai.getByRole('button',{name:'Следующая работа',exact:true}).click();
            await ai.getByRole('heading',{name:'Ученик 3',exact:true}).waitFor();
            await ai.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            await ai.getByRole('button',{name:'Предыдущая работа',exact:true}).click();
            await ai.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            await ai.locator('.gc-submission-stage[aria-busy="true"]').waitFor({state:'hidden'});
            await ai.getByRole('textbox',{name:'Комментарий ученику',exact:true}).waitFor();
            check('task-review-draft-survives-student-switch',(await ai.getByRole('textbox',{name:'Комментарий ученику',exact:true}).evaluate(el=>el.closest('.CodeMirror')?.CodeMirror.getValue() ?? el.value))==='Нужно пояснить начальное значение.',width);
            await page.screenshot({path:`${output}/task-teacher-ai-${width}.png`,fullPage:true});
            const recheckResponse=page.waitForResponse(response=>response.url().endsWith('/'+id(13)+'/recheck') && response.request().method()==='POST');
            await page.getByRole('dialog',{name:'Работа с решением'}).getByRole('button',{name:'Закрыть'}).click();
            const result=ai.locator('[data-kind="check"][data-attempt="'+id(13)+'"]');
            await result.getByRole('button',{name:'Перепроверить',exact:true}).click();
            await page.getByRole('combobox',{name:'Способ проверки'}).selectOption('strong');
            await page.getByRole('textbox',{name:'Комментарий к перепроверке',exact:true}).fill('Проверьте второй критерий подробнее.');
            if(width===1440)await page.getByRole('dialog',{name:'Перепроверить решение'}).screenshot({path:`${output}/chat-recheck-method.png`});
            await page.getByRole('button',{name:'Запустить перепроверку',exact:true}).click();
            const enhanced=await recheckResponse;
            check('task-enhanced-ai-recheck',enhanced.status()===202 && JSON.parse(enhanced.request().postData()).stronger_ai===true,width);
            await result.getByRole('button',{name:'Перепроверить',exact:true}).waitFor({state:'hidden'});

            await visit('teacher',14,id(14),{open:false});
            const manual=page.locator('#execution-workspace-14');
            await manual.locator('[data-attempt="'+id(14)+'"]').getByRole('button',{name:'Оценить решение',exact:true}).click();
            await manual.getByRole('spinbutton',{name:'Баллы, из 20',exact:true}).fill('17');
            await writeComment(manual,'Объяснение верное, добавьте пример.');
            await manual.getByRole('button',{name:'Сохранить оценку',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            await manual.getByRole('button',{name:'Следующая работа',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 3',exact:true}).waitFor();
            check('task-grade-then-next',true,width);
            check('task-skip-button-removed',await manual.getByRole('button',{name:'Пропустить работу',exact:true}).count()===0,width);
            await manual.getByRole('button',{name:'Следующая работа',exact:true}).click();
            await manual.getByRole('heading',{name:'Ученик 1',exact:true}).waitFor();
            check('task-arrow-navigation',true,width);
            await page.getByRole('dialog',{name:'Работа с решением'}).getByRole('button',{name:'Закрыть',exact:true}).click();
            await manual.getByRole('button',{name:'К списку учеников',exact:true}).click();
            await page.locator('#task14 [data-execution-student-list]').getByRole('link',{name:'Мои решения',exact:true}).click();
            const own=page.locator('#execution-own-workspace-14');
            await own.getByRole('button',{name:'Отправить решение',exact:true}).click();
            const editor=page.locator('dialog.gc-work-dialog[data-view="compose"][open] .gc-execution-composer');
            await editor.getByRole('textbox',{name:'Ответ',exact:true}).fill('Собственная сдача преподавателя.');
            const ownResponse=page.waitForResponse(response=>response.url().endsWith('/tasks/14/code-submissions') && response.request().method()==='POST');
            await editor.getByRole('button',{name:'Отправить решение',exact:true}).click();
            const response=await ownResponse;
            check('task-teacher-own-submission',response.status()===201 && (await response.json()).user_id===2,width);
            await visit('teacher',17,id(18),{open:false});
            const selected=page.locator('#execution-workspace-17');
            await selected.locator('[data-kind="check"][data-attempt="'+id(17)+'"]').getByRole('button',{name:'Перепроверить',exact:true}).click();
            await page.getByRole('dialog',{name:'Перепроверить решение'}).getByRole('button',{name:'Оценить вручную'}).click();
            await selected.getByRole('spinbutton',{name:'Баллы, из 20',exact:true}).fill('19');
            await writeComment(selected,'Проверил исходный блокнот.');
            const saved=page.waitForResponse(r=>r.url().endsWith('/'+id(17)+'/grade') && r.request().method()==='PUT');
            await selected.getByRole('button',{name:'Сохранить оценку',exact:true}).click();
            check('chat-grades-earlier-attempt', (await saved).ok(), width);
            await page.getByRole('dialog',{name:'Работа с решением'}).getByRole('button',{name:'Закрыть'}).click();
            await selected.locator('[data-kind="check"][data-attempt="'+id(17)+'"]').getByText('Проверил исходный блокнот.',{exact:true}).waitFor();
            if (width >= 1200) {
                check('teacher-composer-stays-in-view-after-grading', await selected.locator('.gc-task-chat').evaluate(chat => {
                    const input = chat.querySelector('textarea').getBoundingClientRect();
                    return input.height > 0 && input.bottom <= innerHeight && Math.abs(chat.getBoundingClientRect().bottom - innerHeight) <= 1;
                }), width);
            }
            if(width===1440)await selected.locator('.gc-task-chat').screenshot({path:`${output}/chat-manual-recheck.png`});
            const other=await (await page.request.get(url+'/insider/code-submissions/'+id(18)+'?test_data=metadata',{headers:{Accept:'application/json'}})).json();
            check('chat-keeps-other-attempt-grade',other.points===16,width);
            await page.goto(`${url}/insider/courses/1/steps/1?scenario=${scenario}&actor=student#task18`);
            const quiz=page.locator('#task18');
            await quiz.getByRole('radio',{name:'4',exact:true}).check();
            await quiz.getByRole('button',{name:'Отправить',exact:true}).click();
            await quiz.getByText('Неверный ответ.',{exact:true}).waitFor();
            check('quiz-shows-answer-without-chat',await quiz.locator('[data-quiz-given]').innerText()==='4' &&
                !await quiz.getByRole('radio').count() && !await quiz.locator('.gc-task-chat,[data-task-compose]').count(),width);
            await quiz.getByText('Ответить ещё раз',{exact:true}).click();
            await quiz.getByRole('radio',{name:'8',exact:true}).check();
            await quiz.getByRole('button',{name:'Отправить',exact:true}).click();
            await quiz.locator('[data-quiz-exhausted]').waitFor();
            await page.reload();
            check('quiz-saves-last-answer-and-respects-limit',await quiz.locator('[data-quiz-given]').innerText()==='8' &&
                await quiz.locator('[data-quiz-exhausted]').isVisible() && !await quiz.getByRole('button',{name:'Отправить'}).count(),width);
            check('task-document-no-horizontal-overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),width);
        }catch(error){await page.screenshot({path:`${output}/task-failed-${width}.png`,fullPage:true});errors.push({name:'task-workspace',width,message:error.message,url:page.url()});}
        finally{await context.close();}
    }
    return {results,errors};
}
