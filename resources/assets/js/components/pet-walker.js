import { drawRig } from './pet-rig-renderer.js';

const clamp = (value, low = 0, high = 1) => Math.max(low, Math.min(high, value));
const smooth = t => t * t * (3 - 2 * t);

export class PetWalker {
    constructor(host, image, data, options = {}) {
        Object.assign(this, {host, image, data, stage: options.stage});
        this.direction = 1;
        this.x = null;
        this.mode = 'stand';
        this.phase = 0;
        this.elapsed = 0;
        this.playing = false;
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
        const nav = this.stage ? null : document.querySelector('.gc-bottom-nav');
        const navTop = nav?.getBoundingClientRect().top;
        const bottom = nav?.getClientRects().length ? Math.max(0, innerHeight - navTop) : 0;
        this.host.style.setProperty('--screen-pet-bottom', `${bottom}px`);
        this.host.style.setProperty('--screen-pet-size', `${this.size}px`);
        this.host.style.setProperty('--screen-pet-height', `${this.height}px`);
        const ratio = Math.min(devicePixelRatio || 1, 2);
        this.canvas.width = Math.round(this.size * ratio);
        this.canvas.height = Math.round(this.height * ratio);
        this.ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
        if (this.playing && ['walk', 'prepare'].includes(this.mode)) this.beginLeg();
        this.draw();
    }

    destination() { return this.visit.stage === 'back' ? this.visit.home : this.visit.center; }

    setMode(mode) { this.mode = mode; this.elapsed = 0; }

    beginLeg() {
        this.origin = this.x;
        this.distance = this.destination() - this.x;
        this.cycles = Math.max(1, Math.ceil(Math.abs(this.distance) / (this.data.rig.stride * this.size / 256)));
        // Scale actual foot placement as well as body travel for the short final leg.
        this.stride = Math.abs(this.distance) * 256 / (this.size * this.cycles);
        this.duration = Math.min(30000, this.cycles * this.data.rig.cadence);
        this.phase = 0;
        this.setMode('prepare');
    }

    startVisit(onComplete, random = Math.random) {
        this.pause();
        this.resize();
        this.direction = random() < .5 ? 1 : -1;
        this.x = this.direction > 0 ? this.left : this.right;
        const width = this.stage ? this.stage.clientWidth : innerWidth;
        this.visit = {stage: 'out', home: this.x, center: clamp(width / 2, this.left, this.right), entryDirection: this.direction, onComplete};
        this.beginLeg();
        this.playing = true;
        this.last = null;
        this.draw();
        this.nextFrame();
    }

    nextFrame() { this.raf = requestAnimationFrame(now => this.tick(now)); }

    tick(now) {
        if (!this.playing) return;
        const dt = this.last == null ? 0 : Math.min(50, now - this.last);
        this.last = now;
        this.elapsed += dt;
        const reposition = this.data.rig.legs.length * 150;
        if (this.mode === 'prepare') {
            this.draw();
            if (this.elapsed >= reposition) this.setMode('walk');
        } else if (this.mode === 'walk') {
            const progress = smooth(clamp(this.elapsed / this.duration));
            this.x = this.origin + this.distance * progress;
            this.phase = this.cycles * progress;
            this.draw();
            if (this.elapsed >= this.duration) {
                this.x = this.destination();
                this.phase = 0;
                this.setMode('settle');
            }
        } else if (this.mode === 'settle') {
            this.draw();
            if (this.elapsed >= reposition) {
                if (this.onSettle || this.visit.stage === 'back') {
                    const callback = this.onSettle || this.visit.onComplete;
                    this.pause(); callback?.(); return;
                }
                this.visit.stage = 'pause';
                this.setMode('stand');
            }
        } else if (this.mode === 'stand') {
            this.draw();
            if (this.elapsed >= (this.data.visitPause ?? 1800)) this.setMode('turn');
        } else if (this.mode === 'turn') {
            this.draw();
            if (this.elapsed >= this.data.rig.turnDuration) {
                this.direction *= -1;
                this.visit.stage = 'back';
                this.beginLeg();
            }
        }
        this.nextFrame();
    }

    draw() {
        if (!this.ctx) return;
        this.host.style.transform = `translate3d(${this.x - this.size / 2}px,0,0)`;
        if (this.host.dataset) this.host.dataset.petStage = this.mode === 'turn' ? 'turn' : this.visit?.stage || 'idle';
        const duration = this.mode === 'turn' ? this.data.rig.turnDuration : this.data.rig.legs.length * 150;
        drawRig(this.ctx, this.image, this.data, {size: this.size, height: this.height, direction: this.direction,
            phase: this.phase, state: this.mode, progress: clamp(this.elapsed / duration), stride: this.stride ?? this.data.rig.stride, time: this.elapsed});
    }

    pause() {
        this.playing = false;
        this.onSettle = null;
        this.visit = null;
        cancelAnimationFrame(this.raf);
        this.phase = 0;
        this.setMode('stand');
        this.draw();
    }

    settle(callback) {
        if (!this.playing || this.mode !== 'walk') { this.pause(); callback(); }
        else this.onSettle = callback;
    }

    destroy() { this.pause(); this.canvas.remove(); }
}
