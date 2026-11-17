const clamp = (value, low, high) => Math.max(low, Math.min(high, value));

export function hopPose(phase, frameCount = 16) {
    const time = clamp(phase, 0, 1);
    const flight = clamp((time - .22) / .46, 0, 1);
    return {frame: Math.min(frameCount - 1, Math.floor(time * frameCount)), progress: flight, height: 4 * flight * (1 - flight)};
}

export function locomotionPose(phase, data) {
    if (data.motion === 'hop') return hopPose(phase, data.frames.length);
    const time = clamp(phase, 0, 1);
    return {frame: Math.min(data.frames.length - 1, Math.floor(time * data.frames.length)), progress: time, height: 0};
}

export class PetHopper {
    constructor(host, image, data, options = {}) {
        Object.assign(this, {host, image, data, stage: options.stage});
        this.direction = 1;
        this.x = null;
        this.elapsed = 0;
        this.playing = false;
        this.visit = null;
        this.canvas = document.createElement('canvas');
        this.ctx = this.canvas.getContext('2d');
        host.append(this.canvas);
        this.resize();
    }

    resize() {
        const sidebar = this.stage ? null : document.querySelector('#gcSidebar, .gc-workbook-sidebar');
        const side = sidebar?.getBoundingClientRect();
        const width = this.stage ? this.stage.clientWidth : innerWidth;
        const start = side && innerWidth >= 992 && side.right > 0 ? side.right : 0;
        this.size = width < 480 ? this.data.mobileSize || 80 : this.data.size || 96;
        this.height = this.size + 30;
        this.left = start + this.size / 2 + 10;
        this.right = Math.max(this.left, width - this.size / 2 - 10);
        this.x = clamp(this.x ?? this.left, this.left, this.right);
        if (this.visit) {
            this.visit.home = this.visit.entryDirection > 0 ? this.left : this.right;
            this.visit.center = clamp(width / 2, this.left, this.right);
            if (this.visit.stage === 'pause') this.x = this.visit.center;
        }
        this.origin = this.x;
        this.elapsed = 0;
        this.target = this.nextTarget();
        const nav = this.stage ? null : document.querySelector('.gc-bottom-nav');
        const navTop = nav?.getBoundingClientRect().top;
        const bottom = nav?.getClientRects().length ? Math.max(0, innerHeight - navTop) : 0;
        this.host.style.setProperty('--screen-pet-bottom', `${bottom}px`);
        this.host.style.setProperty('--screen-pet-size', `${this.size}px`);
        this.host.style.setProperty('--screen-pet-height', `${this.height}px`);
        const ratio = Math.min(devicePixelRatio || 1, 2);
        this.canvas.width = Math.round(this.size * ratio);
        this.canvas.height = Math.round(this.height * ratio);
        this.lastPaint = null;
        this.ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.draw({frame: 0, height: 0});
    }

    destination() { return this.visit?.stage === 'back' ? this.visit.home : this.visit?.center; }

    nextTarget() {
        const stride = this.size * (this.data.stride ?? .65);
        if (this.visit) {
            const destination = this.destination();
            return this.x + Math.sign(destination - this.x) * Math.min(stride, Math.abs(destination - this.x));
        }
        return clamp(this.x + this.direction * stride, this.left, this.right);
    }

    play() {
        if (this.playing || !this.ctx) return;
        this.playing = true;
        this.onSettle = null;
        this.last = null;
        this.origin = this.x;
        this.elapsed = -(this.data.rest ?? 400);
        this.target = this.nextTarget();
        this.raf = requestAnimationFrame(now => this.tick(now));
    }

    pause() {
        this.playing = false;
        this.onSettle = null;
        this.visit = null;
        cancelAnimationFrame(this.raf);
        this.elapsed = 0;
        this.draw({frame: 0, height: 0});
    }

    startVisit(onComplete, random = Math.random) {
        this.pause();
        this.resize();
        this.direction = random() < .5 ? 1 : -1;
        this.x = this.direction > 0 ? this.left : this.right;
        const width = this.stage ? this.stage.clientWidth : innerWidth;
        this.visit = {stage: 'out', home: this.x, center: clamp(width / 2, this.left, this.right), entryDirection: this.direction, onComplete};
        this.draw({frame: 0, height: 0});
        this.play();
    }

    settle(callback) {
        if (!this.playing || this.elapsed <= 0 || this.elapsed >= (this.data.duration ?? 1040)) {
            this.pause(); callback();
        } else this.onSettle = callback;
    }

