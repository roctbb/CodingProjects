import test from 'node:test';
import assert from 'node:assert/strict';
import { deadlineState, lessonDeadlineState } from '../../resources/assets/js/components/deadline-summary.js';

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

const cutoff = Date.parse('2026-11-16T21:00:00Z');
const rule = (percent=70,legacy=false) => ({timezone:'Europe/Moscow',legacy,stages:[{cutoff,percent,day:'2026-11-16'}]});

test('lesson calendars distinguish today, recoverable overdue work, and full penalties', () => {
    assert.equal(lessonDeadlineState([rule()],Date.parse('2026-11-15T12:00:00Z')),null);
    assert.equal(lessonDeadlineState([rule()],Date.parse('2026-11-16T12:00:00Z')),'today');
    assert.equal(lessonDeadlineState([rule()],cutoff),'overdue');
    assert.equal(lessonDeadlineState([rule(0)],cutoff),null);
    assert.equal(lessonDeadlineState([rule(0.004)],cutoff),'overdue');
    assert.equal(lessonDeadlineState([],cutoff),null);
});

test('lesson calendar alerts preserve the strict legacy scoring boundary', () => {
    assert.equal(lessonDeadlineState([rule(70,true)],cutoff),null);
    assert.equal(lessonDeadlineState([rule(70,true)],cutoff+1),'overdue');
});

test('lesson calendar day respects timezone and ignores expired zero-point work', () => {
    const tomorrow={timezone:'Europe/Moscow',legacy:false,stages:[{cutoff:cutoff+86400000,percent:50,day:'2026-11-17'}]};
    const now=Date.parse('2026-11-16T22:00:00Z');
    assert.equal(lessonDeadlineState([tomorrow],now),'today');
    assert.equal(lessonDeadlineState([{...tomorrow,timezone:'America/New_York'}],now),null);
    assert.equal(lessonDeadlineState([rule(0),tomorrow],now),'today');
    assert.equal(lessonDeadlineState([tomorrow,rule()],now),'overdue');
});
