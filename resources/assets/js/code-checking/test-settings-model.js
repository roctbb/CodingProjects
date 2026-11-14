export const comparisons = {
    tokens:'По словам и числам', exact:'Точное', float:'Вещественные числа',
    tokens_ignore_cr:'По словам, без символов CR', exact_crlf:'Точное с нормализацией CRLF',
    float_binary64:'Вещественные числа (binary64)', strip_suffix:'Суффикс без крайних пробелов',
    lower_suffix:'Суффикс без учёта регистра', lower_exact:'Точное без учёта регистра',
    normalized_suffix:'Нормализованный суффикс', exact_suffix:'Точный суффикс', last_time:'Последнее время',
    last_integer:'Последнее целое число', integer_tokens:'Последовательность целых чисел',
    integer_tokens_ascii30:'Целые числа (совместимость ASCII 30)', keyword_integers_ascii30:'Ключевое слово и целые числа (ASCII 30)',
    word_integer_ascii30:'Слово и целое число (ASCII 30)', splitlines:'По строкам', word_lower:'Слово без учёта регистра',
    rational_absolute:'Рациональное число с абсолютной погрешностью', magic_mismatch:'Сохранённое специальное правило',
};

export function enableGroups(config, maximum) {
    config.scoring='groups';
    if (!config.groups?.length) config.groups=[{id:'main',name:'Основная группа',points:maximum}];
    for (const test of config.tests) if (!config.groups.some(group=>group.id===test.group)) test.group=config.groups[0].id;
}
export function addGroup(config) {
    let index=1;while (config.groups.some(group=>group.id===`group_${index}`)) index++;
    const group={id:`group_${index}`,name:`Группа ${index}`,points:1};config.groups.push(group);return group;
}
export function removeGroup(config, id) {
    config.groups=config.groups.filter(group=>group.id!==id);
    for (const test of config.tests) if (test.group===id) delete test.group;
}
export function testIssues(config, maximum) {
    const issues=[];
    if (!config.tests?.length) issues.push('Добавьте хотя бы один тест.');
    if (config.tests?.length>50) issues.push('Допустимо не более 50 тестов.');
    if (config.scoring==='groups') {
        const groups=config.groups || [], ids=groups.map(group=>group.id);
        if (!groups.length) issues.push('Добавьте хотя бы одну группу.');
        if (new Set(ids).size!==ids.length || groups.some(group=>!group.id || !group.name.trim())) issues.push('У групп должны быть разные ID и непустые названия.');
        if (groups.some(group=>!Number.isInteger(group.points) || group.points<1)) issues.push('Баллы каждой группы должны быть положительным целым числом.');
        if (groups.reduce((total,group)=>total+Number(group.points || 0),0)!==maximum) issues.push(`Сумма баллов групп должна равняться максимуму задания: ${maximum} XP.`);
        if (config.tests.some(test=>!ids.includes(test.group))) issues.push('Назначьте каждому тесту группу.');
        if (groups.some(group=>!config.tests.some(test=>test.group===group.id))) issues.push('В каждой группе должен быть хотя бы один тест.');
    }
    return issues;
}
