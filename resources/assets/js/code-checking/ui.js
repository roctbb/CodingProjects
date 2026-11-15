export function node(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    for (const [key, value] of Object.entries(attrs)) {
        if (key.startsWith('on')) el.addEventListener(key.slice(2).toLowerCase(), value);
        else if (key === 'class') el.className = value;
        else if (value !== null && value !== false && value !== undefined) {
            if (key in el) el[key] = value;
            else el.setAttribute(key, value === true ? '' : value);
        }
    }
    for (const child of children.flat(Infinity)) if (child !== null && child !== undefined) el.append(child instanceof Node ? child : document.createTextNode(String(child)));
    return el;
}
export const button = (label, action, tone = 'outline-secondary') => node('button', {type:'button', class:`btn btn-${tone}`, onclick:action}, label);
export const field = (label, input, hint) => node('label', {class:'gc-execution-field'}, node('span', {}, label), input, hint ? node('small', {class:'text-muted'}, hint) : null);
export const input = (value, change, attrs = {}) => node('input', {class:'form-control', value:value ?? '', oninput:event => change(event.target.value), ...attrs});
export const textarea = (value, change, attrs = {}) => node('textarea', {class:'form-control', rows:4, value:value ?? '', oninput:event => change(event.target.value), ...attrs});
export function select(value, options, change) {
    const el = node('select', {class:'form-select', onchange:event => change(event.target.value)}, Object.entries(options).map(([key,label]) => node('option',{value:key},label)));
    el.value = value; return el;
}
export const check = (label, value, change) => field(label, node('input', {type:'checkbox', class:'form-check-input', checked:!!value, onchange:event => change(event.target.checked)}));
export const actions = (...children) => node('div', {class:'gc-execution-actions'}, children);
export const modes = {files:'Загрузить файлы', code:'Вставить код', text:'Ответ текстом', project:'Несколько файлов кода', repository:'GitHub-репозиторий'};
export const phases = {queued:'В очереди', preparing:'Подготовка', compiling:'Компиляция', testing:'Проверка тестов', grading:'Оценка решения', publishing:'Сохранение результата', completed:'Проверка завершена', error:'Не удалось проверить', awaiting_review:'Ожидает преподавателя', imported_pending:'Историческая проверка'};
export const verdicts = {passed:'Пройден', wrong_answer:'Неверный ответ', runtime_error:'Ошибка выполнения', compilation_error:'Ошибка компиляции', time_limit_exceeded:'Превышено время', output_limit_exceeded:'Превышен вывод', error:'Ошибка проверки'};
export function download(url, label) {
    try { const target = new URL(url, location.origin); if (!['http:','https:'].includes(target.protocol)) return node('span',{},label);
        return node('a', {href:target.href, target:'_blank', rel:'noopener'}, label);
    } catch { return node('span',{},label); }
}
export async function request(url, method = 'GET', body, options = {}) {
    const multipart = body instanceof FormData;
    if (multipart && method === 'PUT') {body.set('_method','PUT'); method = 'POST';}
    const response = await fetch(url, {signal:options.signal, method, credentials:'same-origin', headers:{Accept:'application/json',
        'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || '', ...(!multipart && body ? {'Content-Type':'application/json'} : {})},
    ...(body ? {body:multipart ? body : JSON.stringify(body)} : {})});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const messages = {401:'Сессия завершилась. Войдите снова и повторите действие.',403:'Недостаточно прав для этого действия.',404:'Не удалось найти сохранённые данные. Повторите загрузку.',409:'Данные изменились. Повторите действие после проверки сохранённой версии.',413:'Файлы слишком велики.',429:'Лимит попыток исчерпан. Можно приобрести дополнительную попытку.'};
        const conflict=response.status===409 && typeof data.message==='string' && /[А-Яа-яЁё]/.test(data.message) ? data.message : null;
        const error = new Error(Object.values(data.errors || {}).flat().join('\n') || conflict || messages[response.status] || data.message || 'Не удалось выполнить запрос. Повторите попытку.');
        error.status = response.status; throw error;
    }
    return data;
}
