const clamp = (value, low = 0, high = 1) => Math.max(low, Math.min(high, value));
const mix = (a, b, t) => a + (b - a) * t;
const smooth = t => t * t * (3 - 2 * t);
const point = (a, b, t) => ({x: mix(a[0], b[0], t), y: mix(a[1], b[1], t)});

function hipAt(leg, yaw) {
    const views = yaw < .5 ? ['right', 'front'] : ['front', 'left'];
    return point(leg[views[0]], leg[views[1]], smooth(yaw < .5 ? yaw * 2 : yaw * 2 - 1));
}

function footAt(leg, yaw) {
    const positions = leg.feet || leg;
    const views = yaw < .5 ? ['right', 'front'] : ['front', 'left'];
    return point(positions[views[0]], positions[views[1]], smooth(yaw < .5 ? yaw * 2 : yaw * 2 - 1));
}

export function solveLeg(hip, foot, upper, lower, bend = 1) {
    const dx = foot.x - hip.x, dy = foot.y - hip.y;
    const actual = Math.max(.001, Math.hypot(dx, dy));
    const distance = clamp(actual, Math.abs(upper - lower) + .001, upper + lower - .001);
    const along = (upper * upper - lower * lower + distance * distance) / (2 * distance);
    const height = Math.sqrt(Math.max(0, upper * upper - along * along));
    return {x: hip.x + dx / actual * along - dy / actual * height * bend,
        y: hip.y + dy / actual * along + dx / actual * height * bend};
}

export function plantedFoot(phase, stride, duty, lift) {
    const cycle = ((phase % 1) + 1) % 1;
    const half = stride * duty / 2;
    if (cycle < duty) return {x: half - stride * cycle, y: 0, planted: true, angle: 0};
    const swing = (cycle - duty) / (1 - duty);
    return {x: mix(-half, half, smooth(swing)), y: -Math.sin(Math.PI * swing) * lift,
        planted: false, angle: -.18 * Math.sin(Math.PI * swing)};
}

export function rigPose(rig, {direction = 1, phase = 0, state = 'stand', progress = 0, stride = rig.stride, time = 0} = {}) {
    const startView = direction > 0 ? 'right' : 'left';
    const endView = direction > 0 ? 'left' : 'right';
    let yaw = direction > 0 ? 0 : 1;
    let bodyLayers = [{frame: rig.body[startView], opacity: 1}];
    let order, turnIndex;
    if (state === 'turn') {
        yaw = direction > 0 ? smooth(progress) : 1 - smooth(progress);
        const sequence = rig.turnBody || [rig.body.right, rig.body.front, rig.body.left];
        turnIndex = Math.round(yaw * (sequence.length - 1));
        bodyLayers = [{frame: sequence[turnIndex], opacity: 1}];
        order = [[startView, 'front'], ['front', endView]].map(views => rig.legs
            .map((leg, index) => ({index, distance: Math.abs((leg.feet || leg)[views[0]][0] - (leg.feet || leg)[views[1]][0])}))
            .sort((a, b) => b.distance - a.distance).map(item => item.index));
    }
    const legs = rig.legs.map((leg, index) => {
        const mount = state === 'turn' ? rig.turnHips?.[turnIndex]?.[index] : null;
        const hip = mount ? {x: mount[0], y: mount[1]} : hipAt(leg, yaw);
        const step = plantedFoot(phase + leg.phase, stride, rig.duty, rig.lift);
        let foot = {x: (leg.feet || leg)[startView][0], y: rig.ankle, angle: 0, view: startView, planted: true};
        if (state === 'walk') {
            foot = {...foot, x: foot.x + direction * step.x, y: foot.y + step.y,
                angle: direction * step.angle, planted: step.planted};
        } else if (state === 'prepare' || state === 'settle') {
            const t = clamp(progress * rig.legs.length - index);
            const amount = state === 'prepare' ? smooth(t) : 1 - smooth(t);
            const initial = plantedFoot(leg.phase, stride, rig.duty, rig.lift);
            foot.x += direction * initial.x * amount;
            foot.y -= Math.sin(Math.PI * t) * rig.repositionLift;
            foot.planted = t === 0 || t === 1;
        } else if (state === 'turn') {
            const slot = .25 / rig.legs.length;
            const swing = slot * .95;
            let anchor = {x: (leg.feet || leg)[startView][0], y: rig.ankle};
            for (let stepIndex = 0; stepIndex < 3; stepIndex++) {
                const offset = order[stepIndex < 2 ? 0 : 1].indexOf(index) * slot;
                const start = stepIndex * .36 + offset, end = start + swing;
                if (progress < start) break;
                const landingYaw = stepIndex === 2 ? (direction > 0 ? 1 : 0)
                    : (direction > 0 ? smooth(end) : 1 - smooth(end));
                const target = footAt(leg, landingYaw);
                if (progress < end) {
                    const t = (progress - start) / swing;
                    foot.x = mix(anchor.x, target.x, smooth(t));
                    foot.y = mix(anchor.y, target.y, smooth(t)) - Math.sin(Math.PI * t) * rig.repositionLift;
                    foot.planted = false;
                    const viewYaw = t < .5 ? yaw : landingYaw;
                    foot.view = viewYaw < .25 ? 'right' : viewYaw > .75 ? 'left' : 'front';
                    break;
                }
                anchor = target;
                foot.x = anchor.x;
                foot.y = anchor.y;
                foot.view = landingYaw < .25 ? 'right' : landingYaw > .75 ? 'left' : 'front';
            }
        }
        const depth = Math.cos(Math.PI * yaw) * (leg.far ? -1 : 1)
            + Math.sin(Math.PI * yaw) * (leg.frontLeg ? .8 : -.8);
        return {hip, foot, depth, upper: leg.upper, lower: leg.lower, bend: leg.bend * Math.cos(Math.PI * yaw), far: !!leg.far};
    });
    let bodyY = state === 'walk' ? Math.cos(phase * 4 * Math.PI) * rig.bob : Math.sin(time / 1800) * .7;
    if (state === 'turn') bodyY += Math.sin(Math.PI * progress) * 7;
    // A small crouch keeps fixed-length limbs in reach during a short step or a turn.
    for (const leg of legs) {
        const dx = leg.foot.x - leg.hip.x;
        const reach = leg.upper + leg.lower - .2;
        const maxHeight = Math.sqrt(Math.max(0, reach * reach - dx * dx));
        bodyY = Math.max(bodyY, leg.foot.y - leg.hip.y - maxHeight);
    }
    for (const leg of legs) {
        leg.hip.y += bodyY;
        leg.knee = solveLeg(leg.hip, leg.foot, leg.upper, leg.lower, leg.bend);
    }
    return {legs, bodyLayers, bodyY, yaw};
}
