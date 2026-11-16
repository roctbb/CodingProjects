const PATTERN_URL = '/images/sidebar-pattern.svg';
let geometryPromise;

function loadGeometry() {
    if (!geometryPromise) {
        geometryPromise = fetch(PATTERN_URL).then(response => {
            if (!response.ok) throw new Error('Sidebar pattern unavailable');
            return response.text();
        }).then(source => {
            const svg = new DOMParser().parseFromString(source, 'image/svg+xml').documentElement;
            const viewBox = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
            const width = parseFloat(svg.getAttribute('width'));
            const height = parseFloat(svg.getAttribute('height'));
            if (viewBox.length !== 4 || !viewBox.every(Number.isFinite) || !(viewBox[2] > 0 && viewBox[3] > 0 && width > 0 && height > 0)) {
                throw new Error('Invalid sidebar pattern');
            }
            const faces = [...svg.querySelectorAll('polygon')].flatMap(polygon => {
                const values = (polygon.getAttribute('points') || '').trim().split(/[\s,]+/).map(Number);
                if (values.length !== 6 || !values.every(Number.isFinite)) return [];
                return [Array.from({length: 3}, (_, i) => [
                    (values[i * 2] - viewBox[0]) * width / viewBox[2],
                    (values[i * 2 + 1] - viewBox[1]) * height / viewBox[3],
                ])];
            });
            return {width, height, faces};
        }).catch(error => {
            geometryPromise = null;
            throw error;
        });
    }
    return geometryPromise;
}

function selectFaces(geometry, width, top, bottom, previous) {
    const faces = [];
    for (let y = 0; y < bottom; y += geometry.height) {
        for (let x = 0; x < width; x += geometry.width) {
            geometry.faces.forEach((face, index) => {
                const points = face.map(point => [point[0] + x, point[1] + y]);
                if (!points.every(point => point[0] >= 8 && point[0] <= width - 8 && point[1] >= top && point[1] <= bottom)) return;
                faces.push({id: `${x}:${y}:${index}`, points: [...points, points[0]], center: [
                    points.reduce((sum, point) => sum + point[0], 0) / 3,
                    points.reduce((sum, point) => sum + point[1], 0) / 3,
                ]});
            });
        }
    }
    const fresh = faces.filter(face => !previous.has(face.id));
    const pool = fresh.length >= 3 ? fresh : faces;
    for (let i = pool.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [pool[i], pool[j]] = [pool[j], pool[i]];
    }
    const selected = [];
    for (const face of pool) {
        if (selected.every(other => Math.hypot(face.center[0] - other.center[0], face.center[1] - other.center[1]) > 60)) selected.push(face);
        if (selected.length === 3) break;
    }
    for (const face of pool) {
        if (selected.length === 3) break;
        if (!selected.includes(face)) selected.push(face);
    }
    return selected;
}

function trace(context, points, start, end) {
    let offset = 0;
    context.beginPath();
    for (let i = 1; i < points.length; i++) {
        const a = points[i - 1], b = points[i];
        const length = Math.hypot(b[0] - a[0], b[1] - a[1]);
        const low = Math.max(start, offset), high = Math.min(end, offset + length);
        if (high > low) {
            const from = (low - offset) / length, to = (high - offset) / length;
            context.moveTo(a[0] + (b[0] - a[0]) * from, a[1] + (b[1] - a[1]) * from);
            context.lineTo(a[0] + (b[0] - a[0]) * to, a[1] + (b[1] - a[1]) * to);
        }
        offset += length;
    }
    context.stroke();
}

export default function initSidebarPatternLight(sidebar) {
    const brand = sidebar?.querySelector('.gc-sidebar__brand');
    if (!brand || sidebar.dataset.patternLightReady) return;
    sidebar.dataset.patternLightReady = 'true';

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let canvas, context, animation = 0, pending = false, generation = 0;
    let previous = new Set();

    function stop() {
        generation++;
        pending = false;
        cancelAnimationFrame(animation);
        animation = 0;
        if (canvas) {
            context?.clearRect(0, 0, sidebar.clientWidth, sidebar.clientHeight);
            canvas.hidden = true;
        }
    }

    async function start() {
        if (pending || animation || reduced.matches || document.hidden || sidebar.inert) return;
        const bounds = sidebar.getBoundingClientRect();
        if (bounds.right <= 0 || bounds.left >= window.innerWidth || !getComputedStyle(sidebar).backgroundImage.includes('sidebar-pattern.svg')) return;
        pending = true;
        const current = ++generation;
        try {
            const geometry = await loadGeometry();
            if (current !== generation || reduced.matches || document.hidden || sidebar.inert || !brand.matches(':hover, :focus-visible')) return;
            const width = sidebar.clientWidth, height = sidebar.clientHeight;
            const sidebarTop = sidebar.getBoundingClientRect().top;
            const top = Math.max(170, brand.getBoundingClientRect().bottom - sidebarTop + 80);
            const footer = sidebar.querySelector('.gc-sidebar__user');
            const bottom = Math.min(height - 16, footer ? footer.getBoundingClientRect().top - sidebarTop - 12 : height - 16);
            const faces = selectFaces(geometry, width, top, bottom, previous);
            if (!faces.length) return;

            if (!canvas) {
                canvas = document.createElement('canvas');
                canvas.className = 'gc-sidebar-pattern-light';
                canvas.setAttribute('aria-hidden', 'true');
                canvas.hidden = true;
                sidebar.append(canvas);
                context = canvas.getContext('2d');
            }
            if (!context) return;
            const ratio = Math.min(window.devicePixelRatio || 1, 2);
            canvas.width = Math.ceil(width * ratio);
            canvas.height = Math.ceil(height * ratio);
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            canvas.hidden = false;
            previous = new Set(faces.map(face => face.id));
            const color = getComputedStyle(canvas).color;
            const started = performance.now();
            const paths = faces.map(face => ({points: face.points, length: face.points.slice(1).reduce((sum, point, index) =>
                sum + Math.hypot(point[0] - face.points[index][0], point[1] - face.points[index][1]), 0)}));

            function frame(now) {
                const progress = (now - started) / 1200;
                context.clearRect(0, 0, width, height);
                context.strokeStyle = color;
                context.lineWidth = 1.15;
                context.lineCap = 'round';
                context.shadowColor = color;
                context.shadowBlur = 5;
                paths.forEach((path, index) => {
                    const local = (progress - index * 0.14) / 0.68;
                    if (local < 0 || local > 1) return;
                    const head = local * path.length * 1.24;
                    context.globalAlpha = Math.sin(Math.PI * local) * 0.72;
                    trace(context, path.points, Math.max(0, head - path.length * 0.24), Math.min(head, path.length));
                });
                context.globalAlpha = 1;
                if (progress < 1.08) animation = requestAnimationFrame(frame);
                else stop();
            }
            animation = requestAnimationFrame(frame);
        } catch {
            // A decorative asset must never interrupt navigation.
        } finally {
            if (current === generation) pending = false;
        }
    }

    brand.addEventListener('pointerenter', event => { if (event.pointerType !== 'touch') start(); });
    brand.addEventListener('focus', () => { if (brand.matches(':focus-visible')) start(); });
    reduced.addEventListener('change', stop);
    window.addEventListener('resize', stop, {passive: true});
    document.addEventListener('visibilitychange', () => { if (document.hidden) stop(); });
}
