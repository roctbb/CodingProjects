import {node, button, iconButton, request, phases} from './ui';
import {watchTaskChat} from './chat-live';
import {showCriteria, criterionTable, testSummary} from './chat-results';
import {markdownContent} from './feedback';
import autosize from 'autosize';
import {executionPhase} from './composer-options';
import {subscribe} from './progress';

const clock = new Intl.DateTimeFormat('ru-RU',{hour:'2-digit',minute:'2-digit'});
const day = new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric'});
const pointsPlural = new Intl.PluralRules('ru-RU');
const dateOf = item => new Date(item.at);
const tutorPortrait = '/images/rostislop.webp';
const tutorName = 'Ростислоп Нейрославович';

// Both panes end at the visual viewport, including when the mobile keyboard resizes it.
function fitChat(shell, root, input) {
    const sheet=root.closest('.gc-chat-sheet');
    const condition=sheet?.querySelector(':scope > .gc-task-condition-disclosure');
    const header=root.closest('[data-step-details-page]')?.querySelector('.gc-workbook-header');
    const viewport=window.visualViewport;
    const stepNav=sheet?.closest('.step-main')?.querySelector('.step-page-nav');
    let frame=0, inputWidth=0;
    function measure() {
        frame=0;
        if(shell.hidden || !shell.getClientRects().length)return;
        const height=viewport?.height || innerHeight;
        const navSpace=stepNav ? stepNav.offsetHeight + parseFloat(getComputedStyle(stepNav).marginTop) : 0;
        const available=element=>{
            // Measure from the document origin so page scrolling never grows either panel.
            const y=element.getBoundingClientRect().top + window.scrollY;
            return Math.round(Math.max(height * .42, height - Math.max(0,y) - navSpace));
        };
        shell.style.setProperty('--gc-chat-height',available(shell)+'px');
        if(condition)condition.style.setProperty('--gc-reading-height',available(condition)+'px');
        if (input.clientWidth !== inputWidth) {
            inputWidth = input.clientWidth;
            autosize.update(input);
        }
    }
    function schedule(){if(!frame)frame=requestAnimationFrame(measure);}
    const observer=new ResizeObserver(schedule);
    for(const el of [header,sheet,condition,root,input,stepNav])if(el)observer.observe(el);
    window.addEventListener('resize',schedule);
    viewport?.addEventListener('resize',schedule);
    schedule();
    return ()=>{
        cancelAnimationFrame(frame);observer.disconnect();
        window.removeEventListener('resize',schedule);
        viewport?.removeEventListener('resize',schedule);
        shell.style.removeProperty('--gc-chat-height');condition?.style.removeProperty('--gc-reading-height');
    };
}

