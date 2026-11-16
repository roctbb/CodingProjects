import test from 'node:test';
import assert from 'node:assert/strict';
import { advanceRay, rayHoverCounter } from '../../resources/assets/js/components/sidebar-ray-model.js';

test('the logo ray requires twenty hovers and clears old or completed sequences', () => {
    const entered = rayHoverCounter();
    for (let i = 0; i < 19; i++) assert.equal(entered(i * 1000), false);
    assert.equal(entered(19000), true);
    assert.equal(entered(20000), false);
    assert.equal(entered(120000), false);
});

test('a ray reflects at each wall and retains its speed', () => {
    const cases = [
        {x: 9, y: 60, vx: -200, vy: 20}, {x: 310, y: 60, vx: 200, vy: 20},
        {x: 60, y: 9, vx: 20, vy: -200}, {x: 60, y: 190, vx: 20, vy: 200},
    ];
    for (const ray of cases) {
        const result = advanceRay(ray, .05, 320, 200);
        assert.equal(result.bounces, 1);
        assert.ok(result.x >= 8 && result.x <= 312 && result.y >= 8 && result.y <= 192);
        assert.equal(Math.hypot(result.vx, result.vy), Math.hypot(ray.vx, ray.vy));
    }
    const corner = advanceRay({x: 9, y: 9, vx: -200, vy: -200}, .05, 320, 200);
    assert.equal(corner.bounces, 2);
    assert.ok(corner.vx > 0 && corner.vy > 0);
});
