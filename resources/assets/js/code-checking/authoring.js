import {testSettings} from './test-settings.js';
import {submissionFields, checkerPayload} from './authoring-settings.js';
import {node, button, field, input, textarea, select, check, actions, request, download, modes} from './ui';

export function authoring(root) {
    const form = root.closest('form');
    const hidden = form.querySelector('[data-checker-value]');
    let config, testEditor, attachments, updateAttachments;
    const modeSettings = new Map();
    try {config = JSON.parse(hidden.value);} catch {root.textContent = 'Исправьте настройки проверки и повторите сохранение.'; return;}
    const base = root.dataset.endpoint;
    const message = node('div',{class:'gc-checker-notice',role:'status','aria-live':'polite'});
    const body = node('div'); root.replaceChildren(body,message);
    const sync = () => {hidden.value = JSON.stringify(checkerPayload(config));};
    const notify = error => {message.className = 'gc-checker-notice alert alert-danger'; message.textContent = error.message;};
    const safe = action => async event => {event?.preventDefault(); try {await action();} catch (e) {notify(e);} };
    const update = (key,value) => {config[key] = value; sync();};
    function toggle() {
        root.hidden = form.querySelector('[name=answer_mode]:checked')?.value !== 'native';
        hidden.disabled = root.hidden;
    }
    form.addEventListener('change',toggle); toggle();
    const maximum=()=>Number(form.querySelector('[name=max_mark]')?.value || 0);
    form.addEventListener('input',event=>{if(event.target.name==='max_mark')testEditor?.refreshMaximum();});
    form.addEventListener('submit',event=>{
        if(root.hidden)return;
        const issues=config.check_type==='tests'?testEditor?.issues() || []:[];
        if(issues.length){event.preventDefault();notify(new Error(issues.join('\n')));message.tabIndex=-1;message.focus();}
    },{capture:true});
    function render() {
        testEditor=null;
        const conditional=[];
        const when=(key,element)=>{conditional.push([key,element]);return element;};
        const refreshFields=()=>{
            const visible=submissionFields(config);
            for(const [key,element] of conditional){element.hidden=!visible[key];element.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=element.hidden);}
            more.hidden=!Object.values(visible).some(Boolean);
        };
        const panel = node('div',{class:'gc-execution-settings'},
            node('p',{class:'gc-help-text'},'Ученик отправляет работу прямо в задаче: загружает файлы или вставляет готовый код.'),
            node('div',{class:'gc-form-pair'},
                field('Тип проверки',select(config.check_type,{manual:'Преподавателем',tests:'По тестам',gpt:'С помощью ИИ'},value => {
                    const previous=config.check_type==='tests'?'tests':'other',next=value==='tests'?'tests':'other';
                    if(previous!==next){
                        modeSettings.set(previous,structuredClone({language:config.language,allowed_languages:config.allowed_languages,submission_modes:config.submission_modes,test_mode:config.test_mode}));
                        if(modeSettings.has(next))Object.assign(config,structuredClone(modeSettings.get(next)));
                        else if(value==='tests'){config.language=['python','cpp'].includes(config.language)?config.language:'python';config.allowed_languages=(config.allowed_languages||[]).filter(l=>['python','cpp'].includes(l));if(!config.allowed_languages.includes(config.language))config.allowed_languages.unshift(config.language);config.submission_modes=(config.submission_modes||[]).filter(m=>m!=='text');if(!config.submission_modes.length)config.submission_modes=['files','code'];config.test_mode='io';}
                    }
                    config.check_type=value;
                    render();
                })), field('Основной язык',select(config.language,Object.fromEntries((config.check_type === 'tests' ? ['python','cpp'] : ['text','python','cpp','js','java','cs','html','css','sql','php','swift','json','xml','brainfuck']).map(l => [l,l === 'text' ? 'Текст / документы / изображения' : l])),value => {update('language',value); if (!config.allowed_languages?.includes(value)) config.allowed_languages = [value]; sync();refreshFields();}))),
            node('h3',{class:'h6 mt-3'},'Разрешённые способы сдачи'));
        panel.append(actions(Object.entries(modes).filter(([key]) => key !== 'text' || config.check_type !== 'tests').map(([key,label]) => check(label,config.submission_modes?.includes(key),value => {
            const values = new Set(config.submission_modes || []); value ? values.add(key) : values.delete(key); update('submission_modes',[...values]);refreshFields();
        }))));
        const more = node('details',{class:'gc-disclosure gc-disclosure--inline mt-3'},node('summary',{},'Параметры сдачи'));
        more.append(when('languages',field('Разрешённые языки',input((config.allowed_languages || [config.language]).join(', '),value => update('allowed_languages',value.split(',').map(v => v.trim()).filter(Boolean))), 'Идентификаторы через запятую, например python, cpp. Для тестов доступны Python и C++.')),
            when('extensions',field('Расширения файлов',input((config.allowed_extensions || []).join(', '),value => update('allowed_extensions',value.split(',').map(v => v.trim().replace(/^\./,'').toLowerCase()).filter(Boolean))),'Например py, cpp, zip, ipynb, xlsx, png. Пустое поле — любые разрешённые сервером файлы.')),
            when('entrypoint',field('Точка входа проекта',input(config.entrypoint,value => update('entrypoint',value || null)), 'Например main.py. Можно оставить пустым.')),
            when('limits',node('div',{class:'gc-form-pair'},field('Максимум файлов',input(config.max_files ?? 20,value => update('max_files',Number(value)),{type:'number',min:1,max:100})),
                field('Размер одного файла, МиБ',input((config.max_file_bytes ?? 52428800)/1048576,value => update('max_file_bytes',Math.round(Number(value)*1048576)),{type:'number',min:0.000001,max:50,step:'any'})),
                field('Общий размер, МиБ',input((config.max_total_bytes ?? 52428800)/1048576,value => update('max_total_bytes',Math.round(Number(value)*1048576)),{type:'number',min:1,max:50})))));
        panel.append(more,node('details',{class:'gc-disclosure gc-disclosure--inline mt-3'},node('summary',{},'Анализ работ'),
            check('Проверять совпадения с другими работами',!config.bypass_similarity_check,value => update('bypass_similarity_check',!value)),
            check('Анализировать возможную помощь ИИ',config.ai_authorship_check ?? true,value => update('ai_authorship_check',value))));
        if (config.check_type === 'gpt') panel.append(
            field('Закрытые критерии для ИИ',textarea(config.private_instructions,value => update('private_instructions',value),{rows:7})),
            node('details',{class:'gc-disclosure gc-disclosure--inline',open:!!config.reference_answer},node('summary',{},'Эталонный ответ (необязательно)'),field('Эталонный ответ',textarea(config.reference_answer,value => update('reference_answer',value),{rows:4}))),
            node('details',{class:'gc-disclosure gc-disclosure--inline'},node('summary',{},'Модель и лимит попыток'),
                field('Модель',input(config.gpt_model,value => update('gpt_model',value || null)), 'Пустое поле — модель, выбранная для сервиса проверки.'),
                field('Попыток в час',input(config.gpt_rate_limit ?? 3,value => update('gpt_rate_limit',Number(value)),{type:'number',min:1,max:1000}))));
        if (config.check_type === 'tests' && config.test_mode === 'suite') {
            panel.append(node('p',{class:'gc-help-text mt-3'},'Проверяющий сценарий проверяет структуру кода, файлы и критерии задачи. Чтобы заменить его обычными тестами, выберите тип «По тестам».'));
        } else if (config.check_type === 'tests') {
            config.test_config ||= {tests:[]};
            testEditor=testSettings(config.test_config,{base,maximum,sync,safe});
            panel.append(testEditor.element,check('Разрешить покупку проваленных скрытых тестов за GC',config.allows_test_reveals ?? true,value=>update('allows_test_reveals',value)));
        }
        if (base) {
            if(!attachments){
            attachments = node('details',{class:'gc-disclosure gc-disclosure--inline mt-3'},node('summary',{},'Материалы, шаблоны и приватные файлы'));
            const list = node('div');
            const file = node('input',{type:'file',class:'form-control'});
            let path = '', kind = 'attachment', isPublic = true, readOnly = false, includeInCheck = false;
            const load = async () => {const value = await request(base+'/checker/files'); list.replaceChildren(value.items.map(row => actions(download(row.download_url,row.path),node('small',{class:'text-muted'},row.is_public ? 'Доступно ученику' : 'Приватно'),button('Заменить',safe(async () => {
                if (!file.files[0]) throw new Error('Выберите новый файл в поле ниже.');
                const data = new FormData();data.append('file',file.files[0]);data.append('path',row.path);data.append('kind',row.kind);
                for (const key of ['is_public','read_only','include_in_check']) data.append(key,row[key] ? '1' : '0');
                await request(base+'/checker/files/'+row.id+'/replace','POST',data); file.value=''; updateAttachments();await load();
            })),button('Удалить',safe(async () => {if (!confirm(`Удалить файл «${row.path}» из задания? Сданные работы сохранят свою версию.`)) return; await request(base+'/checker/files/'+row.id,'DELETE'); await load();})))));};
            const readOnlyField=check('Шаблон нельзя изменять',readOnly,value=>{readOnly=value;}),aiField=check('Передать файл в проверку ИИ',includeInCheck,value=>{includeInCheck=value;}),publicField=check('Доступен ученику',isPublic,value=>{isPublic=value;});
            const metadata=node('div',{},field('Имя в проекте',input('',value => {path = value;},{placeholder:'data/input.csv'})),
                field('Назначение',select(kind,{attachment:'Материал задания',starter:'Шаблон кода',resource:'Данные для программы'},value => {kind = value;updateAttachments();})),
                publicField,readOnlyField,aiField,
                button('Загрузить файл',safe(async () => {
                    if (!file.files[0]) throw new Error('Выберите файл.');
                    const data = new FormData(); data.append('file',file.files[0]); data.append('path',path || file.files[0].name); data.append('kind',kind);
                    data.append('is_public',kind==='resource'||isPublic ? '1' : '0'); data.append('read_only',kind==='starter'&&readOnly ? '1' : '0'); data.append('include_in_check',config.check_type==='gpt'&&includeInCheck ? '1' : '0');
                    await request(base+'/checker/files','POST',data); file.value = ''; updateAttachments();await load();
                })));
            updateAttachments=()=>{metadata.hidden=!file.files.length;readOnlyField.hidden=kind!=='starter';aiField.hidden=config.check_type!=='gpt';publicField.hidden=kind==='resource';};
            file.addEventListener('change',updateAttachments);
            attachments.append(list,field('Добавить файл',file),metadata,node('small',{class:'text-muted d-block mt-2'},'Файлы сохраняются сразу. Скрытые тесты добавляйте в разделе «Тесты»; ресурсы программы всегда доступны ученику.'));
            let loaded = false;
            attachments.addEventListener('toggle',() => {if (attachments.open && !loaded) {loaded = true; load().catch(error => {loaded = false; notify(error);});}});
            }
            updateAttachments();
            panel.append(attachments);
        } else panel.append(node('p',{class:'gc-help-text mt-3'},'После создания задания можно добавить материалы, шаблоны и большие файлы тестов в его редакторе.'));
        body.replaceChildren(panel);refreshFields();sync();
    }
    render();
}
