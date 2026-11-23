import {node, button, iconButton, request, phases} from './ui';
import {watchTaskChat} from './chat-live';
import {showCriteria, criterionTable, testSummary, testGroupSummary} from './chat-results';
import {combinedCheck} from './authoring-settings';
import {markdownContent} from './feedback';
import autosize from 'autosize';
import {chatDesktop, mountTaskChatPanel} from '../components/workbook-navigation';
import {executionPhase} from './composer-options';
import {subscribe} from './progress';

const clock = new Intl.DateTimeFormat('ru-RU',{hour:'2-digit',minute:'2-digit'});
const day = new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric'});
const pointsPlural = new Intl.PluralRules('ru-RU');
const dateOf = item => new Date(item.at);
const tutorPortrait = '/images/rostislop.webp';
const tutorName = 'Ростислоп Нейрославович';
const askTeacherLabel = 'Спросить преподавателя';
const teacherMention = '@преподаватель ';
const historyTimeout = 12_000;
const hasTeacherMention = /(?:^|[^\p{L}\p{N}_@])@преподаватель(?![\p{L}\p{N}_])/iu;

// Keep the composer visible when the phone keyboard changes the viewport.
function fitChat(shell, root, input, dialog) {
    const sheet=root.closest('.gc-chat-sheet');
    const desktop=chatDesktop();
    const condition=sheet?.querySelector('[data-task-condition]');
    const header=root.closest('[data-step-details-page]')?.querySelector('.gc-workbook-header');
    const viewport=window.visualViewport;
    let frame=0, inputWidth=0;
    function measure() {
        frame=0;
        if (dialog && viewport) {
            dialog.style.setProperty('--gc-chat-viewport-height', viewport.height+'px');
            dialog.style.setProperty('--gc-chat-viewport-top', viewport.offsetTop+'px');
        }
        if(shell.hidden || !shell.getClientRects().length)return;
        if (!dialog && (!sheet || !desktop.matches)) {
            const height=viewport?.height || innerHeight;
            const top=shell.getBoundingClientRect().top + window.scrollY;
            shell.style.setProperty('--gc-chat-height', Math.round(Math.max(height * .42, height - Math.max(0,top)))+'px');
        }
        if (input.clientWidth !== inputWidth) {
            inputWidth = input.clientWidth;
            autosize.update(input);
        }
    }
    function schedule(){if(!frame)frame=requestAnimationFrame(measure);}
    const observer=new ResizeObserver(schedule);
    for(const el of [header,sheet,condition,root,shell,input])if(el)observer.observe(el);
    window.addEventListener('resize',schedule);
    viewport?.addEventListener('resize',schedule);
    if (dialog) viewport?.addEventListener('scroll',schedule);
    schedule();
    return ()=>{
        cancelAnimationFrame(frame);observer.disconnect();
        window.removeEventListener('resize',schedule);
        viewport?.removeEventListener('resize',schedule);
        if (dialog) viewport?.removeEventListener('scroll',schedule);
        shell.style.removeProperty('--gc-chat-height');
    };
}

