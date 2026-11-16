import { loadSidebarPatternGeometry } from './sidebar-pattern-light';
import { advanceRay, rayHoverCounter } from './sidebar-ray-model';

export default function initSidebarRay(sidebar) {
    const brand = sidebar?.querySelector('.gc-sidebar__brand');
    if (!brand || sidebar.dataset.rayReady) return;
    sidebar.dataset.rayReady = 'true';
    const entered = rayHoverCounter();
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let canvas, ctx, raf, pending = false, generation = 0, nextAllowed = 0;

    function stop() {
        generation++;
        pending = false;
        cancelAnimationFrame(raf);
        raf = null;
        canvas?.remove();
        canvas = null;
        sidebar.dataset.rayState = 'idle';
    }

    async function launch() {
        if (pending || canvas || reduced.matches || document.hidden || Date.now() < nextAllowed
            || document.querySelector('dialog[open], .modal.show, .offcanvas.show')) return;
        const rect = sidebar.getBoundingClientRect();
        if (rect.right <= 0 || rect.left >= innerWidth || sidebar.inert) return;
        pending = true;
        const current = ++generation;
        try {
            const geometry = await loadSidebarPatternGeometry();
            if (generation !== current || reduced.matches || document.hidden) return;
            const brandBottom = brand.getBoundingClientRect().bottom;
            const footer = sidebar.querySelector('.gc-sidebar__user')?.getBoundingClientRect().top ?? rect.bottom;
            const faces = [];
            for (let row = 0; row < rect.height; row += geometry.height) {
                for (let col = 0; col < rect.width; col += geometry.width) {
                    for (const face of geometry.faces) {
                        const points = face.map(([x, y]) => [rect.left + col + x, rect.top + row + y]);
                        if (points.every(([x, y]) => x >= rect.left + 8 && x <= rect.right - 8 && y >= brandBottom + 28 && y <= footer - 16)) faces.push(points);
                    }
                }
            }
            if (!faces.length) return;
            const face = faces[Math.floor(Math.random() * faces.length)];
            const width = innerWidth, height = innerHeight;
            canvas = document.createElement('canvas');
            canvas.className = 'gc-sidebar-ray';
            canvas.setAttribute('aria-hidden', 'true');
            document.body.append(canvas);
            const ratio = Math.min(devicePixelRatio || 1, 1.5, 2500 / width);
            canvas.width = Math.ceil(width * ratio);
            canvas.height = Math.ceil(height * ratio);
            ctx = canvas.getContext('2d');
            if (!ctx) { stop(); return; }
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            const color = getComputedStyle(canvas).color;
            const angle = (Math.random() > .5 ? 1 : -1) * (.35 + Math.random() * .45);
            let ray = {x: face.reduce((sum, point) => sum + point[0], 0) / 3,
                y: face.reduce((sum, point) => sum + point[1], 0) / 3,
                vx: Math.cos(angle) * 720, vy: Math.sin(angle) * 720, bounces: 0};
            const started = performance.now();
            let last = started, burstAt = null, burstAfter = null, particles = [], trail = [];
            nextAllowed = Date.now() + 90000;
            sidebar.dataset.rayState = 'flight';

            function frame(now) {
                const dt = Math.min(.05, (now - last) / 1000); last = now;
                ctx.clearRect(0, 0, width, height);
                ctx.strokeStyle = color; ctx.fillStyle = color; ctx.lineCap = 'round';
                ctx.shadowColor = color; ctx.shadowBlur = 12;
                if (burstAt == null) {
                    ray = advanceRay(ray, dt, width, height);
                    trail.push({...ray, at: now});
                    trail = trail.filter(point => now - point.at < 220);
                    for (let i = 1; i < trail.length; i++) {
                        ctx.globalAlpha = i / trail.length * .9;
                        ctx.lineWidth = 2 + i / trail.length * 3;
                        ctx.beginPath(); ctx.moveTo(trail[i - 1].x, trail[i - 1].y); ctx.lineTo(trail[i].x, trail[i].y); ctx.stroke();
                    }
                    ctx.globalAlpha = 1;
                    ctx.beginPath(); ctx.arc(ray.x, ray.y, 4, 0, Math.PI * 2); ctx.fill();
                    const pulse = Math.max(0, 1 - (now - started) / 500);
                    if (pulse) {
                        ctx.globalAlpha = pulse * .85; ctx.lineWidth = 2;
                        ctx.beginPath(); face.forEach(([x, y], index) => index ? ctx.lineTo(x, y) : ctx.moveTo(x, y)); ctx.closePath(); ctx.stroke();
                    }
                    // Let the last reflection carry the ray away from the wall before the burst.
                    if (burstAfter == null && (ray.bounces >= 4 || now - started >= 4800)) burstAfter = now + 240;
                    if (burstAfter != null && now >= burstAfter) {
                        burstAt = now; sidebar.dataset.rayState = 'fireworks';
                        particles = Array.from({length: 72}, (_, i) => {
                            const a = i / 72 * Math.PI * 2 + Math.random() * .12;
                            const speed = 110 + Math.random() * 280;
                            return {x: ray.x, y: ray.y, vx: Math.cos(a) * speed, vy: Math.sin(a) * speed, life: 700 + Math.random() * 600};
                        });
                    }
                } else {
                    for (const particle of particles) {
                        const age = now - burstAt;
                        particle.x += particle.vx * dt; particle.y += particle.vy * dt; particle.vy += 160 * dt;
                        ctx.globalAlpha = Math.max(0, 1 - age / particle.life);
                        ctx.lineWidth = 1.7;
                        ctx.beginPath(); ctx.moveTo(particle.x, particle.y); ctx.lineTo(particle.x - particle.vx * .024, particle.y - particle.vy * .024); ctx.stroke();
                    }
                    if (now - burstAt >= 1350) { stop(); return; }
                }
                ctx.globalAlpha = 1;
                raf = requestAnimationFrame(frame);
            }
            raf = requestAnimationFrame(frame);
        } catch { stop(); }
        finally { if (generation === current) pending = false; }
    }

    brand.addEventListener('pointerenter', event => { if (event.pointerType !== 'touch' && entered()) launch(); });
    brand.addEventListener('focus', () => { if (brand.matches(':focus-visible') && entered()) launch(); });
    reduced.addEventListener('change', stop);
    window.addEventListener('resize', stop, {passive: true});
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
    sidebar.dataset.rayState = 'idle';
}
