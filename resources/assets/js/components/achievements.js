import * as bootstrap from 'bootstrap';

export default function initAchievements() {
    document.querySelectorAll('[data-ai-achievement-toggle]').forEach(function (toggle) {
        const form = toggle.closest('form') || document;
        const field = form.querySelector('[data-ai-achievement-field]');
        if (!field) return;

        const updateAiAchievementField = function () {
            field.hidden = !toggle.checked;
        };

        updateAiAchievementField();
        toggle.addEventListener('change', updateAiAchievementField);
    });

    (function initAchievementTrophyViewer() {
        const trophyButtons = document.querySelectorAll('[data-achievement-trophy-viewer], [data-collection-viewer]');
        if (!trophyButtons.length) return;

        let viewer = document.getElementById('achievement-trophy-viewer');
        if (!viewer) {
            viewer = document.createElement('div');
            viewer.className = 'modal fade achievement-trophy-viewer';
            viewer.id = 'achievement-trophy-viewer';
            viewer.tabIndex = -1;
            viewer.setAttribute('aria-hidden', 'true');
            viewer.innerHTML = [
                '<div class="modal-dialog modal-dialog-centered modal-lg">',
                '  <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">',
                '    <div class="modal-header border-bottom p-3">',
                '      <div class="min-width-0">',
                '        <div class="gc-eyebrow" data-trophy-viewer-kind>Кубок достижения</div>',
                '        <h5 class="modal-title text-truncate" id="achievement-trophy-viewer-title" data-trophy-viewer-title></h5>',
                '      </div>',
                '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>',
                '    </div>',
                '    <div class="modal-body p-3 p-md-4">',
                '      <div class="achievement-trophy-viewer__figure">',
                '        <img class="achievement-trophy-viewer__image" data-trophy-viewer-image alt="">',
                '      </div>',
                '      <p class="achievement-trophy-viewer__description mt-3 mb-0 text-muted" data-trophy-viewer-description></p>',
                '    </div>',
                '  </div>',
                '</div>'
            ].join('');
            document.body.appendChild(viewer);
        }

        const modal = new bootstrap.Modal(viewer);
        const title = viewer.querySelector('[data-trophy-viewer-title]');
        const description = viewer.querySelector('[data-trophy-viewer-description]');
        const image = viewer.querySelector('[data-trophy-viewer-image]');
        let lastTrigger;
        viewer.setAttribute('aria-labelledby', 'achievement-trophy-viewer-title');
        viewer.addEventListener('hidden.bs.modal', () => lastTrigger?.focus({preventScroll: true}));

        const launchConfetti = function (origin) {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const canvas = document.createElement('canvas');
            canvas.className = 'achievement-confetti-canvas';
            const context = canvas.getContext('2d');
            if (!context) return;

            const palette = getComputedStyle(document.documentElement);
            const colors = ['--gc-accent', '--gc-primary', '--gc-info'].map(name => palette.getPropertyValue(name).trim());
            const startedAt = performance.now();
            const duration = 1000;
            const originX = origin ? origin.x : window.innerWidth / 2;
            const originY = origin ? origin.y : Math.min(window.innerHeight * 0.48, 360);
            const pieces = Array.from({ length: 72 }, function (_, index) {
                const isSpark = index % 5 === 0;
                const angle = -Math.PI / 2 + (Math.random() - 0.5) * 2.25;
                const speed = 4.5 + Math.random() * 10;
                return {
                    x: originX + (Math.random() - 0.5) * 42,
                    y: originY + (Math.random() - 0.5) * 24,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    gravity: 0.18 + Math.random() * 0.12,
                    rotation: Math.random() * Math.PI,
                    rotationSpeed: (Math.random() - 0.5) * 0.28,
                    size: 6 + Math.random() * 8,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    spark: isSpark
                };
            });

            const resize = function () {
                const ratio = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
                canvas.width = window.innerWidth * ratio;
                canvas.height = window.innerHeight * ratio;
                canvas.style.width = window.innerWidth + 'px';
                canvas.style.height = window.innerHeight + 'px';
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
            };

            resize();
            document.body.appendChild(canvas);

            const draw = function (now) {
                const elapsed = now - startedAt;
                context.clearRect(0, 0, canvas.width, canvas.height);
                pieces.forEach(function (piece) {
                    piece.x += piece.vx;
                    piece.y += piece.vy;
                    piece.vy += piece.gravity;
                    piece.rotation += piece.rotationSpeed;
                    context.save();
                    context.translate(piece.x, piece.y);
                    context.rotate(piece.rotation);
                    context.globalAlpha = Math.max(0, 1 - elapsed / duration);
                    context.fillStyle = piece.color;
                    if (piece.spark) {
                        context.beginPath();
                        context.moveTo(0, -piece.size);
                        context.lineTo(piece.size * 0.24, -piece.size * 0.24);
                        context.lineTo(piece.size, 0);
                        context.lineTo(piece.size * 0.24, piece.size * 0.24);
                        context.lineTo(0, piece.size);
                        context.lineTo(-piece.size * 0.24, piece.size * 0.24);
                        context.lineTo(-piece.size, 0);
                        context.lineTo(-piece.size * 0.24, -piece.size * 0.24);
                        context.closePath();
                        context.fill();
                    } else {
                        context.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2);
                    }
                    context.restore();
                });

                if (elapsed < duration) {
                    requestAnimationFrame(draw);
                } else {
                    window.removeEventListener('resize', resize);
                    canvas.remove();
                }
            };

            window.addEventListener('resize', resize);
            requestAnimationFrame(draw);
        };

        trophyButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                lastTrigger = button;
                const trophyTitle = button.dataset.trophyTitle || 'Достижение';
                const trophyDescription = (button.dataset.trophyDescription || '').trim();
                const rect = button.getBoundingClientRect();
                viewer.classList.toggle('is-collection', button.hasAttribute('data-collection-viewer'));
                viewer.querySelector('[data-trophy-viewer-kind]').textContent = button.hasAttribute('data-collection-viewer') ? 'Наклейка за урок' : 'Кубок достижения';
                title.textContent = trophyTitle;
                description.textContent = trophyDescription;
                description.hidden = trophyDescription === '';
                image.src = button.dataset.trophySrc || '';
                image.alt = button.dataset.trophyAlt || 'Кубок «' + trophyTitle + '»';
                modal.show();
                if (!button.hasAttribute('data-collection-viewer')) launchConfetti({
                    x: event.clientX || rect.left + rect.width / 2,
                    y: event.clientY || rect.top + rect.height / 2
                });
            });
        });
    })();
}
