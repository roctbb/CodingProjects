import test from 'node:test';
import assert from 'node:assert/strict';
import { stageRow, deadlinePayload } from '../../resources/assets/js/components/deadline-editor.js';

test('deadline row preserves zero and rejects untrusted date markup', () => {
    const row=stageRow(3, {deadline_date:'2026-11-15',retained_percent:0});
    assert.match(row, /value="0"/);
    assert.match(row, /type="date"/);
    assert.match(row, /до 23:59/);
    assert.doesNotMatch(stageRow(4, {deadline_date:'"><img src=x>',retained_percent:'bad'}), /<img/);
});
test('serialization retains every stage and explicit zero', () => {
    const mode={value:'custom'};
    const editor={querySelector: selector=>selector.includes('revision')?{value:'7'}:selector.includes('timezone')?{value:'Europe/Moscow'}:mode,
        querySelectorAll:()=>Array.from({length:15},(_,i)=>({querySelector:selector=>({value:selector.includes('date')?'2026-11-15':String(i)})}))};
    const form={querySelector:()=>editor};
    const data=deadlinePayload(form);
    assert.equal(data.policy_revision,7); assert.equal(data.stages.length,15); assert.equal(data.stages[0].retained_percent,0);
    assert.equal(data.stages[0].deadline_date,'2026-11-15');
    mode.value='none'; assert.deepEqual(deadlinePayload(form).stages,[]);
    mode.value='inherit'; assert.deepEqual(deadlinePayload(form).stages,[]);
});
