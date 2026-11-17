import { PetHopper } from './pet-hop';
import { PetWalker } from './pet-walker';
import { PetVisits } from './pet-visits';

export default function initScreenPet() {
    const host = document.querySelector('[data-screen-pet]');
    if (!host || host.dataset.petReady) return;
    host.dataset.petReady = 'true';
    let data;
    try { data = JSON.parse(host.dataset.screenPet); } catch { return; }
    if (!data?.frames?.length || !data.url) return;
    const controls = [...document.querySelectorAll('[data-screen-pet-toggle]')];
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const key = `gc-screen-pet:${data.userId}`;
    // Discard the shorter schedules saved before rare visits were introduced.
    const nextKey = `${key}:next:v2`;
    const read = name => { try { return localStorage.getItem(name); } catch { return null; } };
    const save = (name, value) => { try { localStorage.setItem(name, value); } catch { /* Optional local preference. */ } };
    let enabled = read(key) !== 'off';
    let pet, imagePromise, failed = false, suspended = false;
    let generation = 0, exitTimer;

    function blocked() {
        return suspended || document.hidden
            || !!document.querySelector('dialog[open], .modal.show, .offcanvas.show, #gcSidebar.show')
            || !!document.activeElement?.closest('input, textarea, select, [contenteditable="true"], .cm-editor')
            || (window.visualViewport && innerHeight - visualViewport.height > 150);
    }

    function hide() {
        clearTimeout(exitTimer);
        host.hidden = true;
        host.classList.remove('is-arriving', 'is-leaving');
        host.dataset.petVisit = 'idle';
        pet?.pause();
    }

    async function arrive() {
        const current = ++generation;
        clearTimeout(exitTimer);
        try {
            if (!imagePromise) imagePromise = new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = () => resolve(image);
                image.onerror = reject;
                image.src = data.url;
            });
            const image = await imagePromise;
            if (current !== generation || !visits.active || blocked()) return;
            if (!pet) pet = data.motion === 'rig' ? new PetWalker(host, image, data) : new PetHopper(host, image, data);
            host.classList.remove('is-leaving', 'is-arriving');
            host.hidden = false;
            host.dataset.petVisit = 'hopping';
            host.classList.add('is-arriving');
            pet.startVisit(() => visits.complete());
        } catch {
            failed = true;
            visits.cancel();
            hide();
            sync();
        }
    }

    function leave(reason) {
        const current = ++generation;
        if (reason !== 'finished' || blocked() || !pet || host.hidden) { hide(); return; }
        pet.settle(() => {
            if (current !== generation) return;
            host.classList.remove('is-arriving');
            host.classList.add('is-leaving');
            host.dataset.petVisit = 'leaving';
            exitTimer = setTimeout(() => { if (current === generation) hide(); }, 350);
        });
        queueMicrotask(sync);
    }

    const visits = new PetVisits({
        onStart: arrive,
        onStop: leave,
        nextAt: Number(read(nextKey)) || 0,
        onNext: nextAt => save(nextKey, String(nextAt)),
    });

    function sync() {
        controls.forEach(input => {
            input.checked = enabled && !failed;
            input.disabled = failed;
        });
        const available = enabled && !failed && !blocked() && !reduced.matches;
        visits.setAvailable(available);
        if (!available) hide();
    }

    controls.forEach(input => input.addEventListener('change', () => {
        enabled = input.checked;
        save(key, enabled ? 'on' : 'off');
        if (!enabled) { visits.cancel(); hide(); }
        sync();
    }));
    reduced.addEventListener('change', sync);
    document.addEventListener('visibilitychange', sync);
    document.addEventListener('focusin', sync);
    document.addEventListener('focusout', () => queueMicrotask(sync));
    for (const event of ['show.bs.modal', 'shown.bs.modal', 'hidden.bs.modal', 'show.bs.offcanvas', 'shown.bs.offcanvas', 'hidden.bs.offcanvas', 'toggle']) {
        document.addEventListener(event, () => queueMicrotask(sync), true);
    }
    const sidebar = document.getElementById('gcSidebar');
    const observer = sidebar ? new MutationObserver(sync) : null;
    observer?.observe(sidebar, {attributes: true, attributeFilter: ['class']});
    const resize = () => { pet?.resize(); sync(); };
    window.addEventListener('resize', resize, {passive: true});
    window.visualViewport?.addEventListener('resize', resize, {passive: true});
    window.addEventListener('storage', event => { if (event.key === key) { enabled = event.newValue !== 'off'; sync(); } });
    window.addEventListener('pagehide', () => { suspended = true; visits.cancel(); hide(); });
    window.addEventListener('pageshow', () => { suspended = false; sync(); });
    host.dataset.petVisit = 'idle';
    sync();
    if (data.preview) visits.start();
}
