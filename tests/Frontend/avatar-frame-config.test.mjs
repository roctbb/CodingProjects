import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeFrameConfig, frameCostFor, frameAnimationFor, avatarEffectFor, gradientFor } from '../../resources/assets/js/components/avatar-frame-config.js';

const sample = overrides => normalizeFrameConfig({
    type: 'linear', shape: 'circle', motion: 'spin', pattern: 'sparkles', avatarEffect: 'sheen',
    colors: ['#22D3EE', '#8b5cf6'], angle: 135, width: 6, glow: 28, speed: 100,
    patternOpacity: 72, effectOpacity: 70, animated: true, ...overrides,
});

test('frame dimensions and animation inputs stay within supported limits', () => {
    const config = sample({ width: 100, angle: -20, glow: 90, speed: 1, patternOpacity: 130, effectOpacity: -5 });
    assert.equal(config.width, 14);
    assert.equal(config.angle, 0);
    assert.equal(config.glow, 52);
    assert.equal(config.speed, 60);
    assert.equal(config.patternOpacity, 100);
    assert.equal(config.effectOpacity, 20);
});

test('static frames disable both frame and avatar animation', () => {
    const config = sample({ animated: false });
    assert.equal(frameAnimationFor(config), 'none');
    assert.deepEqual(avatarEffectFor(config), ['transparent', 'none', '0']);
});

test('preview pricing follows the selected frame type and supplied store prices', () => {
    const prices = { frameStaticCost: '60', frameAnimatedCost: '90' };
    assert.equal(frameCostFor(sample({ animated: false }), prices), 60);
    assert.equal(frameCostFor(sample({ animated: true }), prices), 90);
    assert.equal(frameCostFor(sample(), null), 0);
});

test('invalid palette input cannot introduce arbitrary CSS into a frame', () => {
    const config = sample({ colors: ['#AABBCC', 'url(https://example.invalid/track)'] });
    const gradient = gradientFor(config);
    assert.match(gradient, /#aabbcc/);
    assert.doesNotMatch(gradient, /url\(|example/);
});
