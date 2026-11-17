import { rigPose } from './pet-rig-model.js';

export function drawRig(ctx, image, data, {size, height, ...motion}) {
    const rig = data.rig;
    const pose = rigPose(rig, motion);
    const ratio = image.naturalWidth / (data.cell * data.columns);
    function sprite(index, x, y) {
        const frame = data.frames[index];
        ctx.drawImage(image, frame.x * ratio, frame.y * ratio, data.cell * ratio, data.cell * ratio, x, y, 256, 256);
    }
    function bone(index, from, to, length) {
        ctx.save(); ctx.translate(from.x, from.y);
        ctx.rotate(Math.atan2(to.y - from.y, to.x - from.x) - Math.PI / 2);
        ctx.scale(1, Math.hypot(to.x - from.x, to.y - from.y) / length);
        sprite(index, -128, -128); ctx.restore();
    }
    function leg(item, pass = 'all') {
        if (rig.material === 'robot') { drawRobotLeg(ctx, {...item, far: item.depth < 0}, pass); return; }
        if (pass !== 'lower') bone(rig.bones.upper, item.hip, item.knee, rig.bones.upperLength);
        if (pass === 'upper') return;
        bone(rig.bones.lower, item.knee, item.foot, rig.bones.lowerLength);
        ctx.save(); ctx.translate(item.foot.x, item.foot.y); ctx.rotate(item.foot.angle);
        sprite(rig.feet[item.foot.view], -128, -128); ctx.restore();
    }
    ctx.clearRect(0, 0, size, height);
    ctx.save(); ctx.translate(0, height - 1 - 253 * size / 256); ctx.scale(size / 256, size / 256);
    const sorted = [...pose.legs].sort((a, b) => a.depth - b.depth);
    sorted.filter(item => item.depth < 0).forEach(item => leg(item));
    sorted.filter(item => item.depth >= 0).forEach(item => leg(item, 'upper'));
    for (const layer of pose.bodyLayers) {
        if (layer.opacity <= 0) continue;
        ctx.save(); ctx.globalAlpha = layer.opacity; sprite(layer.frame, 0, pose.bodyY); ctx.restore();
    }
    sorted.filter(item => item.depth >= 0).forEach(item => leg(item, 'lower'));
    ctx.restore();
}

function drawRobotLeg(ctx, {hip, knee, foot, far}, pass) {
    const palette = far ? ['#b4c4d4', '#e3e9ef', '#6e8195'] : ['#d2dbe5', '#fffdf9', '#8d9eb0'];
    function bone(from, to, width) {
        const gradient = ctx.createLinearGradient(from.x - width / 2, from.y, from.x + width / 2, from.y);
        gradient.addColorStop(0, palette[0]); gradient.addColorStop(.38, palette[1]); gradient.addColorStop(1, palette[2]);
        ctx.strokeStyle = '#27323d'; ctx.lineWidth = width + 2; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(from.x, from.y); ctx.lineTo(to.x, to.y); ctx.stroke();
        ctx.strokeStyle = gradient; ctx.lineWidth = width; ctx.stroke();
    }
    if (pass !== 'lower') bone(hip, knee, 13);
    if (pass !== 'upper') bone(knee, foot, 11);
    for (const joint of pass === 'upper' ? [hip] : pass === 'lower' ? [knee] : [hip, knee]) {
        ctx.fillStyle = '#28323e'; ctx.beginPath(); ctx.arc(joint.x, joint.y, 7, 0, 2 * Math.PI); ctx.fill();
        ctx.fillStyle = far ? '#11679f' : '#087bcd'; ctx.beginPath(); ctx.arc(joint.x, joint.y, 5, 0, 2 * Math.PI); ctx.fill();
        ctx.fillStyle = '#64c5ff'; ctx.beginPath(); ctx.arc(joint.x - 1.3, joint.y - 1.5, 1.8, 0, 2 * Math.PI); ctx.fill();
    }
    if (pass === 'upper') return;
    ctx.save(); ctx.translate(foot.x, foot.y); ctx.rotate(foot.angle);
    ctx.scale(foot.view === 'left' ? -1 : 1, 1);
    ctx.fillStyle = '#263544'; ctx.beginPath(); ctx.ellipse(2, 3, 16, 6, 0, 0, 2 * Math.PI); ctx.fill();
    const shell = ctx.createLinearGradient(0, -7, 0, 6);
    shell.addColorStop(0, palette[1]); shell.addColorStop(1, palette[0]);
    ctx.fillStyle = shell; ctx.beginPath(); ctx.ellipse(1, -1, 16, 8, 0, 0, 2 * Math.PI); ctx.fill();
    for (const toe of [-5, 1, 7]) {
        ctx.fillStyle = far ? '#08629a' : '#087cc8'; ctx.beginPath();
        if (foot.view === 'front') ctx.ellipse(toe, 3, 2.4, 4.5, 0, 0, 2 * Math.PI);
        else ctx.ellipse(11, toe / 2, 5.5, 2.3, -.15, 0, 2 * Math.PI);
        ctx.fill();
    }
    ctx.restore();
}
