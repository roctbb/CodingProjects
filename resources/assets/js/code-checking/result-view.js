import {node,button,download,request} from './ui';

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

export function resultHeader(record,{sourceOnly=false,showScore=true,showSourceDate=true}={}) {
    const verdict=resultVerdict(record);
    const title=node('h3',{class:'gc-result-verdict__title'},verdict.title);
    const details=node('div',{class:'gc-result-verdict__details'},title,
        sourceOnly && showSourceDate?node('p',{class:'text-muted small mb-0'},`Сохранённая версия решения · ${new Date(record.created_at).toLocaleString('ru-RU')}`):null);
    if(!sourceOnly && record.kind!=='legacy_attempt' && record.awarded_xp!==null && record.awarded_xp!==undefined)
        details.append(node('p',{class:'gc-help-text mb-0'},`Зачтено: ${record.awarded_xp} XP`));
    const header=node('header',{class:`gc-result-verdict is-${verdict.tone}`,'aria-label':'Итог проверки'},
        node('i',{class:`fas ${verdict.icon} gc-result-verdict__icon`,'aria-hidden':'true'}),details);
    if(showScore && record.state!=='error' && record.kind!=='legacy_attempt' && record.points!==null && record.points!==undefined)header.append(node('div',{class:'gc-result-score'},
        node('strong',{},String(record.points)),node('span',{},sourceOnly?' баллов':` / ${record.maximum_points} баллов`)));
    else if(showScore && record.kind==='legacy_attempt' && record.awarded_xp!==null)header.append(node('div',{class:'gc-result-score'},node('strong',{},String(record.awarded_xp)),node('span',{},' XP')));
    return {header,terminal:terminal.includes(record.state)};
}

