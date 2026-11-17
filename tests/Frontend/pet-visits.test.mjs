import test from 'node:test';
import assert from 'node:assert/strict';
import { PetVisits } from '../../resources/assets/js/components/pet-visits.js';

function fixture(random = .5, nextAt = 0, onNext = () => {}) {
    let time = 0, index = 0;
    const timers = new Map(), events = [];
    const visits = new PetVisits({random: () => random, now: () => time, nextAt, onNext,
        setTimer: (callback, delay) => { const id = ++index; timers.set(id, {at: time + delay, callback}); return id; },
        clearTimer: id => timers.delete(id), onStart: () => events.push('start'), onStop: reason => events.push(reason)});
    return {visits, events, timers, advance(ms) {
        const until = time + ms;
        while (true) {
            const first = [...timers].sort((a, b) => a[1].at - b[1].at)[0];
            if (!first || first[1].at > until) break;
            time = first[1].at; timers.delete(first[0]); first[1].callback();
        }
        time = until;
    }};
}

test('automatic visits are rare and a completed return schedules another long wait', () => {
    const f = fixture(); f.visits.setAvailable(true);
    f.advance(539999); assert.deepEqual(f.events, []);
    f.advance(1); assert.deepEqual(f.events, ['start']);
    f.advance(28000); assert.deepEqual(f.events, ['start']);
    f.visits.complete(); assert.deepEqual(f.events, ['start', 'finished']);
    f.advance(539999); assert.equal(f.events.length, 2);
    f.advance(1); assert.equal(f.events.at(-1), 'start');
});

test('hidden pages freeze the wait and interrupt motion without starting background visits', () => {
    const f = fixture(); f.visits.setAvailable(true); f.advance(100000);
    f.visits.setAvailable(false); f.advance(900000); assert.deepEqual(f.events, []);
    f.visits.setAvailable(true); f.advance(439999); assert.deepEqual(f.events, []);
    f.advance(1); assert.equal(f.events.at(-1), 'start');
    f.visits.setAvailable(false); assert.equal(f.events.at(-1), 'interrupted'); assert.equal(f.timers.size, 0);
});

test('a stalled animation ends eventually, with no duplicate active visits', () => {
    const f = fixture(); f.visits.setAvailable(true); f.visits.start(); f.visits.start();
    assert.deepEqual(f.events, ['start']);
    f.advance(180000); assert.deepEqual(f.events, ['start', 'finished']);
    f.visits.cancel(); assert.equal(f.timers.size, 0);
});

test('saved cooldown prevents reloads from immediately repeating a visit', () => {
    const f = fixture(.5, 600000); f.visits.setAvailable(true);
    f.advance(599999); assert.deepEqual(f.events, []);
    f.advance(1); assert.deepEqual(f.events, ['start']);
});

test('navigation preserves the first scheduled visit and updates the wait after a pause', () => {
    let saved = 0;
    const first = fixture(.5, 0, next => { saved = next; });
    first.visits.setAvailable(true);
    first.advance(120000);
    first.visits.cancel();
    const nextPage = fixture(1, saved - 120000, next => { saved = next; });
    nextPage.visits.setAvailable(true);
    nextPage.advance(419999); assert.deepEqual(nextPage.events, []);
    nextPage.advance(1); assert.deepEqual(nextPage.events, ['start']);
    nextPage.visits.complete();
    nextPage.advance(1000);
    nextPage.visits.setAvailable(false);
    nextPage.advance(900000);
    nextPage.visits.setAvailable(true);
    assert.equal(saved, 2040000);
    nextPage.advance(718999); assert.equal(nextPage.events.length, 2);
    nextPage.advance(1); assert.equal(nextPage.events.at(-1), 'start');
});
