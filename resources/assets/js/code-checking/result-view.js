import {node,button,download,select} from './ui';

const terminal=['completed','error','awaiting_review','imported_pending'];

export function resultVerdict(record) {
    if(record.kind==='legacy_attempt')return record.awarded_xp===null
        ? {tone:'review',icon:'fa-clock',title:'Ожидает оценки преподавателя'}
        : {tone:'review',icon:'fa-file-circle-check',title:'Сдача оценена'};
    if(record.state==='error')return {tone:'failed',icon:'fa-triangle-exclamation',title:'Проверка не завершилась'};
    if(record.state==='awaiting_review')return {tone:'review',icon:'fa-clock',title:'Ожидает оценки преподавателя'};
    if(record.state==='imported_pending')return {tone:'review',icon:'fa-clock-rotate-left',title:'Историческая сдача'};
    if(!terminal.includes(record.state))return {tone:'running',icon:'fa-gear',title:record.state==='queued'?'В очереди на проверку':'Решение проверяется'};
    if(record.report?.total>0){
        if(record.report.passed===record.report.total)return {tone:'passed',icon:'fa-circle-check',title:'Все тесты пройдены'};
        const compilation=record.report.tests?.some(test=>test.verdict==='compilation_error');
        return {tone:record.report.passed>0&&!compilation?'partial':'failed',icon:'fa-circle-xmark',title:compilation?'Ошибка компиляции':record.report.passed>0?'Часть тестов не пройдена':'Тесты не пройдены'};
    }
    if(record.points!==null && record.points!==undefined){
        if(record.maximum_points && record.points>=record.maximum_points)return {tone:'passed',icon:'fa-circle-check',title:'Решение принято'};
        return {tone:'partial',icon:'fa-circle-half-stroke',title:record.points>0?'Решение принято частично':'Решение требует доработки'};
    }
    return {tone:'review',icon:'fa-file-circle-check',title:'Результат проверки сохранён'};
}

export function resultHeader(record,{sourceOnly=false}={}) {
    const verdict=resultVerdict(record);
    const title=node('h3',{class:'gc-result-verdict__title'},verdict.title);
    const details=node('div',{class:'gc-result-verdict__details'},title,
        sourceOnly?node('p',{class:'text-muted small mb-0'},`Сохранённая версия решения · ${new Date(record.created_at).toLocaleString('ru-RU')}`):null);
    const header=node('header',{class:`gc-result-verdict is-${verdict.tone}`,'aria-label':'Итог проверки'},
        node('i',{class:`fas ${verdict.icon} gc-result-verdict__icon`,'aria-hidden':'true'}),details);
    if(record.points!==null && record.points!==undefined)header.append(node('div',{class:'gc-result-score'},
        node('strong',{},String(record.points)),node('span',{},sourceOnly?' баллов':` / ${record.maximum_points} баллов`)));
    else if(record.kind==='legacy_attempt' && record.awarded_xp!==null)header.append(node('div',{class:'gc-result-score'},node('strong',{},String(record.awarded_xp)),node('span',{},' XP')));
    return {header,terminal:terminal.includes(record.state)};
}

export function submittedWork(record) {
    const files=record.files || [];
    const section=node('section',{class:'gc-submitted-work','aria-label':'Отправленная работа'});
    const textFiles=files.filter(file=>file.source!==undefined);
    const initial=textFiles.find(file=>/\.(py|cpp|cc|c|h|hpp|js|ts|java|cs|php|swift|go|rs|html|css|sql)$/i.test(file.name)) || textFiles[0];
    if(initial){
        const viewer=node('div',{class:'gc-submitted-code'});
        const code=node('code');
        const source=node('pre',{class:'gc-submitted-code__source',tabIndex:0},code);
        const fileControls=node('div',{class:'gc-submitted-code__toolbar'});
        const controls=node('div',{class:'gc-submitted-code__actions'});
        let selected=initial;
        const render=file=>{
            selected=file;code.textContent=file.source || '(пустой файл)';
            const ext=file.name.split('.').pop().toLowerCase();
            const language=({py:'python',cc:'cpp',c:'cpp',h:'cpp',hpp:'cpp',js:'javascript',html:'xml',cs:'csharp'})[ext] || ext;
            code.className=globalThis.hljs?.getLanguage(language)?'language-'+language:'language-plaintext';
            delete code.dataset.highlighted;
            if(globalThis.hljs?.getLanguage(language))globalThis.hljs.highlightElement(code);
            source.setAttribute('aria-label','Код решения: '+file.name);
            controls.replaceChildren(download(file.download_url,'Скачать'),button('Копировать',async event=>{
                const target=event.currentTarget;
                try{await navigator.clipboard.writeText(selected.source || '');target.textContent='Скопировано';}
                catch{source.focus();const selection=getSelection(),range=document.createRange();range.selectNodeContents(code);selection.removeAllRanges();selection.addRange(range);}
            }));
            controls.querySelector('button').classList.add('btn-sm');
        };
        if(textFiles.length>1){
            const picker=select(initial.name,Object.fromEntries(textFiles.map(file=>[file.name,file.name])),name=>render(textFiles.find(file=>file.name===name)));
            picker.setAttribute('aria-label','Файл решения');fileControls.append(picker);
        }else fileControls.append(node('strong',{},initial.original_name || initial.name));
        fileControls.append(controls);viewer.append(fileControls,source);section.append(viewer);render(initial);
    }
    if(record.text)section.append(node('h4',{class:'h6 mt-3'},record.mode==='text'?'Ответ':'Пояснение к решению'),node('pre',{class:'gc-execution-output'},record.text));
    const otherFiles=files.filter(file=>file.source===undefined);
    if(otherFiles.length){
        const cards=node('div',{class:'gc-submitted-materials'});
        for(const file of otherFiles){
            const image=/\.(png|jpe?g|gif|webp)$/i.test(file.name);
            const card=node('article',{class:'gc-submitted-file'},node('div',{class:'gc-submitted-file__heading'},
                node('i',{class:`fas ${image?'fa-image':'fa-file-arrow-down'}`,'aria-hidden':'true'}),
                node('div',{},node('strong',{},file.original_name || file.name),node('small',{class:'d-block text-muted'},`${file.name.split('.').pop().toUpperCase()} · `,file.bytes>=1048576?`${(file.bytes/1048576).toFixed(1)} МБ`:`${Math.max(1,Math.round(file.bytes/1024))} КБ`)),download(file.download_url,'Скачать')));
            if(image){const url=new URL(file.download_url,location.origin);url.searchParams.set('proxy','1');card.append(node('img',{src:url.href,alt:file.original_name || file.name,class:'gc-execution-image',loading:'lazy'}));}
            cards.append(card);
        }
        section.append(cards);
    }
    if(!files.length && !record.text)section.append(node('p',{class:'text-muted'},'Отдельная версия отправленной работы не сохранилась.'));
    return section;
}

export function resultActions() {
    const menu=node('div',{class:'dropdown-menu dropdown-menu-end'});
    const toggle=button('Действия',()=>{});toggle.classList.add('btn-sm');toggle.setAttribute('data-bs-toggle','dropdown');toggle.setAttribute('aria-expanded','false');
    const root=node('div',{class:'dropdown gc-result-actions'},toggle,menu);
    return {root,add:action=>{action.className='dropdown-item';menu.append(action);}};
}