export function submittedWork(record) {
    const files=record.files || [];
    const section=node('section',{class:'gc-submitted-work','aria-label':'Отправленная работа'});
    if(files.length){
        const tabs=node('div',{class:'gc-source-tabs',role:'tablist','aria-label':'Файлы решения'});
        const preview=node('div',{class:'gc-submitted-preview',role:files.length>1?'tabpanel':'region'});
        let selected,sequence=0;
        const renderSource=(file,source,{extraAction=null,showFilename=true}={})=>{
            const code=node('code',{},source || '(пустой файл)'),pre=node('pre',{class:'gc-submitted-code__source',tabIndex:0,'aria-label':'Код решения: '+file.name},code);
            const ext=file.name.split('.').pop().toLowerCase(),language=({py:'python',cc:'cpp',c:'cpp',h:'cpp',hpp:'cpp',js:'javascript',html:'xml',cs:'csharp'})[ext] || ext;
            code.className=globalThis.hljs?.getLanguage(language)?'language-'+language:'language-plaintext';
            if(globalThis.hljs?.getLanguage(language))globalThis.hljs.highlightElement(code);
            const copy=button('Копировать',async()=>{try{await navigator.clipboard.writeText(source);copy.textContent='Скопировано';}catch{pre.focus();const selection=getSelection(),range=document.createRange();range.selectNodeContents(code);selection.removeAllRanges();selection.addRange(range);}});copy.classList.add('btn-sm');
            return node('div',{class:'gc-submitted-code'},node('div',{class:'gc-submitted-code__toolbar'},showFilename?node('strong',{},file.name):null,node('div',{class:'gc-submitted-code__actions'+(showFilename?'':' ms-auto')},file.download_url?download(file.download_url,file.download_label || 'Скачать'):null,extraAction,copy)),pre);
        };
        const show=async file=>{
            selected=file;const version=++sequence;
            tabs.querySelectorAll('[role="tab"]').forEach(tab=>{const active=tab.dataset.file===file.name;tab.classList.toggle('is-active',active);tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});
            preview.setAttribute('aria-label',file.name);
            if(file.source!==undefined){preview.replaceChildren(renderSource(file,file.source,{showFilename:files.length===1}));return;}
            if(/\.(png|jpe?g|gif|webp)$/i.test(file.name)){
                const url=new URL(file.download_url,location.origin);url.searchParams.set('proxy','1');
                preview.replaceChildren(node('div',{class:'gc-submitted-file__heading'},node('strong',{},file.original_name || file.name),download(file.download_url,'Скачать')),node('img',{src:url.href,alt:file.original_name || file.name,class:'gc-execution-image',loading:'lazy'}));return;
            }
            if(file.archive_url){
                preview.replaceChildren(node('p',{class:'text-muted',role:'status'},'Читаем список файлов архива…'));
                try{
                    const archive=await request(file.archive_url);if(version!==sequence)return;
                    const entries=archive.entries || [],entryCache=new Map();let entrySequence=0;
                    const archiveTabs=node('div',{class:'gc-source-tabs',role:'tablist','aria-label':'Файлы в архиве'}),archiveBody=node('div',{role:entries.length>1?'tabpanel':'region'});
                    const original=download(archive.download_url || file.download_url,'');original.setAttribute('aria-label','Скачать исходный архив');original.title='Скачать исходный архив';original.append(node('i',{class:'fas fa-download','aria-hidden':'true'}));
                    const singleEntry=entries.length===1;
                    const showEntry=async entry=>{
                        const entryVersion=++entrySequence;archiveTabs.querySelectorAll('[role="tab"]').forEach(tab=>{const active=tab.dataset.entry===String(entry.id);tab.classList.toggle('is-active',active);tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});
                        archiveBody.setAttribute('aria-label',entry.name || entry.path);
                        if(!entry.previewable){archiveBody.replaceChildren(node('strong',{},entry.name || entry.path),node('p',{class:'text-muted'},'Файл доступен для скачивания.'),download(entry.download_url || archive.download_url || file.download_url,entry.download_kind==='file'?'Скачать файл':'Скачать архив'),...(singleEntry && entry.download_kind==='file'?[original]:[]));return;}
                        archiveBody.replaceChildren(node('p',{class:'text-muted',role:'status'},'Загружаем файл…'),...(singleEntry?[original]:[]));
                        try{const value=entryCache.get(entry.id) || await request(entry.source_url);entryCache.set(entry.id,value);if(version!==sequence || entryVersion!==entrySequence)return;archiveBody.replaceChildren(renderSource({name:entry.name || entry.path,download_url:entry.download_kind==='file'?entry.download_url:null,download_label:'Скачать файл'},value.source ?? '',{showFilename:singleEntry,extraAction:singleEntry?original:null}));}
                        catch(e){if(version===sequence && entryVersion===entrySequence)archiveBody.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить',()=>showEntry(entry)),...(singleEntry?[original]:[]));}
                    };
                    archiveTabs.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight'].includes(event.key)||event.target.getAttribute('role')!=='tab')return;event.preventDefault();const current=entries.findIndex(entry=>String(entry.id)===archiveTabs.querySelector('[aria-selected="true"]')?.dataset.entry),next=entries[(current+(event.key==='ArrowRight'?1:entries.length-1))%entries.length];showEntry(next);archiveTabs.querySelector('[aria-selected="true"]')?.focus();});
                    entries.forEach(entry=>{const tab=button(entry.name || entry.path,()=>showEntry(entry));tab.className='gc-source-tab';tab.setAttribute('role','tab');tab.dataset.entry=entry.id;archiveTabs.append(tab);});
                    if(entries.length>1){archiveTabs.append(original);preview.replaceChildren(archiveTabs,archiveBody);}
                    else if(singleEntry)preview.replaceChildren(archiveBody);
                    else preview.replaceChildren(node('div',{class:'gc-submitted-code__toolbar'},node('strong',{},file.original_name || file.name),original),archiveBody);
                    if(entries[0])showEntry(entries[0]);else archiveBody.textContent='В архиве нет файлов для просмотра.';
                }catch(e){if(version===sequence)preview.replaceChildren(node('p',{class:'text-danger'},e.message),button('Повторить',()=>show(file)));}
                return;
            }
            preview.replaceChildren(node('div',{class:'gc-submitted-file__heading'},node('i',{class:'fas fa-file-arrow-down','aria-hidden':'true'}),node('div',{},node('strong',{},file.original_name || file.name),node('small',{class:'d-block text-muted'},`${file.name.split('.').pop().toUpperCase()} · `,file.bytes>=1048576?`${(file.bytes/1048576).toFixed(1)} МБ`:`${Math.max(1,Math.round(file.bytes/1024))} КБ`)),download(file.download_url,'Скачать')));
        };
        for(const file of files){const tab=button(file.name,()=>show(file));tab.className='gc-source-tab';tab.setAttribute('role','tab');tab.dataset.file=file.name;tabs.append(tab);}
        tabs.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();const index=files.indexOf(selected),next=files[(index+(event.key==='ArrowRight'?1:files.length-1))%files.length];show(next);tabs.querySelector('[aria-selected="true"]')?.focus();});
        if(files.length>1)section.append(tabs);section.append(preview);show(files.find(file=>file.source!==undefined) || files[0]);
    }
    if(record.text && (record.mode==='text' || record.mode==='files'))section.append(node('h4',{class:'h6 mt-3'},'Ответ'),node('pre',{class:'gc-execution-output'},record.text));
    if(!files.length && !record.text)section.append(node('p',{class:'text-muted'},'Отправленная работа не сохранилась отдельно.'));
    return section;
}

export function resultActions() {
    const menu=node('div',{class:'dropdown-menu dropdown-menu-end'});
    const toggle=button('Действия',()=>{});toggle.classList.add('btn-sm');toggle.setAttribute('data-bs-toggle','dropdown');toggle.setAttribute('aria-expanded','false');
    const root=node('div',{class:'dropdown gc-result-actions'},toggle,menu);
    return {root,add:action=>{action.className='dropdown-item';menu.append(action);}};
}
