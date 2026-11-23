import {node,button,iconButton,download,request} from './ui';
import {combinedCheck, usesAi} from './authoring-settings';

const terminal=['completed','error','awaiting_review','imported_pending'];

export function resultVerdict(record) {
    if(record.kind==='legacy_attempt')return record.awarded_xp===null
        ? {tone:'review',icon:'fa-clock',title:'Ожидает оценки преподавателя'}
        : {tone:'review',icon:'fa-file-circle-check',title:'Сдача оценена'};
    if(record.state==='error')return {tone:'failed',icon:'fa-triangle-exclamation',title:'Проверка не завершилась'};
    if(record.state==='awaiting_review')return {tone:'review',icon:'fa-clock',title:'Ожидает оценки преподавателя'};
    if(record.state==='imported_pending')return {tone:'review',icon:'fa-clock-rotate-left',title:'Историческая сдача'};
    if(!terminal.includes(record.state))return {tone:'running',icon:'fa-gear',title:record.state==='queued'?'В очереди на проверку':'Решение проверяется'};
    if(record.check_type !== combinedCheck && record.report?.total>0){
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

export function resultHeader(record,{sourceOnly=false,showScore=true,showSourceDate=true,reviewing=false,compact=false}={}) {
    const verdict=resultVerdict(record);
    const title=node('h3',{class:'gc-result-verdict__title'},verdict.title);
    const details=node('div',{class:'gc-result-verdict__details'},title,
        sourceOnly && showSourceDate?node('p',{class:'text-muted small mb-0'},`Сохранённая версия решения · ${new Date(record.created_at).toLocaleString('ru-RU')}`):null);
    const raw=record.award_breakdown?.evaluated_points ?? record.evaluated_points ?? record.points;
    const maximum=record.maximum_points ?? record.grading_maximum_points;
    const credited=!sourceOnly && record.awarded_xp!==null && record.awarded_xp!==undefined;
    if(raw!==null && raw!==undefined && record.state!=='error' && !(compact && showScore && !credited && raw===record.points))details.append(node('p',{class:'gc-help-text mb-0', 'data-score-repeated':credited && Number(raw) === Number(record.awarded_xp) ? '' : null},`Оценка за работу: ${raw}${maximum?' из '+maximum:''} баллов${usesAi(record.check_type)?' · проверка ИИ':''}`));
    const header=node('header',{class:`gc-result-verdict is-${verdict.tone}`,'aria-label':'Итог проверки'},
        node('i',{class:`fas ${verdict.icon} gc-result-verdict__icon`,'aria-hidden':'true'}),details);
    header.hidden=reviewing && (record.state==='awaiting_review' || record.kind==='legacy_attempt' && record.awarded_xp===null);
    if(showScore && record.state!=='error' && (credited || record.points!==null && record.points!==undefined))header.append(node('div',{class:'gc-result-score'},
        node('strong',{},String(credited?record.awarded_xp:record.points)),node('span',{},record.kind==='legacy_attempt'?' XP':(compact && !credited ? (maximum!=null?' из '+maximum:'')+' баллов' : ` / ${maximum} ${credited?'XP':'баллов'}`)),credited?node('small',{},'Зачтено за эту сдачу'):null));
    return {header,terminal:terminal.includes(record.state)};
}

export function submittedWork(record) {
    const files=record.files || [];
    const section=node('details',{class:'gc-submitted-work',open:true,'aria-label':'Отправленная работа'});
    const heading=node('summary',{},node('i',{class:'fas fa-chevron-down','aria-hidden':'true'}),node('span',{class:'gc-submitted-work__title'},files.length===1?files[0].name:!files.length && record.text?'Текст ответа':'Исходная работа'));
    const tools=node('div',{class:'gc-submitted-work__tools'});heading.append(tools);
    const bundle=files.length>1 && record.bundle_url?download(record.bundle_url,'ZIP'):null;
    if(bundle){bundle.prepend(node('i',{class:'fas fa-file-zipper','aria-hidden':'true'}),' ');bundle.className='btn btn-outline-secondary';bundle.title='Скачать все файлы ZIP-архивом';bundle.setAttribute('aria-label',bundle.title);heading.append(bundle);}
    section.append(heading);
    if(files.length){
        const tabs=node('div',{class:'gc-source-tabs',role:'tablist','aria-label':'Файлы решения'});
        const preview=node('div',{class:'gc-submitted-preview',role:files.length>1?'tabpanel':'region'});
        let selected,sequence=0;
        const renderSource=(file,source,{extraAction=null,showFilename=true}={})=>{
            const code=node('code',{},source || '(пустой файл)'),pre=node('pre',{class:'gc-submitted-code__source',tabIndex:0,'aria-label':'Код решения: '+file.name},code);
            const ext=file.name.split('.').pop().toLowerCase(),language=({py:'python',cc:'cpp',c:'cpp',h:'cpp',hpp:'cpp',js:'javascript',html:'xml',cs:'csharp'})[ext] || ext;
            code.className=globalThis.hljs?.getLanguage(language)?'language-'+language:'language-plaintext';
            if(globalThis.hljs?.getLanguage(language))globalThis.hljs.highlightElement(code);
            const copy=iconButton('Копировать код','fa-copy',async()=>{try{await navigator.clipboard.writeText(source);copy.setAttribute('aria-label','Скопировано');copy.title='Скопировано';}catch{pre.focus();const selection=getSelection(),range=document.createRange();range.selectNodeContents(code);selection.removeAllRanges();selection.addRange(range);}});
            const link=file.download_url?download(file.download_url,''):null;if(link){link.append(node('i',{class:'fas fa-download','aria-hidden':'true'}));link.setAttribute('aria-label','Скачать исходную работу');link.title='Скачать исходную работу';link.className='btn btn-outline-secondary gc-execution-icon-button';}
            tools.replaceChildren(...[link,extraAction,copy].filter(Boolean));
            return node('div',{class:'gc-submitted-code'},pre);
        };
        const show=async file=>{
            selected=file;const version=++sequence;tools.replaceChildren();
            tabs.querySelectorAll('[role="tab"]').forEach(tab=>{const active=tab.dataset.file===file.name;tab.classList.toggle('is-active',active);tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});
            preview.setAttribute('aria-label',file.name);
            if(/\.ipynb$/i.test(file.name)){
                preview.replaceChildren(node('p',{class:'gc-help-text',role:'status'},'Открываем тетрадку…'));
                try{
                    const {notebookPreview}=await import('./submission-notebook');
                    if(version!==sequence)return;
                    const notebook=await notebookPreview(file);if(version!==sequence)return;
                    const toolbar=notebook.querySelector(':scope > .gc-submitted-code__toolbar'),name=toolbar?.querySelector('.gc-submitted-notebook__name');
                    name?.querySelector('strong')?.remove();
                    if(name)notebook.prepend(node('p',{class:'gc-notebook-summary gc-help-text'},name.textContent));
                    if(toolbar){tools.replaceChildren(...[...toolbar.children].filter(child=>child!==name));toolbar.remove();}
                    preview.replaceChildren(notebook);
                }catch(error){if(version===sequence)preview.replaceChildren(node('p',{class:'gc-help-text'},'Не удалось показать тетрадку. '+error.message),download(file.download_url,'Скачать исходную тетрадку'),button('Повторить загрузку тетрадки',()=>show(file)));}
                return;
            }
            const source = file.source ?? (file.is_answer ? record.text : undefined);
            if(source != null){preview.replaceChildren(renderSource(file,source,{showFilename:files.length===1}));return;}
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
            const extension=file.name.split('.').pop().toLowerCase(),typeName=({xlsx:'Таблица Excel',xls:'Таблица Excel',pdf:'Документ PDF',docx:'Документ Word'})[extension] || 'Исходный файл';
            preview.replaceChildren(node('div',{class:'gc-submitted-file__heading'},node('i',{class:'fas fa-file-arrow-down','aria-hidden':'true'}),node('div',{},node('strong',{},typeName),node('small',{class:'d-block text-muted'},`${extension.toUpperCase()} · `,file.bytes>=1048576?`${(file.bytes/1048576).toFixed(1)} МБ`:`${Math.max(1,Math.round(file.bytes/1024))} КБ`)),download(file.download_url,'Скачать')));
        };
        for(const file of files){const tab=button(file.name,()=>show(file));tab.className='gc-source-tab';tab.setAttribute('role','tab');tab.dataset.file=file.name;tabs.append(tab);}
        tabs.addEventListener('keydown',event=>{if(!['ArrowLeft','ArrowRight'].includes(event.key))return;event.preventDefault();const index=files.indexOf(selected),next=files[(index+(event.key==='ArrowRight'?1:files.length-1))%files.length];show(next);tabs.querySelector('[aria-selected="true"]')?.focus();});
        if(files.length>1){heading.querySelector('.gc-submitted-work__title').remove();tools.before(tabs);}section.append(preview);show(files.find(file=>file.source!==undefined) || files[0]);
    }
    const duplicatesAnswer = record.mode === 'text' && files.some(file =>
        file.is_answer || (file.name === 'answer.txt' && typeof file.source === 'string'
            && file.source.replace(/\r\n/g, '\n') === record.text?.replace(/\r\n/g, '\n')));
    if(record.text && !duplicatesAnswer && (record.mode==='text' || record.mode==='files')){
        const answer=node('pre',{class:'gc-submitted-answer__text',tabIndex:0,'aria-label':'Текст ответа ученика'},record.text);
        section.append(node('div',{class:'gc-submitted-answer'},files.length?node('h4',{class:'h6'},'Комментарий к работе'):null,answer));
        if(!files.length)tools.append(iconButton('Копировать ответ','fa-copy',async()=>{
            try{await navigator.clipboard.writeText(record.text);}catch{answer.focus();const range=document.createRange();range.selectNodeContents(answer);const selection=getSelection();selection.removeAllRanges();selection.addRange(range);}
        }));
    }
    if(!files.length && !record.text)section.append(node('p',{class:'text-muted'},'Отправленная работа не сохранилась отдельно.'));
    return section;
}

export function resultActions() {
    const menu=node('div',{class:'dropdown-menu dropdown-menu-end'});
    const toggle=iconButton('Действия','fa-ellipsis',()=>{});toggle.setAttribute('data-bs-toggle','dropdown');toggle.setAttribute('aria-expanded','false');
    const root=node('div',{class:'dropdown gc-result-actions'},toggle,menu);
    return {root,add:action=>{action.className='dropdown-item';menu.append(action);}};
}
