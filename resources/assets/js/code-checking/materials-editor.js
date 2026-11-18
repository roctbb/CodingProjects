import {node, button, field, input, select, check, actions, request, download} from './ui';

const kinds = {attachment:'Файл к условию',starter:'Шаблон кода',resource:'Данные для программы'};

export function materialsEditor(root, base, usesAI) {
    if (!root) return {refresh() {}};
    // Materials use their own immediate save, independently of the task form.
    for(const type of ['input','change'])root.addEventListener(type,event=>event.stopPropagation());
    if (!base) {
        root.replaceChildren(node('p',{class:'gc-help-text mb-0'},'Сохраните задание, чтобы добавить файлы к условию, стартовый код или данные для программы.'));
        return {refresh() {}};
    }
    const list=node('ul',{class:'gc-material-editor__list list-unstyled mb-0'});
    const notice=node('div',{role:'status','aria-live':'polite',class:'gc-checker-notice'});
    let busy=false, path='', kind='attachment', isPublic=true, readOnly=false, includeInCheck=false;
    const safe=action=>async event=>{
        event?.preventDefault();if(busy)return;
        busy=true;root.setAttribute('aria-busy','true');notice.replaceChildren();
        try {await action();} catch(error) {notice.className='gc-checker-notice text-danger';notice.textContent=error.message;}
        finally {busy=false;root.removeAttribute('aria-busy');}
    };
    const load=async()=>{
        const {items}=await request(base+'/checker/files');
        list.replaceChildren(...items.map(row=>{
            const replacement=node('input',{type:'file',hidden:true,'aria-label':'Заменить '+row.path,onchange:safe(async()=>{
                if(!replacement.files[0])return;
                const data=new FormData();data.append('file',replacement.files[0]);data.append('path',row.path);data.append('kind',row.kind);
                for(const key of ['is_public','read_only','include_in_check'])data.append(key,row[key]?'1':'0');
                await request(base+'/checker/files/'+row.id+'/replace','POST',data);await load();
            })});
            const metadata=[kinds[row.kind] || kinds.attachment,row.is_public?'Виден ученику':'Только преподавателю'];
            if(row.read_only)metadata.push('Только чтение');
            if(row.include_in_check)metadata.push('Для проверки ИИ');
            return node('li',{class:'gc-material-editor__file'},
                node('div',{},download(row.download_url,row.path),node('small',{class:'text-muted d-block'},metadata.join(' · '))),
                actions(button('Заменить',()=>replacement.click()),button('Удалить',safe(async()=>{
                    if(!confirm(`Удалить файл «${row.path}» из задания? Сданные работы сохранят свою версию.`))return;
                    await request(base+'/checker/files/'+row.id,'DELETE');await load();
                }))),replacement);
        }));
        if(!items.length)list.append(node('li',{class:'gc-help-text'},'Файлы пока не добавлены.'));
    };
    const file=node('input',{type:'file',class:'form-control',onchange:()=>{path='';pathInput.value='';refresh();}});
    const pathInput=input('',value=>{path=value;},{placeholder:'По умолчанию — имя выбранного файла'});
    const publicField=check('Виден ученику',isPublic,value=>{isPublic=value;});
    const readOnlyField=check('Запретить изменение шаблона',readOnly,value=>{readOnly=value;});
    const aiField=check('Передавать файл в проверку ИИ',includeInCheck,value=>{includeInCheck=value;});
    const explanation=node('p',{class:'gc-help-text mb-0'});
    const metadata=node('div',{class:'gc-material-editor__upload'},
        node('div',{class:'gc-form-pair'},field('Имя файла',pathInput),field('Назначение',select(kind,kinds,value=>{kind=value;refresh();}))),
        explanation,node('div',{class:'gc-checker-choices'},publicField,readOnlyField,aiField),
        button('Добавить файл',safe(async()=>{
            if(!file.files[0])throw new Error('Выберите файл.');
            const data=new FormData();data.append('file',file.files[0]);data.append('path',path || file.files[0].name);data.append('kind',kind);
            data.append('is_public',kind!=='attachment'||isPublic?'1':'0');data.append('read_only',kind==='starter'&&readOnly?'1':'0');
            data.append('include_in_check',usesAI()&&includeInCheck?'1':'0');
            await request(base+'/checker/files','POST',data);file.value='';path='';pathInput.value='';refresh();await load();
        })));
    function refresh() {
        metadata.hidden=!file.files.length;publicField.hidden=kind!=='attachment';readOnlyField.hidden=kind!=='starter';aiField.hidden=!usesAI();
        explanation.textContent={attachment:'Ссылка появится в карточке задания. Файл не добавляется в решение автоматически.',starter:'Стартовый код копируется в новый черновик ученика. Шаблон всегда виден ученику.',resource:'Данные доступны ученику и его программе при запуске. Они не копируются в редактируемый код.'}[kind];
    }
    root.replaceChildren(list,field('Добавить материал',file),metadata,
        node('p',{class:'gc-help-text mb-0'},'Файлы сохраняются сразу. Скрытые тесты настраиваются в разделе проверки.'),notice);
    refresh();safe(load)();
    return {refresh};
}
