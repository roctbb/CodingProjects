import test from 'node:test';
import assert from 'node:assert/strict';
import { deadlineState } from '../../resources/assets/js/components/deadline-summary.js';

test('deadline summary shows the current penalty and only the next stage, including zero retained points', () => {
    const stages = [{cutoff:1000,percent:70},{cutoff:2000,percent:50},{cutoff:3000,percent:0}];
    assert.deepEqual(deadlineState(stages,false,999),{penalty:0,next:stages[0]});
    assert.deepEqual(deadlineState(stages,false,1000),{penalty:30,next:stages[1]});
    assert.deepEqual(deadlineState(stages,false,2500),{penalty:50,next:stages[2]});
    assert.deepEqual(deadlineState(stages,false,3000),{penalty:100,next:null});
    assert.deepEqual(deadlineState([],false,3000),{penalty:0,next:null});
});

test('deadline summary preserves legacy strict boundaries and fractional penalties', () => {
    const stages = [{cutoff:1000,percent:72.5}];
    assert.deepEqual(deadlineState(stages,true,1000),{penalty:0,next:stages[0]});
    assert.deepEqual(deadlineState(stages,true,1001),{penalty:27.5,next:null});
});
