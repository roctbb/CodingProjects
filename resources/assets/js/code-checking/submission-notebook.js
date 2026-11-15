import {node,button,download} from './ui';
import {notebookData} from './notebook-data';

export async function notebookPreview(file){
    if(file.bytes>8388608)throw new Error('Тетрадка больше 8 МБ. Скачайте исходный файл для просмотра в Jupyter.');
    let source=file.source;
    if(source===undefined){
        const url=new URL(file.download_url,location.origin);url.searchParams.set('proxy','1');
        const response=await fetch(url,{credentials:'same-origin'});
        if(!response.ok)throw new Error('Не удалось загрузить тетрадку. Повторите попытку или скачайте файл.');
        source=await response.text();
    }
    if(new Blob([source]).size>8388608)throw new Error('Тетрадка больше 8 МБ. Скачайте исходный файл для просмотра в Jupyter.');
    const notebook=notebookData(source),renderer=(await import('../nbv')).default;
    const body=node('div',{class:'notebook-container markdown gc-submitted-notebook__body'});
    const raw=node('pre',{class:'gc-submitted-code__source',hidden:true,tabIndex:0,'aria-label':'Исходный JSON тетрадки'},node('code',{},source));
    const preview=button('Тетрадка',()=>setMode(false)),json=button('Исходный JSON',()=>setMode(true));
    const modes=node('div',{class:'gc-submitted-notebook__modes',role:'group','aria-label':'Просмотр тетрадки'},preview,json);
    const view=node('section',{class:'gc-submitted-notebook','aria-label':'Тетрадка Jupyter'},
        node('div',{class:'gc-submitted-code__toolbar'},node('div',{class:'gc-submitted-notebook__name'},
            node('strong',{},node('i',{class:'fas fa-book-open','aria-hidden':'true'}),' ',file.original_name||file.name),
            node('span',{class:'gc-help-text'},`Ячеек: ${notebook.cells.length} · с кодом: ${notebook.cells.filter(cell=>cell.cell_type==='code').length}`)),
            modes,download(file.download_url,'Скачать')),body,raw);
    function setMode(showRaw){body.hidden=showRaw;raw.hidden=!showRaw;preview.setAttribute('aria-pressed',String(!showRaw));json.setAttribute('aria-pressed',String(showRaw));}
    let position=0;
    const more=button('Показать ещё ячейки',append);
    function append(){
        const batch=notebook.cells.slice(position,position+50),host=node('div');position+=batch.length;
        renderer.render({...notebook,cells:batch},host);body.append(host);more.hidden=position>=notebook.cells.length;
        if(!more.hidden)body.append(more);
        const math=globalThis.MathJax;
        if(math?.startup)requestAnimationFrame(()=>{if(host.isConnected)math.startup.promise.then(()=>math.typesetPromise?.([host])).catch(()=>{});});
    }
    setMode(false);append();return view;
}
