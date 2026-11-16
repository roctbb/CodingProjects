import test from 'node:test';
import assert from 'node:assert/strict';
import { hopPose } from '../../resources/assets/js/components/pet-hop.js';

test('a rabbit stays still on the ground, travels in flight and lands at the destination', () => {
    for (const time of [0, .1, .2]) {
        assert.equal(hopPose(time).progress, 0);
        assert.equal(hopPose(time).height, 0);
    }
    const apex = hopPose(.45);
    assert.ok(Math.abs(apex.height - 1) < .001);
    assert.ok(Math.abs(apex.progress - .5) < .001);
    for (const time of [.7, .9, 1]) {
        assert.equal(hopPose(time).progress, 1);
        assert.equal(hopPose(time).height, 0);
    }
});

test('one hop uses all sixteen poses and its seated endpoints remain valid', () => {
    const sequence = Array.from({length: 160}, (_, i) => hopPose(i / 159));
    assert.equal(new Set(sequence.map(pose => pose.frame)).size, 16);
    assert.equal(hopPose(-1).frame, 0);
    assert.equal(hopPose(2).frame, 15);
    assert.ok(sequence.every((pose, i) => !i || pose.progress >= sequence[i - 1].progress));
});
