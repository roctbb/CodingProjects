export function submissionGroups(config) {
    const allowed=config.submission_modes || [];
    const groups=[];
    if(allowed.includes('files'))groups.push('files');
    if(allowed.includes('code') || allowed.includes('project'))groups.push('code');
    if(allowed.includes('repository'))groups.push('repository');
    return groups;
}

export function submissionMode(config,kind,fileCount=0) {
    const allowed=config.submission_modes || [];
    if(kind==='code')return fileCount<=1 && allowed.includes('code')?'code':'project';
    if(kind==='text')return fileCount && allowed.includes('files')?'files':'text';
    if(kind==='files' && allowed.includes('text') && !fileCount)return 'text';
    return kind;
}

export function uploadDescription(config) {
    const extensions=(config.allowed_extensions || []).map(value=>value.replace(/^\./,'').toLowerCase());
    if(extensions.length===1 && extensions[0]==='ipynb')return 'Jupyter Notebook (.ipynb)';
    if(extensions.length && extensions.every(value=>['xlsx','xls','csv'].includes(value)))return `Таблица Excel (${extensions.map(value=>'.'+value).join(', ')})`;
    return extensions.length?'Файлы '+extensions.map(value=>'.'+value).join(', '):'Файлы решения';
}

export function executionPhase(progress={}) {
    const labels={queued:'Жду своей очереди',preparing:'Создаю среду для проверки',compiling:'Компилирую программу',testing:'Запускаю тесты',grading:'Проверяю решение',publishing:'Сохраняю результат',completed:'Проверка завершена',error:'Не удалось завершить проверку'};
    const exact=progress.phase==='testing' && Number(progress.total)>0;
    const completed=Math.min(Number(progress.total)||0,Math.max(0,Number(progress.completed)||0));
    return {label:(labels[progress.phase] || 'Проверяю решение')+(exact?` · ${completed} из ${progress.total}`:''),completed,total:exact?Number(progress.total):null};
}

export function draftFilePayload(files,revision) {
    const retained=file=>revision>0 && file.index!==undefined && !file.changed;
    return {keep_files:files.filter(retained).map(file=>file.index),
        editor_files:files.filter(file=>!retained(file) && file.content!==undefined).map(file=>({path:file.path,content:file.content}))};
}
