import {node, button, iconButton, field, input, textarea, actions, download} from './ui';
import {commentEditor} from './comment-editor';

let commentFieldId=0;

export function gradingPanel({key, comment = '', mark = '', maximum, hint, onSave, onNext, onSkip, drafts}) {
    const value = drafts.get(key) || {mark:mark ?? '', comment};
    const status = node('p', {class:'gc-help-text', role:'status'},'Сохраните, чтобы опубликовать оценку и комментарий.');
    const remember = () => {drafts.set(key, {...value}); status.textContent = 'Есть изменения · ученик их ещё не видит';};
    const grade = input(value.mark, v => {value.mark = v; remember();}, {type:'number', min:0, max:maximum});
    let richEditor;
    const resize = () => {if(richEditor?.ready){richEditor.refresh();return;}feedback.style.height='auto';feedback.style.height=Math.max(190,feedback.scrollHeight+2)+'px';};
    const feedbackId='gc-review-comment-'+(++commentFieldId);
    const feedback = textarea(value.comment, v => {value.comment = v; remember();if(!richEditor?.ready)resize();}, {id:feedbackId,rows:7, placeholder:'Что получилось и что стоит исправить','aria-label':'Комментарий ученику'});
    const label=node('label',{class:'gc-help-text',htmlFor:feedbackId,onclick:event=>{if(richEditor?.ready){event.preventDefault();richEditor.focus();}}},'Комментарий ученику');
    const editor=node('div',{class:'gc-comment-editor'},label,feedback,node('span',{id:feedbackId+'-help',class:'gc-help-text'},'Поддерживает Markdown: списки, код и таблицы'));
    const form = node('div', {class:'gc-inline-grade__editor'}, field('Баллы, из '+maximum, grade), editor);
    let saving=false;
    const save = async () => {
        if(saving)return;
        if (value.mark === '' || !Number.isFinite(Number(value.mark)) || Number(value.mark) < 0 || Number(value.mark) > maximum) {
            status.textContent = `Укажите баллы от 0 до ${maximum}.`; grade.focus(); return;
        }
        saving=true;form.setAttribute('inert',''); status.textContent = 'Сохраняем…';
        try {
            const saved = await onSave(Number(value.mark), value.comment);
            if(saved!==false)drafts.delete(key);
        } catch (error) {status.textContent = error.message;}
        finally {saving=false;form.removeAttribute('inert');}
    };
    const primary = button('Сохранить оценку',save,'primary');primary.classList.add('gc-grade-save');primary.setAttribute('aria-keyshortcuts','Control+Enter Meta+Enter');
    const controls = actions(primary);
    form.append(controls, status);
    const setWidth=expanded=>{const layout=panel.closest('.gc-result-assessment');expanded=expanded&&!layout?.classList.contains('is-grading-only');layout?.classList.toggle('is-wide-editor',expanded);wide.setAttribute('aria-label',expanded?'Узкий комментарий':'Широкий комментарий');wide.title=wide.getAttribute('aria-label');wide.querySelector('i').className='fas '+(expanded?'fa-compress':'fa-expand');resize();};
    const wide=iconButton('Широкий комментарий','fa-expand',()=>{value.wide=!value.wide;drafts.set(key,{...value});setWidth(value.wide);});
    const panel=node('section', {class:'gc-inline-grade', 'aria-label':'Оценка и комментарий'},node('header',{class:'gc-grade-heading'},node('h4',{class:'h6 mb-0'},'Оценка ученику'),wide),form);
    panel.addEventListener('keydown',event=>{if(event.key==='Enter'&&(event.ctrlKey||event.metaKey)&&!event.isComposing){event.preventDefault();save();}});
    panel.addEventListener('gc:grading-dispose',()=>richEditor?.destroy(),{once:true});
    requestAnimationFrame(()=>{if(!panel.isConnected)return;setWidth(!!value.wide);richEditor=commentEditor(feedback);});
    return panel;
}

