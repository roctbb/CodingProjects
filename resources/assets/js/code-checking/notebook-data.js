const text=value=>typeof value==='string'?value:Array.isArray(value)?value.filter(part=>typeof part==='string').join(''):'';

export function notebookData(source){
    const input=JSON.parse(source);
    if(!input || ![3,4].includes(input.nbformat))throw new Error('Не удалось прочитать формат тетрадки. Исходный файл доступен для скачивания.');
    const cells=input.nbformat===4?input.cells:input.worksheets?.flatMap(sheet=>sheet.cells||[]);
    if(!Array.isArray(cells))throw new Error('В тетрадке не найден список ячеек.');
    const normalised=cells.filter(cell=>cell&&typeof cell==='object').map(cell=>{
        const value={...cell,source:text(cell.source??cell.input)};
        if(cell.cell_type==='code')value.outputs=(Array.isArray(cell.outputs)?cell.outputs:[]).filter(output=>output&&typeof output==='object').map(output=>{
            const copy={...output,metadata:output.metadata||{}};
            if(output.text!==undefined)copy.text=[text(output.text)];
            if(output.traceback!==undefined)copy.traceback=Array.isArray(output.traceback)?output.traceback:[text(output.traceback)];
            if(output.data)copy.data=Object.fromEntries(Object.entries(output.data).map(([key,value])=>[key,[text(value)]]));
            if(['display_data','execute_result'].includes(copy.output_type))copy.data ||= {};
            return copy;
        });
        if(cell.cell_type==='raw')return {...value,cell_type:'markdown',source:value.source.split('\n').map(line=>'    '+line).join('\n')};
        if(cell.cell_type==='heading')return {...value,cell_type:'markdown',source:'#'.repeat(Math.min(6,Math.max(1,Number(cell.level)||1)))+' '+value.source};
        return value;
    }).filter(cell=>['code','markdown'].includes(cell.cell_type));
    return {nbformat:4,metadata:input.metadata||{},cells:normalised};
}
