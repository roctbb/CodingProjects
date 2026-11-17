// Injectable timers let tests verify rare visits without waiting minutes.
export class PetVisits {
    constructor({onStart, onStop, onNext = () => {}, nextAt = 0, random = Math.random,
        now = Date.now, setTimer = (callback, delay) => setTimeout(callback, delay), clearTimer = id => clearTimeout(id)}) {
        Object.assign(this, {onStart, onStop, onNext, random, now, setTimer, clearTimer});
        this.available = false;
        this.active = false;
        this.timer = null;
        this.visitTimer = null;
        this.remaining = nextAt > now() ? nextAt - now() : null;
    }
    delay() { return 1800000 + this.random() * 1800000; }
    setAvailable(available) {
        if (available === this.available) return;
        this.available = available;
        if (!available) {
            if (this.timer != null) {
                this.remaining = Math.max(0, this.dueAt - this.now());
                this.clearTimer(this.timer);
                this.timer = null;
            }
            if (this.active) this.finish('interrupted');
        } else this.schedule();
    }
    schedule() {
        if (!this.available || this.active || this.timer != null) return;
        const delay = this.remaining ?? this.delay();
        this.remaining = null;
        this.dueAt = this.now() + delay;
        this.onNext(this.dueAt);
        this.timer = this.setTimer(() => { this.timer = null; this.start(); }, delay);
    }
    start() {
        if (!this.available || this.active) return;
        if (this.timer != null) this.clearTimer(this.timer);
        this.timer = null;
        this.active = true;
        this.remaining = this.delay();
        this.onNext(this.now() + this.remaining);
        this.onStart();
        // Normally the sprite completes its route; this only bounds a stalled visit.
        this.visitTimer = this.setTimer(() => this.finish('finished'), 180000);
    }
    finish(reason) {
        if (!this.active) return;
        this.active = false;
        this.clearTimer(this.visitTimer);
        this.visitTimer = null;
        this.onStop(reason);
        this.schedule();
    }
    complete() { this.finish('finished'); }
    cancel() { this.setAvailable(false); }
}