export function taskChat(root, actions) {
    const teacher = root.dataset.teacher === 'true' && root.dataset.user !== root.dataset.viewerId;
    const viewer = Number(root.dataset.viewerId);
    const withTutor = root.hasAttribute('data-chat-tutor');
    const sheet = root.closest('.gc-task-sheet');
    const openChat = sheet?.querySelector('[data-task-chat-open]');
    const dialog = openChat ? node('dialog', {id:openChat.getAttribute('aria-controls'), class:'gc-task-chat-dialog', 'aria-label':'Чат задачи'}) : null;
    const title = node('strong', {}, withTutor ? tutorName : 'Чат задачи');
    const member = node('small');
    const avatar = withTutor
        ? node('img', {class:'gc-chat-avatar', src:tutorPortrait, alt:''})
        : node('span', {class:'gc-chat-avatar', 'aria-hidden':'true'}, 'ГК');
    const header = node('header', {class:'gc-chat-head'}, avatar, node('div', {}, title, withTutor ? null : member));
    if (actions.back) header.prepend(iconButton('К списку учеников','fa-arrow-left',actions.back));
    if (dialog) {
        const back = button('К задаче', () => dialog.close());
        back.classList.add('gc-chat-back-to-task');
        back.setAttribute('autofocus', '');
        header.prepend(back);
    }
    const messages = node('div', {class:'gc-chat-messages'});
    const log = node('div',{class:'gc-chat-log',role:'log','aria-label':'Сообщения по задаче'},messages);
    const earlier = button('Предыдущие сообщения',()=>load(true)); earlier.className='gc-chat-earlier';
    const status = node('small',{role:'status',class:'gc-chat-status'});
    const readStatus = node('p', {class:'gc-chat-empty mb-0', role:'status'});
    const retry = button('Повторить', () => {
        showHistoryStatus('Загружаем переписку…');
        load(false, true);
    });
    const readNotice = node('div', {class:'gc-chat-read-state'}, readStatus, retry);
    function showHistoryStatus(message, allowRetry = false) {
        readStatus.textContent = message;
        retry.hidden = !allowRetry;
        if (readNotice.parentElement !== messages) messages.append(readNotice);
    }
    const input = node('textarea',{rows:2,maxLength:3000,placeholder:'Сообщение','aria-label':'Сообщение в чат задачи'});
    const attach = button('Решение',actions.compose);
    attach.prepend(node('i',{class:'fas fa-paperclip','aria-hidden':'true'}),' ');attach.className='gc-chat-attach';attach.setAttribute('aria-label','Отправить решение');
    const askTeacher = withTutor && root.dataset.teacher !== 'true'
        ? button(askTeacherLabel, mentionTeacher) : null;
    if (askTeacher) {
        askTeacher.className = 'gc-chat-attach';
        askTeacher.prepend(node('i', {class:'fas fa-at', 'aria-hidden':'true'}), ' ');
    }
    const send = node('button',{type:'submit',class:'gc-chat-send','aria-label':'Отправить сообщение'},node('i',{class:'fas fa-arrow-up','aria-hidden':'true'}));
    const quoted = node('span');
    const reply = node('div',{class:'gc-chat-reply',hidden:true},quoted,iconButton('Отменить ответ','fa-xmark',()=>setReply(null)));
    const shortcuts = askTeacher ? node('div', {class:'gc-chat-shortcuts'}, attach, askTeacher) : null;
    const form = node('form',{class:'gc-chat-compose'},reply,shortcuts,node('div',{class:'gc-chat-entry'},shortcuts ? null : attach,input,send));
    const shell = node('section',{class:dialog ? 'gc-task-chat gc-task-chat--panel' : 'gc-task-chat',hidden:true,'aria-label':'Личная переписка по задаче'},header,log,form,status);
    const solutions = actions.solutions;
    let selectView = () => {};
    if (solutions) {
        log.id = root.id+'-messages';
        const chatButton = button('Чат', () => selectView(false));
        const listButton = button('Список решений', () => selectView(true));
        chatButton.classList.remove('btn-outline-secondary');
        listButton.classList.remove('btn-outline-secondary');
        chatButton.setAttribute('aria-controls', log.id);
        listButton.setAttribute('aria-controls', solutions.id);
        selectView = showSolutions => {
            chatButton.setAttribute('aria-pressed', String(!showSolutions));
            listButton.setAttribute('aria-pressed', String(showSolutions));
            log.hidden = form.hidden = status.hidden = showSolutions;
            solutions.hidden = !showSolutions;
            if (!showSolutions && !shell.hidden) autosize.update(input);
        };
        header.classList.add('gc-chat-head--with-views');
        header.append(node('div', {class:'gc-chat-views', role:'group', 'aria-label':'Вид панели'}, chatButton,listButton));
        shell.append(solutions);
    }
    const surface = dialog ? node('div', {}, dialog) : shell;
    let revealTutorStart = false;
    if (dialog) mountTaskChatPanel({
        sheet, opener:openChat, dialog, panel:shell, onOpen:() => { refresh(); if (withTutor) showLatestTutorStart(); },
    });
    let stopFit, stopWatching, activeRead, refreshPending = false, awaitingTutor = false;
    let student=null, cursor=null, loading=false, token=0, readNumber=0, pending=false, replyTo=null;
    let events=new Map(), drafts=new Map(), newestIds=[], signature='';
    const endpoint = () => root.dataset.endpoint+'/chat/'+student;

    function saveDraft(clientId=null) {
        if(student) drafts.set(student,{text:input.value,reply:replyTo,clientId});
    }
    function setReply(target) {
        replyTo=target; reply.hidden=!target; quoted.textContent=target?'Ответ на посылку · '+target.label:'';
        saveDraft(); input.focus({preventScroll:chatDesktop().matches});
    }
    function mentionTeacher() {
        input.focus({preventScroll:true});
        if (hasTeacherMention.test(input.value)) return;
        if (input.value.length + teacherMention.length > input.maxLength) {
            input.setCustomValidity('Сократи сообщение, чтобы в нём поместилось обращение к преподавателю.');
            input.reportValidity();
            input.setCustomValidity('');
            return;
        }
        const start = input.selectionStart, end = input.selectionEnd;
        input.setRangeText(teacherMention, 0, 0);
        input.setSelectionRange(start + teacherMention.length, end + teacherMention.length);
        input.dispatchEvent(new Event('input', {bubbles:true}));
    }
    function enable() {
        send.disabled = pending || !input.value.trim();
        input.readOnly = pending;
        if (askTeacher) askTeacher.disabled = pending;
    }
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
                    if (progress.phase === 'grading' && event.method === combinedCheck && !events.get(event.id)?.test_groups && !watch.checkedReport) {
                        watch.checkedReport = true;
                        refresh();
                    }
                }, refresh, refresh, tokenForRun);
            }).catch(() => { if (watching.get(run) === watch) watching.delete(run); });
        }
    }

    function checkBody(item, event, inlinePoints) {
        if (event.method === combinedCheck) {
            const groups = testGroupSummary(event.test_groups);
            if (groups) item.append(groups);
            if (event.compiler_error) item.append(node('pre', {class:'gc-chat-diagnostic'}, event.compiler_error));
        }
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

    const quickRequests = new Map();
    async function askAbout(key, body, attempt, run, tutorAction = null) {
        if (pending || !student) return;
        const context = token, requestKey = student + ':' + key;
        const clientId = quickRequests.get(requestKey) || crypto.randomUUID();
        quickRequests.set(requestKey, clientId);
        pending = true;
        enable();
        try {
            await request(endpoint(), 'POST', {body, attempt, run_id:run, tutor_action:tutorAction,
                client_id:clientId, chat_layout:dialog?.open ? 'dialog' : 'panel'});
            if (context === token) await load(false, true);
        } catch (error) {
            if (context === token) status.textContent = 'Сообщение не отправлено. ' + error.message;
        } finally {
            if (context === token) { pending = false; enable(); }
        }
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
                : ({gpt:'Проверка по критериям', manual:'Проверка преподавателя', tests:'Автотесты', [combinedCheck]:'Тесты → ИИ'}[event.method] || 'Проверка');
            if (event.method === 'manual' && finished) author = 'Проверено преподавателем';
            if (event.method === 'manual' && event.state === 'awaiting_review') author = 'Решение получено';
            if (event.method === 'tests' && 'compiler_error' in event) author = 'Ошибка компиляции';
            else if (event.method === 'tests' && finished && event.total > 0 && event.passed != null) {
                author = Number(event.passed) === Number(event.total) ? 'Все тесты пройдены'
                    : Number(event.passed) > 0 ? 'Пройдена часть тестов' : 'Тесты не пройдены';
            }
            const byline = event.type === 'tutor' ? tutorByline() : node('strong', {class:'gc-chat-who'});
            if (event.type === 'check' || event.type === 'grade') {
                const compilation = event.method === 'tests' && 'compiler_error' in event;
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
                const labels = {queued:'Перепроверка поставлена в очередь. Результат появится в истории.',
                    already_requested:'Эта перепроверка уже запрашивалась. Посмотри результат в истории.',
                    superseded:'У этой посылки есть более новая проверка. Открой её в истории.',
                    teacher:'Это замечание можно обсудить с преподавателем.',
                    unavailable:'Автоматическая перепроверка недоступна. Можно спросить преподавателя.'};
                if (labels[r.status]) item.append(node('small', {}, labels[r.status]));
                if (askTeacher && ['teacher','unavailable'].includes(r.status)) {
                    item.append(action(askTeacherLabel, mentionTeacher, 'gc-chat-link'));
                }
            }
            if (withTutor && !teacher) for (const choice of info.choices || []) {
                const attempt = choice.submission_id || 'solution-' + choice.legacy_solution;
                const label = 'Проверка ' + (choice.at || '') + (choice.legacy_solution ? ' · № ' + choice.legacy_solution : '');
                item.append(action(label, () => askAbout(event.id + ':' + (choice.run_id || attempt),
                    info.choice_question || 'Объясни оценку этой проверки.', attempt, choice.run_id || null), 'gc-chat-link'));
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
            if (info.links?.length) item.append(node('div',{class:'gc-chat-sources'},
                info.links.map(link=>node('a',{href:link.url},link.heading || link.title))));
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
            if (event.tutor_review) {
                const review = event.tutor_review;
                const outcome = event.state === 'completed' ? 'Было ' + review.previous + '/' + review.maximum +
                    ', после перепроверки ' + event.points + '/' + event.maximum + '.' :
                    event.state === 'error' ? 'Перепроверка завершилась технической ошибкой.' : 'Перепроверка выполняется.';
                item.append(node('p', {}, outcome + (review.focus ? ' Вопрос: ' + review.focus : '')));
            }
            if (teacher && event.awaiting_teacher) footer.append(gradeAction(event));
            if (event.criteria?.length) footer.append(action('Подробнее', () => showCriteria(event), 'gc-chat-link'));
            if (event.method === combinedCheck && event.test_groups?.length) {
                footer.append(action('Журнал тестирования', () => actions.report(event), 'gc-chat-link'));
            }
            if (event.method === 'tests' && ['completed','error'].includes(event.state)) {
                footer.append(action('Подробнее', () => actions.report(event), 'gc-chat-link'));
            }
            if (!teacher && withTutor && event.run && event.source && event.state === 'completed') {
                if (askTeacher && event.method === 'tests' && event.total >= 3 && event.passed < event.total) {
                    footer.append(action('Показать бесплатные примеры непройденных тестов',
                        () => askAbout(event.id, 'Покажи бесплатные примеры непройденных тестов.', event.source, event.run, 'examples'),
                        'gc-chat-link'));
                }
                const reply = iconButton('Спросить об этой проверке','fa-reply',()=>setReply({id:event.source,run:event.run,label:event.reference || 'Проверка'}));
                reply.className = 'gc-chat-answer';
                footer.append(reply);
            }
        }
        if (event.student_review === 'available') {
            footer.append(action('Попросить перепроверить', () => actions.requestReview(event.attempt, event.reference, status => {
                const result = events.get(event.id);
                if (result) result.student_review = status.requested ? 'requested' : null;
                render();
                refresh();
            }), 'gc-chat-link'));
        } else if (event.student_review === 'requested') {
            footer.append(node('small',{class:'gc-help-text'},'Запрос на перепроверку отправлен'));
        }
        if (teacher && (event.type === 'grade' || event.reviewable)) {
            footer.append(action('Перепроверить', () => actions.recheck(event), 'gc-chat-link'));
        }
        footer.append(time);
        item.append(footer);
        return item;
    }
    function showLatestTutorStart() {
        if (!revealTutorStart || !log.clientHeight) return;
        revealTutorStart = false;
        const latest = messages.querySelector('article[data-event]:last-of-type');
        if (latest?.dataset.kind !== 'tutor' || latest.offsetHeight <= log.clientHeight) return;
        log.scrollTop += latest.getBoundingClientRect().top - log.getBoundingClientRect().top;
    }
    function render(older=false,forceBottom=false) {
        const wasBottom=Math.abs(log.scrollTop)<45;
        const previousLatest = messages.querySelector('article[data-event]:last-of-type')?.dataset.event;
        const anchor=[...log.querySelectorAll('[data-event]')].find(el=>el.getBoundingClientRect().bottom>log.getBoundingClientRect().top);
        const offset=anchor?.getBoundingClientRect().top;
        const sorted=[...events.values()].sort((a,b)=>a.at.localeCompare(b.at) || (a.id<b.id?-1:a.id>b.id?1:0));
        const expanded = new Set([...messages.querySelectorAll('.gc-chat-feedback[open]')].map(el => el.closest('[data-event]').dataset.event));
        const today = day.format(new Date());
        const parts=[];let date='';
        if(cursor)parts.push(earlier);
        else if(withTutor)parts.push(node('article', {class:'gc-chat-bubble', 'data-kind':'tutor'}, tutorByline(),
            markdownContent('Отправь мне решение или задай вопрос по задаче. Можно спросить и до первой попытки. Чтобы открыть редактор, нажми «Решение».' + (askTeacher ? ' Нужна помощь преподавателя? Нажми «'+askTeacherLabel+'».' : ''))));
        for(const [index, e] of sorted.entries()){
            const stamp=day.format(dateOf(e));
            if(stamp!==date){parts.push(node('small',{class:'gc-chat-day',title:stamp},stamp===today?'Сегодня':stamp));date=stamp;}
            const item = bubble(e, sorted[index - 1]);
            const feedback = expanded.has(e.id) && item.querySelector('.gc-chat-feedback');
            if (feedback) feedback.open = true;
            parts.push(item);
        }
        if (awaitingTutor) parts.push(node('small', {class:'gc-chat-typing'},
            node('img', {src:tutorPortrait, width:20, height:20, alt:''}), 'Ростислоп набирает сообщение…'));
        if(!sorted.length && !withTutor)parts.push(node('p',{class:'gc-chat-empty'},teacher?'Сообщений пока нет.':'Здесь можно задать вопрос преподавателю.'));
        const focus=log.contains(document.activeElement)?document.activeElement:null;
        const focusId=focus?.closest('[data-event]')?.dataset.event;
        const controlIndex=focusId?[...focus.closest('[data-event]').querySelectorAll('button, summary, a')].indexOf(focus):-1;
        messages.replaceChildren(...parts);
        if(focusId)log.querySelector('[data-event="'+focusId+'"]')?.querySelectorAll('button, summary, a')[controlIndex]?.focus({preventScroll:true});
        if(forceBottom || (!older && wasBottom)) {
            log.scrollTop=0;
            if (dialog && sorted.at(-1)?.id !== previousLatest) {
                revealTutorStart = sorted.at(-1)?.type === 'tutor';
                showLatestTutorStart();
            }
        }
        else if(anchor){const saved=log.querySelector('[data-event="'+anchor.dataset.event+'"]');if(saved)log.scrollTop+=saved.getBoundingClientRect().top-offset;}
    }
    async function load(older=false,force=false) {
        if(!student || (loading && !force) || (older && !cursor))return;
        const context=token, read=++readNumber;loading=true;earlier.disabled=true;
        activeRead?.abort();
        const controller = new AbortController();
        activeRead = controller;
        const timeout = setTimeout(() => controller.abort(), historyTimeout);
        try {
            const page=await request(endpoint()+(older?'?before='+encodeURIComponent(cursor):''), 'GET', undefined, {signal:controller.signal});
            if(context!==token || read!==readNumber)return;
            readNotice.remove();
            if (!older) {
                awaitingTutor = withTutor && page.awaiting_tutor === true;
            }
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
            render(older,initial || force);
            if(changed.length)actions.updated(changed);
        }catch(e){if(context===token && read===readNumber)showHistoryStatus('Не удалось загрузить переписку.', true);}
        finally {
            clearTimeout(timeout);
            if (activeRead === controller) activeRead = null;
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
        activeRead?.abort();
        stopFit?.();stopFit=null;
        student=Number(studentId)||null;shell.hidden=!student;
        if(!student){dialog?.close();autosize.destroy(input);return;}
        selectView(false);
        stopFit=fitChat(shell,root,input,dialog || sheet?.querySelector('.gc-teacher-chat-dialog'));
        member.textContent=teacher?(name || 'Ученик'):'Вы и преподаватель';
        attach.hidden=!canSubmit;
        const draft=drafts.get(student);replyTo=draft?.reply || null;input.value=draft?.text || '';
        autosize(input);
        autosize.update(input);
        reply.hidden=!replyTo;quoted.textContent=replyTo?'Ответ на посылку · '+replyTo.label:'';
        awaitingTutor=false;revealTutorStart=false;messages.replaceChildren();status.textContent='';
        showHistoryStatus('Загружаем переписку…');
        enable();load();stopWatching=watchTaskChat(root, shell, student, refresh);
    }
    window.addEventListener('pagehide', () => { for (const watch of watching.values()) watch.stop?.(); watching.clear(); });
    input.oninput=()=>{saveDraft();enable();};
    input.onkeydown=e=>{if(e.key==='Enter' && !e.shiftKey && !e.isComposing){e.preventDefault();form.requestSubmit();}};
    form.onsubmit=async e=>{
        e.preventDefault();
        if(!student || pending || !input.value.trim())return;
        const context=token, recipient=student, url=endpoint();
        const id=drafts.get(student)?.clientId || crypto.randomUUID();
        status.textContent = '';
        saveDraft(id);pending=true;enable();
        try{
            await request(url,'POST',{body:input.value.trim(),attempt:replyTo?.id || null,run_id:replyTo?.run || null,client_id:id,chat_layout:dialog?.open ? 'dialog' : 'panel'});
            if(drafts.get(recipient)?.clientId===id)drafts.delete(recipient);
            if(context!==token)return;
            input.value='';autosize.update(input);replyTo=null;reply.hidden=true;
            // Invalidate an older in-flight read so the post is visible immediately.
            await load(false,true);
        }catch(error){if(context===token)status.textContent='Сообщение не отправлено. '+error.message;}
        finally{if(context===token){pending=false;enable();}}
    };
    return {surface,select,refresh,setCanSubmit:value=>{attach.hidden=!value;},setName:name=>{member.textContent=name;}};
}
