import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { PetHopper } from '../../resources/assets/js/components/pet-hop.js';

test('every pet reaches the screen center, waits and returns to its entry side exactly once', () => {
    const names = ['cheremsha-hop-v2', 'learning_bot-walk-v1', 'brr_brr_patapim-walk-v1', 'tralalelo_tralala-walk-v1', 'tung_tung_tung_sahur-walk-v1'];
    const keys = ['document','innerWidth','innerHeight','devicePixelRatio','requestAnimationFrame','cancelAnimationFrame'];
    const before = new Map(keys.map(key => [key, globalThis[key]]));
    try {
        for (const name of names) {
            const data = JSON.parse(readFileSync(new URL(`../../public/images/screen-pets/${name}.json`, import.meta.url)));
            let index = 0, complete = 0, now = 0;
            const frames = new Map(), properties = {}, positions = [];
            const context = {setTransform(){},clearRect(){},save(){},restore(){},translate(){},scale(){},drawImage(){}};
            const canvas = {getContext: () => context,remove(){}};
            Object.assign(globalThis, {document: {createElement: () => canvas, querySelector: () => null}, innerWidth: 640, innerHeight: 800, devicePixelRatio: 1,
                requestAnimationFrame: callback => { const id = ++index; frames.set(id, callback); return id; }, cancelAnimationFrame: id => frames.delete(id)});
            const host = {append(){}, style: {setProperty: (key, value) => { properties[key] = value; }}};
            const pet = new PetHopper(host, {naturalWidth: 1024}, data, {stage: {clientWidth: 640}});
            pet.startVisit(() => complete++, () => 0);
            const home = pet.x;
            for (let i = 0; i < 10000 && !complete; i++) {
                const [id, callback] = frames.entries().next().value;
                frames.delete(id); now += 1000 / 60; callback(now);
                positions.push({x: pet.x, stage: pet.visit?.stage, time: now});
            }
            assert.equal(complete, 1, name);
            assert.equal(pet.x, home, name);
            assert.equal(pet.playing, false, name);
            assert.equal(frames.size, 0, name);
            const pause = positions.filter(point => point.stage === 'pause');
            assert.ok(pause.length > 90, name);
            assert.ok(pause.every(point => point.x === 320), name);
            assert.ok(positions.some(point => point.stage === 'back'), name);
            assert.ok(positions.every(point => point.x >= pet.left && point.x <= pet.right), name);
            assert.equal(properties['--screen-pet-bottom'], '0px', name);
        }
    } finally {
        for (const [key, value] of before) { if (value === undefined) delete globalThis[key]; else globalThis[key] = value; }
    }
});
