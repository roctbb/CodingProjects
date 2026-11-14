import test from 'node:test';
import assert from 'node:assert/strict';
import { stageRow, deadlinePayload } from '../../resources/assets/js/components/deadline-editor.js';

test('deadline row preserves zero and rejects untrusted date markup', () => {
    assert.match(stageRow(3, {cutoff_at:'2026-11-15T18:00',retained_percent:0}), /value="0"/);
    assert.doesNotMatch(stageRow(4, {cutoff_at:'"><img src=x>',retained_percent:'bad'}), /<img/);
});
test('serialization retains every stage and explicit zero', () => {
    const mode={value:'custom'};
    const editor={querySelector: selector=>selector.includes('revision')?{value:'7'}:selector.includes('timezone')?{value:'Europe/Moscow'}:mode,
        querySelectorAll:()=>Array.from({length:15},(_,i)=>({querySelector:selector=>({value:selector.includes('datetime')?'2026-11-15T18:00':String(i)})}))};
    const form={querySelector:()=>editor};
    const data=deadlinePayload(form);
    assert.equal(data.policy_revision,7); assert.equal(data.stages.length,15); assert.equal(data.stages[0].retained_percent,0);
    mode.value='none'; assert.deepEqual(deadlinePayload(form).stages,[]);
    mode.value='inherit'; assert.deepEqual(deadlinePayload(form).stages,[]);
});
