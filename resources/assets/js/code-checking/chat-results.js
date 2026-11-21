import {node, openDialog} from './ui';
import {markdownContent} from './feedback';

const verdictLabels = {met:'Зачтён', partial:'Частично', unmet:'Не зачтён'};

export function criterionTable(criteria, detailed = false) {
    const headers = ['Критерий', 'Результат', 'Баллы'];
    const body = node('tbody');
    for (const row of criteria) {
        const scored = row.maximum != null && row.points != null;
        const verdict = scored ? (row.points === row.maximum ? 'met' : row.points > 0 ? 'partial' : 'unmet') : row.status;
        const title = node('th', {scope:'row'}, row.title);
        if (detailed) title.append(markdownContent(row.comment));
        body.append(node('tr', {}, title, node('td', {class:'gc-criterion-state', 'data-state':verdict}, verdictLabels[verdict]),
            node('td', {class:'gc-criterion-points'}, scored ? row.points+' / '+row.maximum : '—')));
    }
    return node('table', {class:'gc-chat-rubric', 'aria-label':'Результаты по критериям'},
        node('thead', {class:detailed ? '' : 'visually-hidden'}, node('tr', {}, headers.map(h=>node('th', {scope:'col'}, h)))), body);
}

export function showCriteria(event) {
    const body = node('div', {class:'gc-chat-rubric-detail'},
        node('p', {class:'gc-chat-score'}, 'Итого ', node('strong', {}, event.points+' из '+event.maximum)),
        event.text ? markdownContent(event.text) : null, criterionTable(event.criteria, true));
    openDialog(event.stronger ? 'Расширенная проверка по критериям' : 'Проверка по критериям', body);
}

export function testSummary(event) {
    const passed = Number(event.passed), total = Number(event.total);
    const fill = node('span');
    fill.style.width = Math.min(100, Math.max(0, passed / total * 100))+'%';
    return node('div', {class:'gc-chat-test-summary'}, node('span', {}, 'Пройдено тестов: '+passed+' из '+total),
        node('div', {class:'gc-chat-test-meter', 'aria-hidden':'true'}, fill));
}