export function authorshipPanel(record, onDismiss) {
    const integrity = record.integrity;
    const panel = node('div', {class:'gc-authorship-report'});
    if (!integrity) {panel.append(node('p', {class:'gc-help-text'}, 'Результат анализа пока отсутствует.')); return authorshipDialog(panel,record);}
    const plagiarism = integrity.plagiarism || {}, ai = integrity.ai || {};
    const matches = plagiarism.matches || [];
    const warning = !integrity.dismissed && (plagiarism.warning || ai.warning);
    const pending = integrity.state === 'running';
    const unavailable = integrity.state === 'error' || ai.state === 'error';
    panel.append(node('p', {class:'gc-authorship-state'+(warning ? ' is-warning' : '')},
        node('i', {class:'fas '+(warning ? 'fa-magnifying-glass' : 'fa-shield-halved'), 'aria-hidden':'true'}),
        integrity.dismissed ? 'Предупреждение снято преподавателем' : warning ? 'Есть результаты для разбора' : pending ? 'Анализ выполняется…' : unavailable ? 'Часть анализа недоступна' : 'Предупреждений нет'));
    const similarity = node('details', {open:!!plagiarism.warning}, node('summary', {}, 'Сходство исходников',
        plagiarism.warning && matches.length ? ` · до ${Math.max(...matches.map(m => m.percent))}%` : ''));
    similarity.append(node('p', {class:'gc-help-text'}, plagiarism.state !== 'done' ? 'Сравнение исходников не проводилось.'
        : plagiarism.warning ? `Найдены похожие работы: ${matches.length}.` : 'Значимых совпадений не найдено.'));
    if(plagiarism.method==='token_shingles_v1')similarity.append(node('p',{class:'gc-help-text'},'Система сравнивает последовательности элементов кода. Пробелы и переносы строк не влияют на результат. Ниже указан процент сходства с каждой найденной работой.'));
    for (const match of matches) similarity.append(node('p', {class:'gc-authorship-match'},
        match.review_url ? download(match.review_url, match.user_name || 'Открыть похожую работу') : node('span', {}, 'Работа вне доступных вам курсов'),
        ` · ${match.percent}%`));
    const assistance = node('details', {open:!!ai.warning}, node('summary', {}, 'Анализ помощи ИИ'));
    assistance.append(node('p', {class:'gc-help-text'}, integrity.state === 'running' ? 'Анализ выполняется…' : ai.state === 'error' ? 'Анализ временно недоступен.'
        : !['done','imported'].includes(ai.state) ? 'Анализ не проводился.' : ai.warning ? 'Есть признаки возможной помощи ИИ.' : 'Предупреждений об использовании ИИ нет.'));
    if (ai.reasons?.length) assistance.append(node('ul', {}, ai.reasons.map(reason => node('li', {}, reason))));
    panel.append(similarity, assistance, node('p', {class:'gc-help-text'}, 'Автоматический анализ помогает разобрать работу и сам по себе не меняет оценку.'));
    if (onDismiss && (warning || integrity.dismissed)) {
        const status = node('p', {class:'gc-help-text', role:'status'});
        const toggle = button(integrity.dismissed ? 'Вернуть предупреждение' : 'Снять предупреждение', async () => {
            toggle.disabled = true;
            try {await onDismiss(!integrity.dismissed);} catch(error) {status.textContent = error.message;}
            finally {toggle.disabled = false;}
        });
        toggle.classList.add('btn-sm'); panel.append(toggle, status);
    }
    return authorshipDialog(panel,record);
}

let reportId=0;
function authorshipDialog(panel,record){
    const integrity=record.integrity;
    const warning=integrity&&!integrity.dismissed&&(integrity.plagiarism?.warning||integrity.ai?.warning);
    const pending=integrity?.state==='running';
    const unavailable=!integrity||integrity.state==='error'||integrity.ai?.state==='error';
    const state=warning?'есть предупреждения':pending?'анализ выполняется':unavailable?'анализ недоступен':integrity.dismissed?'предупреждение снято':'предупреждений нет';
    const dialog=node('dialog',{class:'gc-dialog gc-authorship-dialog','aria-labelledby':'gc-authorship-report-'+(++reportId)});
    const trigger=iconButton('Антиплагиат и помощь ИИ: '+state,warning?'fa-triangle-exclamation':pending?'fa-hourglass-half':unavailable?'fa-circle-question':'fa-shield-halved',()=>dialog.showModal());
    trigger.dataset.authorshipTrigger='';trigger.setAttribute('aria-haspopup','dialog');trigger.classList.toggle('is-warning',!!warning);
    const close=iconButton('Закрыть отчёт','fa-xmark',()=>dialog.close());
    dialog.append(node('header',{},node('h3',{class:'h5 mb-0',id:dialog.getAttribute('aria-labelledby')},'Анализ авторства'),close),panel);
    dialog.addEventListener('close',()=>{if(trigger.isConnected)trigger.focus({preventScroll:true});});
    return node('div',{class:'gc-authorship-panel'},trigger,dialog);
}
