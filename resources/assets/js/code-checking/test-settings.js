import {node, button, field, input, textarea, select, check, actions, request, download} from './ui.js';
import {comparisons, enableGroups, addGroup, removeGroup, testIssues} from './test-settings-model.js';
import {hasFloatComparison} from './authoring-settings.js';

export function testSettings(config, {base, maximum, sync, safe, diagnostic = false}) {
    config.tests ||= [];
    if (diagnostic && config.scoring !== 'diagnostic') enableGroups(config, maximum(), true);
    const grouped = () => diagnostic || config.scoring === 'groups';
    const host=node('div',{class:'gc-execution-tests'});
    let summary,precision;
    const refreshPrecision=()=>{if(precision){precision.hidden=!hasFloatComparison(config);precision.querySelectorAll('input').forEach(input=>input.disabled=precision.hidden);}};
    const changed=()=>{sync();refreshMaximum();refreshPrecision();};
    const options=value=>({...comparisons,...(value && !comparisons[value] ? {[value]:`Сохранённое правило: ${value}`} : {})});
    const number=(label, object, key, fallback, attrs={}, factor=1, optional=false)=>field(label,input(
        object[key] == null ? optional ? '' : fallback/factor : object[key]/factor,
        value=>{if(optional && value==='') delete object[key];else object[key]=Number(value)*factor;changed();},
        {type:'number',...attrs, ...(optional ? {placeholder:'Как в общих настройках'} : {})}));
    function refreshMaximum() {
        if(!summary)return;
        const total=(config.groups || []).reduce((sum,group)=>sum+Number(group.points || 0),0);
        summary.className=total===maximum()?'text-success':'text-danger';
        summary.textContent=`Баллы групп: ${total} из ${maximum()} XP. Группа засчитывается, когда пройдены все её тесты.`;
    }
    function render() {
        summary=null;
        const rules=node('details',{class:'gc-disclosure gc-disclosure--inline',open:true},node('summary',{},diagnostic?'Правила сравнения и группы':'Правила сравнения и начисления баллов'));
        rules.append(node('div',{class:'gc-form-pair'},
            field('Сравнение по умолчанию',select(config.comparison || 'tokens',options(config.comparison),value=>{config.comparison=value;changed();})),
            diagnostic ? null : field('Начисление баллов',select(config.scoring || 'proportional',{proportional:'Пропорционально тестам',all_or_nothing:'Только за все тесты',groups:'По группам тестов'},value=>{
                if(value==='groups')enableGroups(config,maximum());else config.scoring=value;render();changed();
            }))),
            field('Пробелы и переводы строк в конце ответа',select(config.trailing_whitespace || 'one_newline',{one_newline:'Убрать один завершающий перевод строки',newlines:'Убрать завершающие переводы строк',all:'Убрать все завершающие пробельные символы'},value=>{config.trailing_whitespace=value;changed();})));
        if(grouped()) {
            const groups=node('div',{},node('h4',{class:'h6'},'Группы тестов'));
            if(diagnostic)groups.append(node('p',{class:'gc-help-text'},'Назовите проверяемое поведение. Названия увидят ученики и получит ИИ вместе с результатами тестов.'));
            for(const group of config.groups)groups.append(node('div',{class:'gc-execution-group'},
                node('div',{class:'gc-form-pair'},field('Название группы',input(group.name,value=>{group.name=value;for (const option of host.querySelectorAll('option')) if(option.value===group.id) option.textContent=value;changed();},{maxlength:100})),
                    diagnostic ? null : number('Баллы группы, XP',group,'points',1,{min:1,max:1000000,step:1})),
                button('Удалить группу',()=>{if(config.tests.some(test=>test.group===group.id) && !confirm('Удалить группу? Для её тестов нужно будет выбрать другую группу.'))return;removeGroup(config,group.id);render();changed();})));
            if(!diagnostic)summary=node('p',{role:'status','aria-live':'polite'});
            groups.append(button('Добавить группу',()=>{addGroup(config);render();changed();}));
            if(summary)groups.append(summary);
            rules.append(groups);
        }
        const limits=node('details',{class:'gc-disclosure gc-disclosure--inline'},node('summary',{},'Лимиты исполнения и точность чисел'),
            node('div',{class:'gc-form-pair'},number('Время на тест, с',config,'time_limit',2,{min:1,max:30,step:1}),
                number('Память, МиБ',config,'memory_limit',512,{min:128,max:2048,step:1})),
            node('div',{class:'gc-form-pair'},number('Лимит вывода, КиБ',config,'output_limit',8388608,{min:1,max:8192,step:1},1024),
                number('Максимум процессов',config,'process_limit',128,{min:16,max:256,step:1})),
            check('Запретить swap',config.disable_swap || false,value=>{config.disable_swap=value;changed();}));
        precision=node('div',{class:'gc-form-pair',hidden:!hasFloatComparison(config)},field('Абсолютная погрешность',input(config.absolute_tolerance ?? '0.000001',value=>{config.absolute_tolerance=value;changed();},{type:'number',min:0,max:1,step:'any'})),
            field('Относительная погрешность',input(config.relative_tolerance ?? '0.000001',value=>{config.relative_tolerance=value;changed();},{type:'number',min:0,max:1,step:'any'})));
        limits.append(precision);
        refreshPrecision();
        host.replaceChildren(rules,limits,node('h3',{class:'h6'},'Тесты'));
        config.tests.forEach((test,index)=>{
            const title=node('summary',{},`Тест ${index+1}${test.is_public?' · открытый':' · скрытый'}`);
            const row=node('details',{class:'gc-disclosure gc-disclosure--inline',open:index===0},title);
            const fixture=(key,label)=>{
                const value=test[key],area=node('div');
                if(typeof value==='string' || value==null)area.append(field(label,textarea(value,text=>{test[key]=text;changed();},{spellcheck:false})));
                else area.append(node('p',{},label,' · ',download(`${base}/checker/fixtures/${value.blob_id}`,`Сохранённый файл (${value.bytes} байт)`)),button('Заменить текстом',()=>{test[key]='';render();changed();}));
                if(base){const upload=node('input',{type:'file',class:'form-control',onchange:safe(async()=>{
                    const file=upload.files[0];if(!file)return;if(file.size>33554432)throw new Error('Поле теста должно быть не больше 32 МиБ.');
                    const data=new FormData();data.append('file',file);test[key]=await request(base+'/checker/fixtures','POST',data);render();changed();
                })});
                area.append(field(`Файл: ${label.toLowerCase()}`,upload,'Полный текст хранится в базе данных.'));}return area;
            };
            row.append(node('div',{class:'gc-form-pair'},fixture('input','Входные данные'),fixture('output','Ожидаемый вывод')),
                check('Открытый тест — данные доступны ученику',test.is_public,value=>{test.is_public=value;title.textContent=`Тест ${index+1}${value?' · открытый':' · скрытый'}`;changed();}));
            if(grouped())row.append(field(`Группа теста ${index+1}`,select(test.group || '',{'':'Выберите группу',...Object.fromEntries(config.groups.map(group=>[group.id,group.name]))},value=>{test.group=value;changed();})));
            const overrides=node('details',{class:'gc-disclosure gc-disclosure--inline'},node('summary',{},'Настройки этого теста'),
                field('Правило сравнения этого теста',select(test.comparison || '',{'':'Как в общих настройках',...options(test.comparison)},value=>{if(value)test.comparison=value;else delete test.comparison;changed();})),
                node('div',{class:'gc-form-pair'},number('Время этого теста, с',test,'time_limit',2,{min:1,max:30,step:1},1,true),
                    number('Лимит вывода этого теста, КиБ',test,'output_limit',8388608,{min:1,max:8192,step:1},1024,true)));
            row.append(overrides,actions(button('Копировать тест',()=>{if(config.tests.length>=50)return;config.tests.splice(index+1,0,structuredClone(test));render();changed();}),
                button('Удалить тест',()=>{config.tests.splice(index,1);render();changed();})));
            host.append(row);
        });
        const add=button('Добавить тест',()=>{config.tests.push({input:'',output:'',is_public:false,...(grouped() && config.groups[0]?{group:config.groups[0].id}:{})});render();changed();});
        add.disabled=config.tests.length>=50;host.append(add,node('small',{class:'text-muted'},`${config.tests.length} из 50 тестов`));refreshMaximum();
    }
    render();return {element:host,refreshMaximum,issues:()=>testIssues(config,maximum())};
}
