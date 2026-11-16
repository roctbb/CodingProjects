export function rayHoverCounter(threshold = 20, windowMs = 90000) {
    let entries = [];
    return (now = Date.now()) => {
        entries = entries.filter(time => now - time <= windowMs);
        entries.push(now);
        if (entries.length < threshold) return false;
        entries = [];
        return true;
    };
}

export function advanceRay(ray, dt, width, height, margin = 8) {
    let {x, y, vx, vy, bounces = 0} = ray;
    x += vx * dt; y += vy * dt;
    if (x < margin) { x = 2 * margin - x; vx = Math.abs(vx); bounces++; }
    else if (x > width - margin) { x = 2 * (width - margin) - x; vx = -Math.abs(vx); bounces++; }
    if (y < margin) { y = 2 * margin - y; vy = Math.abs(vy); bounces++; }
    else if (y > height - margin) { y = 2 * (height - margin) - y; vy = -Math.abs(vy); bounces++; }
    return {x, y, vx, vy, bounces};
}