    tick(now) {
        if (!this.playing) return;
        const dt = this.last == null ? 0 : Math.min(50, now - this.last);
        this.last = now;
        this.elapsed += dt;
        const duration = this.data.duration ?? 1040;
        if (this.onSettle && this.elapsed >= duration) {
            const callback = this.onSettle;
            this.x = this.target;
            this.pause(); callback(); return;
        }
        if (this.visit?.stage === 'pause') {
            if (this.elapsed >= (this.data.visitPause ?? 1800)) {
                if (this.data.turn) {
                    this.visit.stage = 'turn';
                    this.elapsed = 0;
                } else {
                    this.visit.stage = 'back';
                    this.direction = -this.visit.entryDirection;
                    this.elapsed = 0;
                    this.origin = this.x;
                    this.target = this.nextTarget();
                }
            }
            this.draw({frame: 0, height: 0});
            this.raf = requestAnimationFrame(time => this.tick(time));
            return;
        }
        if (this.visit?.stage === 'turn') {
            const sequence = this.data.turn[this.direction > 0 ? 'right' : 'left'];
            const progress = clamp(this.elapsed / this.data.turnDuration, 0, 1) * (sequence.length - 1);
            const index = Math.min(sequence.length - 2, Math.floor(progress));
            this.draw({frame: 0, height: 0, layers: [
                {sprite: sequence[index], opacity: 1 - (progress - index)},
                {sprite: sequence[index + 1], opacity: progress - index},
            ]});
            if (this.elapsed >= this.data.turnDuration) {
                this.visit.stage = 'back';
                this.direction = -this.visit.entryDirection;
                this.elapsed = 0;
                this.origin = this.x;
                this.target = this.nextTarget();
            }
            this.raf = requestAnimationFrame(time => this.tick(time));
            return;
        }
        const period = duration + (this.data.rest ?? 400);
        if (this.elapsed >= period) {
            this.x = this.target;
            if (this.visit && Math.abs(this.x - this.destination()) < .1) {
                if (this.visit.stage === 'out') {
                    this.visit.stage = 'pause';
                    this.elapsed = 0;
                    this.draw({frame: 0, height: 0});
                    this.raf = requestAnimationFrame(time => this.tick(time));
                } else {
                    const callback = this.visit.onComplete;
                    this.pause(); callback?.();
                }
                return;
            }
            if (!this.visit && Math.abs(this.x - (this.direction > 0 ? this.right : this.left)) < .1) this.direction *= -1;
            this.origin = this.x;
            this.target = this.nextTarget();
            this.elapsed %= period;
        }
        const pose = locomotionPose(this.elapsed / duration, this.data);
        this.x = this.origin + (this.target - this.origin) * pose.progress;
        this.draw(pose);
        this.raf = requestAnimationFrame(time => this.tick(time));
    }

    draw(pose) {
        if (!this.ctx) return;
        const stage = this.visit?.stage || 'idle';
        if (this.host.dataset && this.host.dataset.petStage !== stage) this.host.dataset.petStage = stage;
        const {cell, columns, baseline, frames} = this.data;
        const sourceScale = this.image.naturalWidth / (cell * columns);
        const scale = this.size / cell;
        this.host.style.transform = `translate3d(${this.x - this.size / 2}px,0,0)`;
        const paint = JSON.stringify([pose, this.direction]);
        if (paint === this.lastPaint) return;
        this.lastPaint = paint;
        this.ctx.clearRect(0, 0, this.size, this.height);
        for (const layer of pose.layers || [{sprite: frames[pose.frame], opacity: 1}]) {
            if (layer.opacity <= 0) continue;
            const frame = layer.sprite;
            this.ctx.save();
            this.ctx.globalAlpha = layer.opacity;
            this.ctx.translate(this.size / 2, this.height - 1 - pose.height * this.size * .15);
            const facing = frame.flip == null ? this.direction * (this.data.facing ?? 1) : (frame.flip ? -1 : 1);
            this.ctx.scale(facing, 1);
            this.ctx.drawImage(this.image, frame.x * sourceScale, frame.y * sourceScale, cell * sourceScale, cell * sourceScale,
                -this.size / 2 + (frame.dx || 0) * scale, -baseline * scale + (frame.dy || 0) * scale, this.size, this.size);
            this.ctx.restore();
        }
    }

    destroy() { this.pause(); this.canvas.remove(); }
}