export function taskChat(root, actions) {
    const teacher = root.dataset.teacher === 'true' && root.dataset.user !== root.dataset.viewerId;
    const viewer = Number(root.dataset.viewerId);
    const withTutor = root.hasAttribute('data-chat-tutor');
    const title = node('strong', {}, withTutor ? tutorName : 'Чат задачи');
    const member = node('small');
    const avatar = withTutor
        ? node('img', {class:'gc-chat-avatar', src:tutorPortrait, alt:''})
        : node('span', {class:'gc-chat-avatar', 'aria-hidden':'true'}, 'ГК');
    const header = node('header', {class:'gc-chat-head'}, avatar, node('div', {}, title, withTutor ? null : member));
    if (actions.back) header.prepend(iconButton('К списку учеников','fa-arrow-left',actions.back));
    const messages = node('div', {class:'gc-chat-messages'});
    const log = node('div',{class:'gc-chat-log',role:'log','aria-label':'Сообщения по задаче'},messages);
    const earlier = button('Предыдущие сообщения',()=>load(true)); earlier.className='gc-chat-earlier';
    const status = node('small',{role:'status',class:'gc-chat-status'});
    const retry = button('Обновить',()=>load()); retry.hidden=true;
    const input = node('textarea',{rows:2,maxLength:3000,placeholder:'Сообщение','aria-label':'Сообщение в чат задачи'});
    const attach = button('Решение',actions.compose);
    attach.prepend(node('i',{class:'fas fa-paperclip','aria-hidden':'true'}),' ');attach.className='gc-chat-attach';attach.setAttribute('aria-label','Отправить решение');
    const send = node('button',{type:'submit',class:'gc-chat-send','aria-label':'Отправить сообщение'},node('i',{class:'fas fa-arrow-up','aria-hidden':'true'}));
    const quoted = node('span');
    const reply = node('div',{class:'gc-chat-reply',hidden:true},quoted,iconButton('Отменить ответ','fa-xmark',()=>setReply(null)));
    const form = node('form',{class:'gc-chat-compose'},reply,node('div',{class:'gc-chat-entry'},attach,input,send));
    const shell = node('section',{class:'gc-task-chat',hidden:true,'aria-label':'Личная переписка по задаче'},header,log,form,status,retry);
    let stopFit, stopWatching, refreshPending = false;
    let student=null, cursor=null, loading=false, token=0, readNumber=0, pending=false, replyTo=null;
    let events=new Map(), drafts=new Map(), newestIds=[], signature='';
    const endpoint = () => root.dataset.endpoint+'/chat/'+student;

    function saveDraft(clientId=null) {
        if(student) drafts.set(student,{text:input.value,reply:replyTo,clientId});
    }
    function setReply(target) {
        replyTo=target; reply.hidden=!target; quoted.textContent=target?'Ответ на посылку · '+target.label:'';
        saveDraft(); input.focus();
    }
    function enable() {send.disabled=pending || !input.value.trim(); input.readOnly=pending;}
    function action(text, handler, css) {
        const b=button(text,handler);b.className=css;return b;
    }
    function gradeAction(event) {
        return action('Оценить решение', () => actions.grade(event.attempt), 'gc-chat-link');
    }
    function tutorByline() {
        return node('strong', {class:'gc-chat-who'},
            node('img', {src:tutorPortrait, alt:'', width:28, height:28, class:'gc-tutor-avatar'}), tutorName);
    }
    function fileBubble(item, event, own) {
        if (!own) item.append(node('strong', {class:'gc-chat-who'}, member.textContent));
        const file = action('', () => actions.source(event.attempt), 'gc-chat-file');
        const caption = event.archived ? 'Версия GeekPaste' : 'Отправлено решение';
        file.append(node('i', {class:'fas fa-code', 'aria-hidden':'true'}),
            node('span', {}, node('small', {}, caption), node('b', {}, event.label)));
        const reply = iconButton(teacher ? 'Ответить на эту посылку' : 'Спросить об этой посылке',
            'fa-reply', () => setReply({id:event.attempt, label:event.reference || event.label}));
        reply.className = 'gc-chat-answer';
        item.append(node('div', {class:'gc-chat-file-row'}, file, reply));
    }

    const watching = new Map();
    function syncProgress() {
        const active = new Map([...events.values()].filter(e => e.progress).map(e => [e.run, e]));
        for (const [run, watch] of watching) {
            if (!active.has(run)) { watch.stop?.(); watching.delete(run); }
        }
        for (const [run, event] of active) {
            if (watching.has(run)) continue;
            const watch = {};
            watching.set(run, watch);
            const tokenForRun = () => request('/insider/code-submissions/'+event.source+'/subscription');
            tokenForRun().then(subscription => {
                if (watching.get(run) !== watch || subscription.execution_id !== run) return;
                watch.stop = subscribe(subscription, progress => {
                    watch.progress = progress;
                    const label = messages.querySelector('[data-run-progress="'+run+'"]');
                    if (label) label.textContent = executionPhase(progress).label;
                }, refresh, refresh, tokenForRun);
            }).catch(() => { if (watching.get(run) === watch) watching.delete(run); });
        }
    }

    function checkBody(item, event, inlinePoints) {
        if (event.progress) {
            const progress = watching.get(event.run)?.progress || event.progress;
            item.append(node('p', {class:'gc-chat-score', 'data-run-progress':event.run, role:'status'}, executionPhase(progress).label));
            return;
        }
        if (event.method === 'tests' && 'compiler_error' in event) {
            if (event.compiler_error) item.append(node('pre', {class:'gc-chat-diagnostic'}, event.compiler_error));
            return;
        }
        if (event.method === 'manual' && event.state === 'awaiting_review') {
            item.append(node('p', {class:'gc-chat-score'}, 'Ожидает проверки преподавателем.'));
            return;
        }
        if (!inlinePoints) {
            const result = node('p', {class:'gc-chat-score'});
            if (event.points != null) {
                result.append(node('strong', {}, event.points));
                if (event.maximum != null) result.append(' из ', event.maximum, pointsPlural.select(event.maximum) === 'one' ? ' балла' : ' баллов');
                else result.append(' ', {one:'балл', few:'балла'}[pointsPlural.select(event.points)] || 'баллов');
            }
            if (!result.hasChildNodes()) result.append(phases[event.state] || 'Проверка');
            item.append(result);
        }
        if (event.method === 'tests' && event.total > 0 && event.passed != null) item.append(testSummary(event));
        if (event.criteria?.length) {
            if (event.text) item.append(markdownContent(event.text));
            item.append(criterionTable(event.criteria));
            return;
        }
        if (!event.text) return;
        const content = markdownContent(event.text);
        if (event.text.length <= 400) {
            item.append(content);
            return;
        }
        const preview = (content.querySelector('p, li') || content).textContent.trim();
        const disclosure = node('details', {class:'gc-chat-feedback'},
            node('summary', {}, node('span', {class:'gc-chat-preview'}, preview),
                node('span', {class:'gc-chat-disclosure-label'}, 'Разбор проверки',
                    node('i', {class:'fas fa-chevron-down', 'aria-hidden':'true'}))), content);
        item.append(disclosure);
    }

    function bubble(event, previous) {
        const manualGrade = event.type === 'grade' && event.method === 'manual';
        const finished = event.type === 'check' && event.state === 'completed' || manualGrade;
        const inlinePoints = finished && event.points != null && event.maximum != null && !('compiler_error' in event);
        const followsSubmission = inlinePoints && !event.stronger && !event.archived
            && previous?.type === 'file' && previous.attempt === event.attempt
            && day.format(dateOf(previous)) === day.format(dateOf(event));
        const own = event.type === 'file' ? student === viewer : event.type === 'message' && event.author === viewer;
        const item = node('article', {class:'gc-chat-bubble' + (own ? ' is-own' : ''), 'data-event':event.id, 'data-kind':event.type, 'data-attempt':event.attempt});
        const when = dateOf(event);
        const time = node('time', {dateTime:event.at, title:day.format(when) + ' ' + clock.format(when)}, clock.format(when));
        if (event.type === 'file') {
            fileBubble(item, event, own);
            if (event.awaiting_teacher) {
                const pending = node('div', {class:'gc-chat-file-pending'}, 'Решение получено. Ожидает проверки преподавателем.');
                if (teacher) pending.append(gradeAction(event));
                item.append(pending);
            }
            item.append(time);
            return item;
        }
        if (!own) {
            let author = event.name;
            if (event.type === 'grade') author = 'Результат проверки';
            if (event.type === 'check') author = event.stronger ? 'Расширенная проверка' : event.archived ? 'Проверка сохранённой версии'
                : ({gpt:'Проверка по критериям', manual:'Проверка преподавателя', tests:'Автотесты'}[event.method] || 'Проверка');
            if (event.method === 'manual' && finished) author = 'Проверено преподавателем';
            if (event.method === 'manual' && event.state === 'awaiting_review') author = 'Решение получено';
            if (event.method === 'tests' && 'compiler_error' in event) author = 'Ошибка компиляции';
            else if (event.method === 'tests' && finished && event.total > 0 && event.passed != null) {
                author = Number(event.passed) === Number(event.total) ? 'Все тесты пройдены'
                    : Number(event.passed) > 0 ? 'Пройдена часть тестов' : 'Тесты не пройдены';
            }
            const byline = event.type === 'tutor' ? tutorByline() : node('strong', {class:'gc-chat-who'});
            if (event.type === 'check' || event.type === 'grade') {
                const compilation = 'compiler_error' in event;
                const counts = event.method === 'tests' && event.total > 0 && event.passed != null;
                const score = counts ? Number(event.passed) : event.points, maximum = counts ? Number(event.total) : event.maximum;
                const tone = compilation || event.state === 'error' ? 'failed' : !finished || score == null ? 'waiting'
                    : maximum > 0 && score >= maximum ? 'passed' : score > 0 ? 'partial' : 'failed';
                item.dataset.verdict = tone;
                const icon = {waiting:'fa-clock', passed:'fa-circle-check', partial:'fa-circle-half-stroke', failed:'fa-circle-xmark'}[tone];
                byline.append(node('i', {class:'fas '+icon, 'aria-hidden':'true'}));
            }
            if (event.type !== 'tutor') byline.append(author);
            if (inlinePoints) {
                item.append(node('div', {class:'gc-chat-check-line'}, byline,
                    node('span', {class:'gc-chat-check-points'}, node('strong', {}, event.points),
                        node('span', {'aria-hidden':'true'}, ' / '),
                        node('span', {class:'visually-hidden'}, ' из '), event.maximum, pointsPlural.select(event.maximum) === 'one' ? ' балла' : ' баллов')));
            } else item.append(byline);
        }
        if (event.attempt && !followsSubmission) {
            const reference = event.reference || 'Посылка';
            const quote = action(reference, () => actions.source(event.attempt), 'gc-chat-quote');
            quote.title = 'Открыть решение · ' + reference;
            item.append(quote);
        }
        if (event.type === 'tutor') {
            item.append(markdownContent(event.text, {conversation:true}));
            const info = event.tutor || {};
            if (info.recheck) {
                const r = info.recheck;
                item.append(node('blockquote', {}, r.claim), node('small', {}, (r.location.file || 'Сдача') + ' · строка ' + r.location.line),
                    node('pre', {}, r.evidence), node('strong', {}, 'Что перепроверить'), node('p', {}, r.focus));
                if (['queued','already_requested'].includes(r.status)) item.append(node('small', {}, 'Перепроверка поставлена в очередь.'));
            }
            for (const example of info.test_examples || []) {
                const details = node('details', {class:'gc-chat-feedback'}, node('summary', {}, 'Тест № ' + example.number + ' · бесплатно'));
                for (const [field, title] of [['input','Ввод'], ['expected','Ожидалось'], ['actual','Получено']]) {
                    if (example[field] != null) details.append(node('small', {}, title), node('pre', {}, example[field] || 'Пустая строка'));
                }
                details.append(node('small', {}, 'Полные данные открыты в отчёте проверки.'));
                item.append(details);
            }
            if (info.reference) item.append(node('strong', {}, info.reference.title), node('p', {}, info.reference.text), node('code', {}, info.reference.example));
            for (const link of info.links || []) item.append(node('a', {href:link.url, class:'d-block'}, link.heading || link.title));
            if (info.coins === 1) {
                const reward = node('small', {class:'gc-chat-coin'},
                    node('i', {class:'fas fa-coins', 'aria-hidden':'true'}), node('strong', {}, '+1 GC'), ' за хороший вопрос');
                item.append(node('div', {class:'gc-chat-foot gc-chat-tutor-foot'}, reward, time));
            } else item.append(time);
            return item;
        }
        if (event.type === 'message') {
            const prose = markdownContent(event.text, {conversation:true});
            item.append(prose, time);
            if (prose.lastElementChild?.matches('p')) item.classList.add('has-inline-time');
            return item;
        }
        const footer = node('div', {class:'gc-chat-foot'});
        if (event.type === 'grade') {
            if (manualGrade) checkBody(item, event, inlinePoints);
            else item.append(node('p', {class:'gc-chat-score'}, event.text));
            if (!teacher) footer.append(action('Посмотреть проверку', () => actions.details(event.attempt), 'gc-chat-link'));
        } else {
            checkBody(item, event, inlinePoints);
            if (teacher && event.awaiting_teacher) footer.append(gradeAction(event));
            if (event.criteria?.length) footer.append(action('Подробнее', () => showCriteria(event), 'gc-chat-link'));
            if (event.method === 'tests' && ['completed','error'].includes(event.state)) {
                footer.append(action('Подробнее', () => actions.report(event), 'gc-chat-link'));
            }
            if (!teacher && withTutor && event.run && event.source && event.state === 'completed') {
                const reply = iconButton('Спросить об этой проверке','fa-reply',()=>setReply({id:event.source,run:event.run,label:event.reference || 'Проверка'}));
                reply.className = 'gc-chat-answer';
                footer.append(reply);
            }
        }
        if (teacher && (event.type === 'grade' || event.reviewable)) {
            footer.append(action('Перепроверить', () => actions.recheck(event), 'gc-chat-link'));
        }
        footer.append(time);
        item.append(footer);
        return item;
    }
    function render(older=false,forceBottom=false) {
        const wasBottom=Math.abs(log.scrollTop)<45;
        const anchor=[...log.querySelectorAll('[data-event]')].find(el=>el.getBoundingClientRect().bottom>log.getBoundingClientRect().top);
        const offset=anchor?.getBoundingClientRect().top;
        const sorted=[...events.values()].sort((a,b)=>a.at.localeCompare(b.at) || (a.id<b.id?-1:a.id>b.id?1:0));
        const expanded = new Set([...messages.querySelectorAll('.gc-chat-feedback[open]')].map(el => el.closest('[data-event]').dataset.event));
        const today = day.format(new Date());
        const parts=[];let date='';
        if(cursor)parts.push(earlier);
        else if(withTutor)parts.push(node('article', {class:'gc-chat-bubble', 'data-kind':'tutor'}, tutorByline(),
            markdownContent('Отправь мне решение или задай любой вопрос по задаче. Чтобы открыть редактор, нажми «Решение». Можно спросить и до первой попытки.')));
        for(const [index, e] of sorted.entries()){
            const stamp=day.format(dateOf(e));
            if(stamp!==date){parts.push(node('small',{class:'gc-chat-day',title:stamp},stamp===today?'Сегодня':stamp));date=stamp;}
            const item = bubble(e, sorted[index - 1]);
            const feedback = expanded.has(e.id) && item.querySelector('.gc-chat-feedback');
            if (feedback) feedback.open = true;
            parts.push(item);
        }
        if(!sorted.length && !withTutor)parts.push(node('p',{class:'gc-chat-empty'},teacher?'Сообщений пока нет.':'Здесь можно задать вопрос преподавателю.'));
        const focus=log.contains(document.activeElement)?document.activeElement:null;
        const focusId=focus?.closest('[data-event]')?.dataset.event;
        const controlIndex=focusId?[...focus.closest('[data-event]').querySelectorAll('button, summary, a')].indexOf(focus):-1;
        messages.replaceChildren(...parts);
        if(focusId)log.querySelector('[data-event="'+focusId+'"]')?.querySelectorAll('button, summary, a')[controlIndex]?.focus({preventScroll:true});
        if(forceBottom || (!older && wasBottom))log.scrollTop=0;
        else if(anchor){const saved=log.querySelector('[data-event="'+anchor.dataset.event+'"]');if(saved)log.scrollTop+=saved.getBoundingClientRect().top-offset;}
    }
    async function load(older=false,force=false) {
        if(!student || (loading && !force) || (older && !cursor))return;
        const context=token, read=++readNumber;loading=true;earlier.disabled=true;
        try {
            const page=await request(endpoint()+(older?'?before='+encodeURIComponent(cursor):''));
            if(context!==token || read!==readNumber)return;
            retry.hidden=true;
            if(!pending)status.textContent='';
            const initial=signature==='';
            const changed=[];
            if(!older){
                if (typeof page.awaiting_reply === 'boolean') {
                    const marker = root.closest('.gc-task-sheet')?.querySelector('[data-student-id="'+student+'"] [data-chat-waiting]');
                    if (marker) marker.hidden = !page.awaiting_reply;
                }
                const updated=JSON.stringify(page);
                if(updated===signature)return;
                if(!initial)for(const item of page.events) {
                    if(['file','check','grade'].includes(item.type) && JSON.stringify(events.get(item.id))!==JSON.stringify(item))changed.push(item.attempt);
                }
                signature=updated;
                // If a whole page arrived between visits, reopen the gap through the older-page cursor.
                const overlap=page.events.some(e=>newestIds.includes(e.id));
                if(!overlap || events.size<=40){events.clear();cursor=page.before;}
                newestIds=page.events.map(e=>e.id);
            }else cursor=page.before;
            for(const e of page.events)events.set(e.id,e);
            syncProgress();
            render(older,initial);
            if(changed.length)actions.updated(changed);
        }catch(e){if(context===token && read===readNumber){status.textContent='Не удалось загрузить переписку.';retry.hidden=false;}}
        finally {
            if (context === token && read === readNumber) {
                loading = false;
                earlier.disabled = false;
                if (refreshPending) { refreshPending = false; load(); }
            }
        }
    }
    function refresh() {
        if (loading) refreshPending = true;
        else load();
    }
    function select(studentId, name, canSubmit) {
        if(student && student!==Number(studentId))saveDraft(drafts.get(student)?.clientId);
        for (const watch of watching.values()) watch.stop?.();
        watching.clear();
        token++;stopWatching?.();refreshPending=false;loading=false;pending=false;events=new Map();newestIds=[];signature='';cursor=null;
        stopFit?.();stopFit=null;
        student=Number(studentId)||null;shell.hidden=!student;
        if(!student){autosize.destroy(input);return;}
        stopFit=fitChat(shell,root,input);
        member.textContent=teacher?(name || 'Ученик'):'Вы и преподаватель';
        attach.hidden=!canSubmit;
        const draft=drafts.get(student);replyTo=draft?.reply || null;input.value=draft?.text || '';
        autosize(input);
        autosize.update(input);
        reply.hidden=!replyTo;quoted.textContent=replyTo?'Ответ на посылку · '+replyTo.label:'';
        messages.replaceChildren();status.textContent='Загружаем переписку…';retry.hidden=true;enable();load();stopWatching=watchTaskChat(root, student, refresh);
    }
    window.addEventListener('pagehide', () => { for (const watch of watching.values()) watch.stop?.(); watching.clear(); });
    input.oninput=()=>{saveDraft();enable();};
    input.onkeydown=e=>{if(e.key==='Enter' && !e.shiftKey && !e.isComposing){e.preventDefault();form.requestSubmit();}};
    form.onsubmit=async e=>{
        e.preventDefault();
        if(!student || pending || !input.value.trim())return;
        const context=token, recipient=student, url=endpoint();
        const id=drafts.get(student)?.clientId || crypto.randomUUID();
        saveDraft(id);pending=true;enable();
        try{
            await request(url,'POST',{body:input.value.trim(),attempt:replyTo?.id || null,run_id:replyTo?.run || null,client_id:id});
            if(drafts.get(recipient)?.clientId===id)drafts.delete(recipient);
            if(context!==token)return;
            input.value='';autosize.update(input);replyTo=null;reply.hidden=true;status.textContent='Сообщение отправлено.';
            // Invalidate an older in-flight read so the post is visible immediately.
            await load(false,true);if(context===token)log.scrollTop=0;
        }catch(error){if(context===token)status.textContent='Сообщение не отправлено. '+error.message;}
        finally{if(context===token){pending=false;enable();}}
    };
    return {shell,select,refresh,setCanSubmit:value=>{attach.hidden=!value;},setName:name=>{member.textContent=name;}};
}
