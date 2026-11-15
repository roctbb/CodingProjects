import {node, button, field, input, textarea, actions, download} from './ui';

export function gradingPanel({key, comment = '', mark = '', maximum, hint, onSave, onNext, onSkip, drafts}) {
    let value = drafts.get(key) || {mark:mark ?? '', comment};
    const status = node('p', {class:'gc-help-text', role:'status'});
    const remember = () => {drafts.set(key, {...value}); status.textContent = 'Изменения ещё не отправлены ученику';};
    const grade = input(value.mark, v => {value.mark = v; remember();}, {type:'number', min:0, max:maximum});
    const feedback = textarea(value.comment, v => {value.comment = v; remember();}, {rows:5, placeholder:'Что получилось и что стоит исправить'});
    const form = node('div', {class:'gc-inline-grade__editor'},
        field('Баллы, из '+maximum, grade), field('Комментарий ученику', feedback),
        node('p', {class:'gc-help-text'}, hint || 'Правила срока применяются к этой сдаче при сохранении.'));
    const save = next => async () => {
        if (value.mark === '' || !Number.isFinite(Number(value.mark)) || Number(value.mark) < 0 || Number(value.mark) > maximum) {
            status.textContent = `Укажите баллы от 0 до ${maximum}.`; grade.focus(); return;
        }
        form.setAttribute('inert',''); status.textContent = 'Сохраняем…';
        try {
            const saved = await onSave(Number(value.mark), value.comment);
            drafts.delete(key);
            if (saved !== false && next) onNext?.();
        } catch (error) {status.textContent = error.message;}
        finally {form.removeAttribute('inert');}
    };
    const primary = button(onNext ? 'Сохранить и далее' : 'Сохранить оценку', save(!!onNext), 'primary');
    const controls = actions(primary);
    if (onNext) controls.append(button('Сохранить', save(false)));
    if (onSkip) controls.append(button('Пропустить работу', async () => {
        form.setAttribute('inert','');
        try {await onSkip();} catch(error) {status.textContent = error.message;}
        finally {form.removeAttribute('inert');}
    }));
    const reset = button('Отменить изменения', () => {
        value = {mark:mark ?? '', comment}; grade.value = value.mark; feedback.value = comment;
        drafts.delete(key); status.textContent = '';
    });
    reset.classList.add('btn-sm');
    form.append(controls, reset, status);
    return node('section', {class:'gc-inline-grade', 'aria-label':'Оценка и комментарий'},
        node('h4', {class:'h6'}, 'Оценка и обратная связь'), form);
}

export function authorshipPanel(record, onDismiss) {
    const integrity = record.integrity;
    const panel = node('section', {class:'gc-authorship-panel', 'aria-label':'Антиплагиат и анализ авторства'}, node('h4', {class:'h6'}, 'Анализ авторства'));
    if (!integrity) {panel.append(node('p', {class:'gc-help-text'}, 'Результат анализа пока отсутствует.')); return panel;}
    const plagiarism = integrity.plagiarism || {}, ai = integrity.ai || {};
    const matches = plagiarism.matches || [];
    const warning = !integrity.dismissed && (plagiarism.warning || ai.warning);
    const pending = integrity.state === 'running';
    const unavailable = integrity.state === 'error' || ai.state === 'error';
    panel.append(node('p', {class:'gc-authorship-state'+(warning ? ' is-warning' : '')},
        node('i', {class:'fas '+(warning ? 'fa-magnifying-glass' : 'fa-shield-halved'), 'aria-hidden':'true'}),
        integrity.dismissed ? 'Предупреждение снято преподавателем' : warning ? 'Есть результаты для разбора' : pending ? 'Анализ выполняется…' : unavailable ? 'Часть анализа недоступна' : 'Предупреждений нет'));
    const similarity = node('details', {}, node('summary', {}, 'Сходство исходников',
        plagiarism.warning && matches.length ? ` · до ${Math.max(...matches.map(m => m.percent))}%` : ''));
    similarity.append(node('p', {class:'gc-help-text'}, plagiarism.state !== 'done' ? 'Сравнение исходников не проводилось.'
        : plagiarism.warning ? `Найдены похожие работы: ${matches.length}.` : 'Значимых совпадений не найдено.'));
    for (const match of matches) similarity.append(node('p', {class:'gc-authorship-match'},
        match.review_url ? download(match.review_url, match.user_name || 'Открыть похожую работу') : node('span', {}, 'Работа вне доступных вам курсов'),
        ` · ${match.percent}%`));
    const assistance = node('details', {}, node('summary', {}, 'Анализ помощи ИИ'));
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
    return panel;
}
