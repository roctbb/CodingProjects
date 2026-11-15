import {test} from 'node:test';
import assert from 'node:assert/strict';
import {notebookData} from '../../resources/assets/js/code-checking/notebook-data.js';

test('notebook preview accepts string and list cells and missing optional output fields without changing the saved source',()=>{
    const source=JSON.stringify({nbformat:4,cells:[{cell_type:'code',source:['  print(1)\n'],outputs:[{output_type:'stream',text:'1\n'}]},{cell_type:'code',source:'x = 1'}]});
    const value=notebookData(source);
    assert.equal(value.cells[0].source,'  print(1)\n');assert.deepEqual(value.cells[0].outputs[0].text,['1\n']);
    assert.deepEqual(value.cells[1].outputs,[]);assert.equal(JSON.parse(source).cells[1].outputs,undefined);
});

test('notebook preview supports v3 worksheets and rejects unrelated JSON',()=>{
    const value=notebookData(JSON.stringify({nbformat:3,worksheets:[{cells:[{cell_type:'code',input:'print(1)',language:'python'}]}]}));
    assert.equal(value.cells[0].source,'print(1)');assert.equal(value.nbformat,4);
    assert.throws(()=>notebookData('{"cells":[]}'));assert.throws(()=>notebookData('{"nbformat":4,"cells":{}}'));
});
