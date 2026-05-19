import axios from 'axios';
import autosize from 'autosize';
import flatpickr from 'flatpickr';
import { Russian } from 'flatpickr/dist/l10n/ru.js';
import hljs from 'highlight.js/lib/core';
import python from 'highlight.js/lib/languages/python';
import javascript from 'highlight.js/lib/languages/javascript';
import xml from 'highlight.js/lib/languages/xml';
import css from 'highlight.js/lib/languages/css';
import json from 'highlight.js/lib/languages/json';
import bash from 'highlight.js/lib/languages/bash';
import sql from 'highlight.js/lib/languages/sql';
import cpp from 'highlight.js/lib/languages/cpp';
import java from 'highlight.js/lib/languages/java';
import csharp from 'highlight.js/lib/languages/csharp';

hljs.registerLanguage('python', python);
hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('css', css);
hljs.registerLanguage('json', json);
hljs.registerLanguage('bash', bash);
hljs.registerLanguage('sql', sql);
hljs.registerLanguage('cpp', cpp);
hljs.registerLanguage('java', java);
hljs.registerLanguage('csharp', csharp);
import linkifyHtml from 'linkify-html';
import * as bootstrap from 'bootstrap';

window.axios = axios;
window.autosize = autosize;
window.flatpickr = flatpickr;
window.flatpickr.localize(Russian);
window.hljs = hljs;
window.bootstrap = bootstrap;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

var token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

document.addEventListener('DOMContentLoaded', function () {
    // Auto-init toasts
    document.querySelectorAll('.toast[data-bs-autohide]').forEach(function (el) {
        new bootstrap.Toast(el).show();
    });

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
        const trophyButtons = document.querySelectorAll('[data-achievement-trophy-viewer]');
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
                '        <div class="gc-eyebrow">Кубок достижения</div>',
                '        <h5 class="modal-title text-truncate" id="achievement-trophy-viewer-title" data-trophy-viewer-title></h5>',
                '      </div>',
                '      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>',
                '    </div>',
                '    <div class="modal-body p-3 p-md-4">',
                '      <div class="achievement-trophy-viewer__figure">',
                '        <span class="achievement-trophy-viewer__shine" aria-hidden="true"></span>',
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
        viewer.setAttribute('aria-labelledby', 'achievement-trophy-viewer-title');

        const launchConfetti = function (origin) {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

            const canvas = document.createElement('canvas');
            canvas.className = 'achievement-confetti-canvas';
            const context = canvas.getContext('2d');
            if (!context) return;

            const colors = ['#f59e0b', '#facc15', '#fde68a', '#22c55e', '#38bdf8', '#8b5cf6', '#ef4444'];
            const startedAt = performance.now();
            const duration = 1500;
            const originX = origin ? origin.x : window.innerWidth / 2;
            const originY = origin ? origin.y : Math.min(window.innerHeight * 0.48, 360);
            const pieces = Array.from({ length: 150 }, function (_, index) {
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
                const trophyTitle = button.dataset.trophyTitle || 'Достижение';
                const trophyDescription = (button.dataset.trophyDescription || '').trim();
                const rect = button.getBoundingClientRect();
                title.textContent = trophyTitle;
                description.textContent = trophyDescription;
                description.hidden = trophyDescription === '';
                image.src = button.dataset.trophySrc || '';
                image.alt = 'Кубок «' + trophyTitle + '»';
                modal.show();
                launchConfetti({
                    x: event.clientX || rect.left + rect.width / 2,
                    y: event.clientY || rect.top + rect.height / 2
                });
            });
        });
    })();

    document.querySelectorAll('[data-learning-avatar-form]').forEach(function (form) {
        const preview = document.getElementById(form.dataset.learningAvatarPreviewTarget);
        if (!preview) return;
        const balance = Number(form.dataset.learningAvatarBalance || 0);
        const summary = form.querySelector('[data-learning-avatar-cost-summary]');
        const submit = form.querySelector('[data-learning-avatar-submit]');
        const characterPreviewPayload = form.querySelector('[data-learning-avatar-character-previews]');
        let characterPreviewsByGrade = {};
        if (characterPreviewPayload) {
            try {
                characterPreviewsByGrade = JSON.parse(characterPreviewPayload.textContent || '{}') || {};
            } catch (error) {
                characterPreviewsByGrade = {};
            }
        }
        const escapeLearningAvatarSelectorValue = function (value) {
            return window.CSS && CSS.escape ? CSS.escape(value) : String(value).replace(/"/g, '\\"');
        };
        const insertLearningAvatarLayer = function (layer) {
            const order = Number(layer.dataset.learningAvatarLayerOrder || 99);
            const nextLayer = Array.from(preview.querySelectorAll('[data-learning-avatar-layer-order]')).find(function (existingLayer) {
                return Number(existingLayer.dataset.learningAvatarLayerOrder || 99) > order;
            });

            preview.insertBefore(layer, nextLayer || null);
        };

        const updateLearningAvatarSlot = function (select) {
            const selectedOption = select.options[select.selectedIndex];
            const equippedSlot = select.name.match(/^equipped\[([^\]]+)\]$/)?.[1];
            if (!equippedSlot) return;

            preview.querySelectorAll('[data-learning-avatar-layer-slot="' + escapeLearningAvatarSelectorValue(equippedSlot) + '"]').forEach(function (layer) {
                layer.remove();
            });

            if (!selectedOption || !selectedOption.value || !selectedOption.dataset.previewSrc) {
                return;
            }

            const slot = document.createElement('span');
            slot.className = 'gc-learning-avatar__slot gc-learning-avatar__slot--' + selectedOption.dataset.previewSlot;
            slot.dataset.learningAvatarLayerSlot = equippedSlot;
            slot.dataset.learningAvatarLayerOrder = selectedOption.dataset.previewOrder || '99';
            slot.setAttribute('style', selectedOption.dataset.previewStyle || '');

            const image = document.createElement('img');
            image.src = selectedOption.dataset.previewSrc;
            image.alt = '';
            image.loading = 'lazy';
            image.className = 'gc-learning-avatar__layer gc-learning-avatar__layer--item';
            image.style.objectFit = selectedOption.dataset.previewFit || 'contain';
            image.style.objectPosition = selectedOption.dataset.previewObjectPosition || 'center center';

            slot.appendChild(image);
            insertLearningAvatarLayer(slot);
        };

        const characterPreviewFromLayer = function (layer) {
            if (!layer || !layer.src) return null;

            return {
                src: layer.src,
                order: layer.order || '99',
                fullCanvas: layer.fullCanvas ? '1' : '0',
                slot: layer.slot || 'character',
                style: layer.style || '',
                innerStyle: layer.innerStyle || '',
                fit: layer.fit === 'cover' ? 'cover' : 'contain',
                objectPosition: layer.objectPosition || 'center bottom'
            };
        };

        const selectedCharacterPreview = function () {
            const genderSelect = form.querySelector('select[name="appearance[gender]"]');
            const gradeSelect = form.querySelector('select[name="appearance[grade]"]');
            const gender = genderSelect ? genderSelect.value : 'boy';
            const grade = gradeSelect ? gradeSelect.value : null;
            const gradePreview = grade ? characterPreviewsByGrade[grade]?.[gender] : null;

            if (gradePreview) {
                return characterPreviewFromLayer(gradePreview);
            }

            const selectedOption = genderSelect?.options[genderSelect.selectedIndex];
            if (!selectedOption || !selectedOption.dataset.characterSrc) return null;

            return {
                src: selectedOption.dataset.characterSrc,
                order: selectedOption.dataset.characterOrder || '99',
                fullCanvas: selectedOption.dataset.characterFullCanvas || '0',
                slot: selectedOption.dataset.characterSlot || 'character',
                style: selectedOption.dataset.characterStyle || '',
                innerStyle: selectedOption.dataset.characterInnerStyle || '',
                fit: selectedOption.dataset.characterFit || 'contain',
                objectPosition: selectedOption.dataset.characterObjectPosition || 'center bottom'
            };
        };

        const updateLearningAvatarCharacter = function (select) {
            const character = selectedCharacterPreview();
            if (!character) return;

            preview.querySelectorAll('[data-learning-avatar-layer-slot="character"], .gc-learning-avatar__layer--full').forEach(function (layer) {
                const src = layer.getAttribute('src') || '';
                const isCharacterLayer = layer.dataset.learningAvatarLayerSlot === 'character'
                    || src.indexOf('/characters/') !== -1
                    || src.indexOf('/20_character_skin_basic') !== -1;

                if (!isCharacterLayer) return;

                layer.remove();
            });

            const image = document.createElement('img');
            image.src = character.src;
            image.alt = '';
            image.loading = 'lazy';
            image.className = 'gc-learning-avatar__layer';
            image.dataset.learningAvatarLayerOrder = character.order || '99';

            if (character.fullCanvas === '0') {
                const slot = document.createElement('span');
                slot.className = 'gc-learning-avatar__slot gc-learning-avatar__slot--' + (character.slot || 'character');
                slot.dataset.learningAvatarLayerSlot = 'character';
                slot.dataset.learningAvatarLayerOrder = character.order || '99';
                slot.setAttribute('style', character.style || '');

                image.classList.add('gc-learning-avatar__layer--item');
                image.style.objectFit = character.fit || 'contain';
                image.style.objectPosition = character.objectPosition || 'center bottom';
                if (character.innerStyle) {
                    image.style.cssText += '; ' + character.innerStyle;
                }
                slot.appendChild(image);
                insertLearningAvatarLayer(slot);
                return;
            }

            image.classList.add('gc-learning-avatar__layer--full');
            image.dataset.learningAvatarLayerSlot = 'character';
            insertLearningAvatarLayer(image);
        };

        const updateLearningAvatarCost = function () {
            const purchased = new Map();

            form.querySelectorAll('select[name^="equipped["]').forEach(function (select) {
                const selectedOption = select.options[select.selectedIndex];
                if (!selectedOption || !selectedOption.value || selectedOption.dataset.itemOwned === '1') return;

                const cost = Number(selectedOption.dataset.itemCost || 0);
                if (cost > 0) {
                    purchased.set(selectedOption.value, {
                        name: selectedOption.textContent.replace(/\s+/g, ' ').trim().replace(/\s+·.+$/, ''),
                        cost: cost
                    });
                }
            });

            const total = Array.from(purchased.values()).reduce(function (sum, item) {
                return sum + item.cost;
            }, 0);

            if (!summary) return;

            summary.classList.remove('is-warning', 'is-muted');
            if (total <= 0) {
                summary.classList.add('is-muted');
                summary.innerHTML = '<span>Новых покупок нет</span>';
                form.removeAttribute('data-confirm');
                if (submit) submit.disabled = false;
                return;
            }

            const itemNames = Array.from(purchased.values()).map(function (item) {
                return item.name;
            }).join(', ');
            const canAfford = total <= balance;
            summary.classList.toggle('is-warning', !canAfford);
            summary.innerHTML = '<span>' + (canAfford ? 'Будет списано' : 'Не хватает GC') + '</span><strong>' + total + ' GC</strong>';
            form.dataset.confirm = 'Сохранить комнату и купить: ' + itemNames + ' за ' + total + ' GC?';
            if (submit) submit.disabled = !canAfford;
        };

        form.querySelectorAll('select[name^="equipped["]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarSlot(select);
                updateLearningAvatarCost();
            });
        });

        form.querySelectorAll('select[name="appearance[gender]"]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarCharacter(select);
            });
        });

        form.querySelectorAll('select[name="appearance[grade]"]').forEach(function (select) {
            select.addEventListener('change', function () {
                updateLearningAvatarCharacter(select);
            });
        });

        updateLearningAvatarCost();
    });

    document.querySelectorAll('[data-solution-recheck-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const feedback = document.getElementById(button.dataset.solutionFeedbackId);
            const form = document.getElementById(button.dataset.solutionFormId);
            if (!feedback || !form) return;

            feedback.hidden = true;
            form.hidden = false;
            form.classList.remove('is-hidden');

            const markInput = form.querySelector('[name="mark"]');
            if (markInput) markInput.focus();
        });
    });

    document.querySelectorAll('[data-solution-recheck-cancel]').forEach(function (button) {
        button.addEventListener('click', function () {
            const feedback = document.getElementById(button.dataset.solutionFeedbackId);
            const form = document.getElementById(button.dataset.solutionFormId);
            if (!feedback || !form) return;

            form.hidden = true;
            form.classList.add('is-hidden');
            feedback.hidden = false;
        });
    });

    var url = document.location.toString();
    const stepDetailsPage = document.querySelector('[data-step-details-page]');
    const stepTabs = document.querySelector('[data-step-content-tabs]');
    const performTabs = document.querySelector('[data-perform-tabs]');
    const linkifySelector = document.body.dataset.linkifySelector || 'div.markdown, [data-linkify]';
    const linkifySkipSelector = 'nav, aside, form, button, textarea, select, option, .dropdown-menu, .gc-sidebar';

    const getTabTriggerForHash = function (hash) {
        if (!hash || hash === '#') return null;

        return Array.from(document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')).find(function (el) {
            return (el.getAttribute('href') || el.getAttribute('data-bs-target')) === hash;
        });
    };

    const applyLinkify = function (root) {
        root.querySelectorAll(linkifySelector).forEach(function (element) {
            if (element.dataset.linkifyReady === '1' || element.closest(linkifySkipSelector)) return;
            element.innerHTML = linkifyHtml(element.innerHTML, { target: '_blank' });
            element.dataset.linkifyReady = '1';
        });

        root.querySelectorAll(linkifySelector + ' a').forEach(function (link) {
            link.target = '_blank';
        });
    };

    const enhanceStepMedia = function (root) {
        root.querySelectorAll('.step-content .markdown img:not([data-step-media-ready])').forEach(function (image) {
            if (image.closest('.step-media-figure')) return;

            image.dataset.stepMediaReady = '1';
            image.loading = 'lazy';
            image.decoding = 'async';

            const imageLink = image.closest('a');
            const singleImageLink = imageLink && imageLink.children.length === 1 ? imageLink : null;
            const mediaNode = singleImageLink || image;
            const figure = document.createElement('figure');
            figure.className = 'step-media-figure';

            mediaNode.before(figure);

            if (singleImageLink) {
                singleImageLink.classList.add('step-media-link');
                singleImageLink.target = '_blank';
                singleImageLink.rel = 'noopener';
                figure.appendChild(singleImageLink);
            } else if (!imageLink && (image.currentSrc || image.src)) {
                const link = document.createElement('a');
                link.className = 'step-media-link';
                link.href = image.currentSrc || image.src;
                link.target = '_blank';
                link.rel = 'noopener';
                figure.appendChild(link);
                link.appendChild(image);
            } else {
                figure.classList.add('step-media-figure--plain');
                figure.appendChild(image);
            }

            const captionText = image.getAttribute('alt');
            if (captionText) {
                const caption = document.createElement('figcaption');
                caption.textContent = captionText;
                figure.appendChild(caption);
            }
        });
    };

    const syncStepReadingToc = function () {
        const layout = document.querySelector('[data-step-content-layout]');
        const toc = document.querySelector('[data-step-reading-toc]');
        if (!layout || !toc) return;

        const activePane = layout.querySelector('.tab-pane.active.markdown');
        const headings = activePane ? Array.from(activePane.querySelectorAll('h2, h3')) : [];
        const escapeHtml = function (value) {
            return value.replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char];
            });
        };

        if (headings.length < 2) {
            toc.hidden = true;
            toc.innerHTML = '';
            layout.classList.remove('has-toc');
            return;
        }

        const links = headings.map(function (heading, index) {
            if (!heading.id) {
                heading.id = 'step-heading-' + index + '-' + heading.textContent.trim().toLowerCase()
                    .replace(/[^a-zа-я0-9]+/gi, '-')
                    .replace(/^-|-$/g, '');
            }

            return '<a class="step-reading-toc__link step-reading-toc__link--' + heading.tagName.toLowerCase() +
                '" href="#' + heading.id + '">' + escapeHtml(heading.textContent) + '</a>';
        });

        toc.innerHTML = '<div class="step-reading-toc__title">В этой теме</div><nav>' + links.join('') + '</nav>';
        toc.hidden = false;
        layout.classList.add('has-toc');
        updateStepReadingState();
    };

    const enhanceStepCodeBlocks = function (root) {
        root.querySelectorAll('.step-content pre:not([data-step-code-ready])').forEach(function (pre) {
            const code = pre.querySelector('code');
            if (!code) return;

            pre.dataset.stepCodeReady = '1';
            pre.classList.add('step-code-block');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'step-code-copy';
            button.textContent = 'Копировать';
            button.addEventListener('click', function () {
                const text = code.textContent;

                if (!navigator.clipboard || !navigator.clipboard.writeText) {
                    button.textContent = 'Недоступно';
                    window.setTimeout(function () {
                        button.textContent = 'Копировать';
                    }, 1400);
                    return;
                }

                navigator.clipboard.writeText(text).then(function () {
                    button.textContent = 'Скопировано';
                    button.classList.add('is-copied');
                    window.setTimeout(function () {
                        button.textContent = 'Копировать';
                        button.classList.remove('is-copied');
                    }, 1400);
                }).catch(function () {
                    button.textContent = 'Не удалось';
                    window.setTimeout(function () {
                        button.textContent = 'Копировать';
                    }, 1400);
                });
            });

            pre.appendChild(button);
        });
    };

    const updateStepReadingState = function () {
        const layout = document.querySelector('[data-step-content-layout]');
        const progress = document.querySelector('[data-step-reading-progress]');
        const fill = progress ? progress.querySelector('span') : null;
        const activePane = layout ? layout.querySelector('.tab-pane.active.markdown') : null;

        if (!activePane || !progress || !fill) return;

        const scrollRoot = document.scrollingElement || document.documentElement;
        const total = Math.max(1, scrollRoot.scrollHeight - window.innerHeight);
        const percent = Math.max(0, Math.min(1, scrollRoot.scrollTop / total));
        const rect = activePane.getBoundingClientRect();

        progress.hidden = total < window.innerHeight * 0.15;
        fill.style.transform = 'scaleX(' + percent + ')';

        const headings = Array.from(activePane.querySelectorAll('h2[id], h3[id]'));
        const activeHeading = headings.reduce(function (current, heading) {
            return heading.getBoundingClientRect().top <= 130 ? heading : current;
        }, headings[0]);

        document.querySelectorAll('.step-reading-toc__link').forEach(function (link) {
            link.classList.toggle('is-active', activeHeading && link.getAttribute('href') === '#' + activeHeading.id);
        });
    };

    const replaceButtonText = function (button, pattern, replacement) {
        Array.from(button.childNodes).some(function (node) {
            if (node.nodeType !== Node.TEXT_NODE || !pattern.test(node.textContent)) return false;
            node.textContent = node.textContent.replace(pattern, replacement);
            return true;
        });
    };

    // Tab activation from URL hash. Step and perform pages initialize their tabs below
    // so they can avoid the browser's eager anchor scroll to hidden tab panes.
    if (url.match('#') && !stepTabs && !performTabs) {
        var hash = window.location.hash;
        var tabEl = getTabTriggerForHash(hash);
        if (tabEl) new bootstrap.Tab(tabEl).show();
    }

    var syncTabContentHeight = function (container) {
        if (!container) return;
        var activePane = container.querySelector('.tab-pane.active');
        if (!activePane) return;

        window.requestAnimationFrame(function () {
            var minimumHeight = Math.min(activePane.offsetHeight, Math.max(320, window.innerHeight * 0.62));
            var nextMinHeight = Math.max(0, Math.round(minimumHeight));
            container.style.minHeight = nextMinHeight + 'px';
        });
    };

    document.querySelectorAll('.tab-content').forEach(syncTabContentHeight);

    document.querySelectorAll('.nav-tabs a, [data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function (event) {
            var hash = event.target.getAttribute('href') || event.target.getAttribute('data-bs-target');
            var targetPane = null;

            if (hash && hash !== '#') {
                history.replaceState(null, '', window.location.pathname + window.location.search + hash);
                targetPane = document.querySelector(hash);
            }

            syncTabContentHeight(targetPane ? targetPane.closest('.tab-content') : null);
            syncStepReadingToc();
            updateStepReadingState();
        });
    });

    if (window.flatpickr) {
        window.flatpickr('.date', { dateFormat: 'Y-m-d', locale: Russian });
    }

    applyLinkify(document);
    enhanceStepMedia(document);
    enhanceStepCodeBlocks(document);
    syncStepReadingToc();
    updateStepReadingState();

    window.addEventListener('scroll', updateStepReadingState, { passive: true });
    window.addEventListener('resize', updateStepReadingState);

    // Initialize Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Initialize Bootstrap popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });

    if (window.MathJax && window.MathJax.typesetPromise) {
        window.MathJax.typesetPromise();
    }

    if (stepDetailsPage) {
        document.querySelectorAll('blockquote').forEach(function (el) {
            el.classList.add('alert', 'alert-info', 'callout-border-info');
        });
        document.querySelectorAll('table').forEach(function (el) {
            el.classList.add('table', 'table-striped');
        });
    }

    if (performTabs) {
        var performHash = window.location.hash;
        var performHashTab = getTabTriggerForHash(performHash);
        var performHashPane = performHash ? performTabs.querySelector(performHash) : null;

        if (performHashTab && performHashPane) {
            new bootstrap.Tab(performHashTab).show();
        } else {
            var activePerformPane = performTabs.querySelector('.tab-pane.active');
            if (!activePerformPane) {
                activePerformPane = performTabs.querySelector('.tab-pane');
                if (activePerformPane) activePerformPane.classList.add('show', 'active');
            }

            if (activePerformPane) {
                var activePerformTab = getTabTriggerForHash('#' + activePerformPane.id);
                if (activePerformTab) {
                    activePerformTab.classList.add('active');
                    activePerformTab.setAttribute('aria-selected', 'true');
                }
            }
        }

        syncTabContentHeight(performTabs);
    }

    if (stepTabs) {
        var stepHash = window.location.hash;
        var stepHashTab = getTabTriggerForHash(stepHash);

        if (stepHashTab) {
            new bootstrap.Tab(stepHashTab).show();

            window.requestAnimationFrame(function () {
                window.scrollTo({ top: 0, behavior: 'auto' });
                syncTabContentHeight(document.querySelector('.step-content'));
                syncStepReadingToc();
                updateStepReadingState();
            });
        } else {
            var activeStepPane = document.querySelector('.step-content .tab-pane.active');
            if (!activeStepPane) {
                var firstTabPane = document.querySelector('.step-content .tab-pane');
                if (firstTabPane) firstTabPane.classList.add('active', 'show');
            }

            if (stepTabs.dataset.zeroTheory === 'true') {
                var activePill = document.querySelector('.step-top-tabs .nav-link.active');
                var firstPill = document.querySelector('.task-pill');
                if (!activePill && firstPill) firstPill.classList.add('active');
            }

            syncTabContentHeight(document.querySelector('.step-content'));
        }

        document.querySelectorAll('a[data-bs-toggle="pill"]').forEach(function (el) {
            el.addEventListener('shown.bs.tab', function (event) {
                if (!window.MathJax || !window.MathJax.typesetPromise) return;
                var targetPane = document.querySelector(event.target.getAttribute('href'));
                if (targetPane) {
                    window.MathJax.typesetPromise([targetPane]).catch(function (error) {
                        console.warn('MathJax typeset error: ' + error.message);
                    });
                }
            });
        });

        document.querySelectorAll('.collapse').forEach(function (el) {
            el.addEventListener('shown.bs.collapse', function () {
                if (!window.MathJax || !window.MathJax.typesetPromise) return;
                window.MathJax.typesetPromise([this]).catch(function (error) {
                    console.warn('MathJax typeset error: ' + error.message);
                });
            });
        });
    }

    if (window.autosize) {
        window.autosize(document.querySelectorAll('textarea'));
    }

    document.querySelectorAll('[data-progress-width]').forEach(function (progressBar) {
        var width = parseFloat(progressBar.dataset.progressWidth);
        if (!Number.isNaN(width)) progressBar.style.width = Math.max(0, Math.min(100, width)) + '%';
    });

    document.querySelectorAll('[data-progress-height]').forEach(function (progressElement) {
        var target = progressElement.classList.contains('progress') ? progressElement : progressElement.closest('.progress');
        if (target) target.style.height = progressElement.dataset.progressHeight;
    });

    document.querySelectorAll('select[data-selected-count]').forEach(function (select) {
        var target = document.querySelector(select.dataset.selectedCount);
        if (!target) return;

        var updateSelectedCount = function () {
            var selectedCount = Array.from(select.selectedOptions || []).length;
            target.textContent = selectedCount + ' выбрано';
            target.classList.toggle('is-empty', selectedCount === 0);
        };

        select.addEventListener('change', updateSelectedCount);
        updateSelectedCount();
    });

    document.querySelectorAll('select[data-enhanced-multiselect][multiple]').forEach(function (select) {
        if (select.dataset.enhancedMultiselectReady === '1') return;
        select.dataset.enhancedMultiselectReady = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'gc-multiselect bootstrap-select show-tick dropdown';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'gc-multiselect__toggle btn btn-light dropdown-toggle w-100';
        button.setAttribute('data-bs-toggle', 'dropdown');
        button.setAttribute('data-bs-auto-close', 'outside');
        button.setAttribute('aria-expanded', 'false');

        var filterOption = document.createElement('span');
        filterOption.className = 'filter-option';

        var filterOptionInner = document.createElement('span');
        filterOptionInner.className = 'filter-option-inner';

        var buttonText = document.createElement('span');
        buttonText.className = 'filter-option-inner-inner gc-multiselect__label text-truncate';
        filterOptionInner.appendChild(buttonText);
        filterOption.appendChild(filterOptionInner);
        button.appendChild(filterOption);

        var menu = document.createElement('div');
        menu.className = 'gc-multiselect__menu dropdown-menu w-100 p-2';

        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm gc-multiselect__search';
        search.placeholder = select.dataset.searchPlaceholder || 'Найти';
        search.autocomplete = 'off';
        menu.appendChild(search);

        var actions = document.createElement('div');
        actions.className = 'gc-multiselect__actions';

        var selectVisible = document.createElement('button');
        selectVisible.type = 'button';
        selectVisible.className = 'btn btn-sm btn-link px-0';
        selectVisible.textContent = 'Выбрать все';

        var clearVisible = document.createElement('button');
        clearVisible.type = 'button';
        clearVisible.className = 'btn btn-sm btn-link px-0 text-muted';
        clearVisible.textContent = 'Снять';

        actions.appendChild(selectVisible);
        actions.appendChild(clearVisible);
        menu.appendChild(actions);

        var list = document.createElement('div');
        list.className = 'gc-multiselect__list';
        menu.appendChild(list);

        var empty = document.createElement('div');
        empty.className = 'gc-multiselect__empty text-muted small text-center py-3 d-none';
        empty.textContent = 'Ничего не найдено';
        menu.appendChild(empty);

        var options = Array.from(select.options || []).map(function (option) {
            var item = document.createElement('label');
            item.className = 'gc-multiselect__option dropdown-item';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input';
            checkbox.checked = option.selected;

            var text = document.createElement('span');
            text.className = 'gc-multiselect__option-text text-truncate';
            text.textContent = option.textContent.trim();

            var check = document.createElement('span');
            check.className = 'gc-multiselect__check icon ion-checkmark';
            check.setAttribute('aria-hidden', 'true');

            item.appendChild(checkbox);
            item.appendChild(text);
            item.appendChild(check);
            list.appendChild(item);

            checkbox.addEventListener('change', function () {
                option.selected = checkbox.checked;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });

            return {
                option: option,
                checkbox: checkbox,
                item: item,
                text: option.textContent.trim(),
                haystack: option.textContent.trim().toLowerCase(),
            };
        });

        var updateButton = function () {
            var selected = options.filter(function (entry) {
                return entry.option.selected;
            });

            if (selected.length === 0) {
                buttonText.textContent = select.dataset.placeholder || 'Ничего не выбрано';
                button.classList.add('is-empty');
            } else if (selected.length <= 2) {
                buttonText.textContent = selected.map(function (entry) {
                    return entry.text;
                }).join(', ');
                button.classList.remove('is-empty');
            } else {
                buttonText.textContent = selected.length + ' выбрано';
                button.classList.remove('is-empty');
            }

            options.forEach(function (entry) {
                entry.item.classList.toggle('selected', entry.option.selected);
                entry.item.classList.toggle('active', entry.option.selected);
            });
        };

        var applySearch = function () {
            var query = search.value.trim().toLowerCase();
            var visibleCount = 0;

            options.forEach(function (entry) {
                var isVisible = query === '' || entry.haystack.indexOf(query) !== -1;
                entry.item.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            empty.classList.toggle('d-none', visibleCount !== 0);
        };

        var setVisibleOptions = function (checked) {
            options.forEach(function (entry) {
                if (entry.item.classList.contains('d-none')) return;
                entry.option.selected = checked;
                entry.checkbox.checked = checked;
            });

            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        menu.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        search.addEventListener('input', applySearch);
        selectVisible.addEventListener('click', function () {
            setVisibleOptions(true);
        });
        clearVisible.addEventListener('click', function () {
            setVisibleOptions(false);
        });
        select.addEventListener('change', function () {
            options.forEach(function (entry) {
                entry.checkbox.checked = entry.option.selected;
            });
            updateButton();
        });

        wrapper.appendChild(button);
        wrapper.appendChild(menu);
        select.after(wrapper);
        select.classList.add('d-none');
        updateButton();
    });

    var closeMarketFrameModal = function (modal) {
        if (!modal) return;

        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('market-digital-frame-modal-open');
    };

    document.querySelectorAll('[data-market-frame-modal]').forEach(function (modal) {
        var wrapper = modal.closest('[data-avatar-frame-editor]') || document;
        var openButton = wrapper.querySelector('[data-market-frame-modal-open]');
        var focusTarget = modal.querySelector('.market-digital-frame-modal__body select, .market-digital-frame-modal__body input, .market-digital-frame-modal__body button');

        if (openButton) {
            openButton.addEventListener('click', function () {
                modal.hidden = false;
                modal.classList.add('is-open');
                document.body.classList.add('market-digital-frame-modal-open');
                if (focusTarget) focusTarget.focus({ preventScroll: true });
            });
        }

        modal.querySelectorAll('[data-market-frame-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeMarketFrameModal(modal);
                if (openButton) openButton.focus({ preventScroll: true });
            });
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-market-frame-modal].is-open').forEach(function (modal) {
            closeMarketFrameModal(modal);
            var wrapper = modal.closest('[data-avatar-frame-editor]');
            var openButton = wrapper ? wrapper.querySelector('[data-market-frame-modal-open]') : null;
            if (openButton) openButton.focus({ preventScroll: true });
        });
    });

    document.querySelectorAll('[data-avatar-frame-editor]').forEach(function (editor) {
        var previews = Array.from(editor.querySelectorAll('[data-avatar-frame-preview]'));
        if (!previews.length) return;

        var previewStage = editor.querySelector('[data-frame-preview-stage]');
        var typeInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[type]"]'));
        var shapeInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[shape]"]'));
        var motionInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[motion]"]'));
        var patternInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[pattern]"]'));
        var avatarEffectInputs = Array.from(editor.querySelectorAll('[name="avatar_frame_config[avatar_effect]"]'));
        var colorInputs = Array.from(editor.querySelectorAll('input[type="color"]'));
        var angleInput = editor.querySelector('[data-frame-angle]');
        var widthInput = editor.querySelector('[data-frame-width]');
        var glowInput = editor.querySelector('[data-frame-glow]');
        var speedInput = editor.querySelector('[data-frame-speed]');
        var patternOpacityInput = editor.querySelector('[data-frame-pattern-opacity]');
        var effectOpacityInput = editor.querySelector('[data-frame-effect-opacity]');
        var animatedInput = editor.querySelector('[name="avatar_frame_config[animated]"][type="checkbox"]');
        var animatedOptionsPanel = editor.querySelector('.profile-avatar-frame-editor__animation-options');
        var animatedOptions = Array.from(editor.querySelectorAll('[data-frame-animated-option]'));
        var patternStrengthField = editor.querySelector('[data-frame-pattern-strength-field]');
        var avatarEffectStrengthField = editor.querySelector('[data-frame-avatar-effect-strength-field]');
        var angleField = editor.querySelector('[data-frame-angle-field]');
        var angleValue = editor.querySelector('[data-frame-angle-value]');
        var widthValue = editor.querySelector('[data-frame-width-value]');
        var glowValue = editor.querySelector('[data-frame-glow-value]');
        var speedValue = editor.querySelector('[data-frame-speed-value]');
        var patternOpacityValue = editor.querySelector('[data-frame-pattern-opacity-value]');
        var effectOpacityValue = editor.querySelector('[data-frame-effect-opacity-value]');
        var summaryType = editor.querySelector('[data-frame-summary-type]');
        var summaryShape = editor.querySelector('[data-frame-summary-shape]');
        var summaryPattern = editor.querySelector('[data-frame-summary-pattern]');
        var summaryAnimation = editor.querySelector('[data-frame-summary-animation]');
        var priceValue = editor.querySelector('[data-frame-price]');
        var submitButton = editor.querySelector('[data-frame-submit]');
        var userBalance = parseInt(editor.dataset.frameBalance || '', 10);
        var defaultFrameConfig = {
            type: 'linear',
            shape: 'circle',
            motion: 'spin',
            pattern: 'sparkles',
            avatar_effect: 'sheen',
            angle: 135,
            width: 6,
            glow: 28,
            speed: 100,
            pattern_opacity: 72,
            effect_opacity: 70,
            animated: true,
            colors: ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e'],
        };
        var randomPalettes = [
            ['#22d3ee', '#8b5cf6', '#f97316', '#22c55e'],
            ['#f43f5e', '#f59e0b', '#22c55e', '#38bdf8'],
            ['#0f172a', '#166534', '#22c55e', '#84cc16'],
            ['#fef3c7', '#f59e0b', '#b45309', '#fde68a'],
            ['#60a5fa', '#a78bfa', '#f472b6', '#fb7185'],
            ['#14b8a6', '#22c55e', '#eab308', '#f97316'],
        ];
        var previewBgClasses = ['is-preview-light', 'is-preview-dark'];
        editor.querySelectorAll('input, select').forEach(function (input) {
            input.dataset.frameInitiallyDisabled = input.disabled ? '1' : '0';
        });
        if (submitButton) {
            submitButton.dataset.frameInitiallyDisabled = submitButton.disabled ? '1' : '0';
        }

        var setFrameInputDisabled = function (input, disabled) {
            input.disabled = input.dataset.frameInitiallyDisabled === '1' || disabled;
        };

        var frameCostFor = function (config) {
            if (!submitButton) return 0;

            var staticCost = parseInt(submitButton.dataset.frameStaticCost || '100', 10) || 100;
            var animatedCost = parseInt(submitButton.dataset.frameAnimatedCost || '150', 10) || 150;

            return config.animated ? animatedCost : staticCost;
        };

        var sanitizeColor = function (value) {
            return /^#[0-9a-f]{6}$/i.test(value || '') ? value.toLowerCase() : '#22d3ee';
        };

        var selectedValue = function (inputs, fallback) {
            var select = inputs.find(function (input) {
                return input.tagName === 'SELECT';
            });
            if (select) return select.value || fallback;

            var checked = inputs.find(function (input) {
                return input.checked;
            });

            return checked ? checked.value : fallback;
        };

        var currentConfig = function () {
            return {
                type: selectedValue(typeInputs, 'linear'),
                shape: selectedValue(shapeInputs, 'circle'),
                motion: selectedValue(motionInputs, 'spin'),
                pattern: selectedValue(patternInputs, 'none'),
                avatarEffect: selectedValue(avatarEffectInputs, 'sheen'),
                colors: colorInputs.map(function (input) {
                    return sanitizeColor(input.value);
                }).filter(Boolean),
                angle: Math.max(0, Math.min(359, parseInt(angleInput ? angleInput.value : 135, 10) || 0)),
                width: Math.max(2, Math.min(14, parseInt(widthInput ? widthInput.value : 6, 10) || 6)),
                glow: Math.max(0, Math.min(52, parseInt(glowInput ? glowInput.value : 28, 10) || 0)),
                speed: Math.max(60, Math.min(180, parseInt(speedInput ? speedInput.value : 100, 10) || 100)),
                patternOpacity: Math.max(20, Math.min(100, parseInt(patternOpacityInput ? patternOpacityInput.value : 72, 10) || 72)),
                effectOpacity: Math.max(20, Math.min(100, parseInt(effectOpacityInput ? effectOpacityInput.value : 70, 10) || 70)),
                animated: animatedInput ? animatedInput.checked : true,
            };
        };

        var gradientFor = function (config) {
            var colors = config.colors.length >= 2 ? config.colors : ['#22d3ee', '#8b5cf6'];
            if (config.type === 'conic') {
                return 'conic-gradient(from ' + config.angle + 'deg, ' + colors.join(', ') + ', ' + colors[0] + ')';
            }

            if (config.type === 'radial') {
                return 'radial-gradient(circle, ' + colors.join(', ') + ')';
            }

            return 'linear-gradient(' + config.angle + 'deg, ' + colors.join(', ') + ')';
        };

        var shapeFor = function (shape) {
            var shapes = {
                circle: ['999px', '999px'],
                squircle: ['1.35rem', '1.1rem'],
                badge: ['1.6rem 0.8rem', '1.25rem 0.62rem'],
                soft: ['1rem', '0.8rem'],
            };

            return shapes[shape] || shapes.circle;
        };

        var patternFor = function (pattern) {
            var patterns = {
                sparkles: 'radial-gradient(circle at 50% 6%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.16rem, transparent 0.19rem), radial-gradient(circle at 94% 34%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.13rem, transparent 0.17rem), radial-gradient(circle at 76% 94%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.15rem, transparent 0.18rem), radial-gradient(circle at 9% 66%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.12rem, transparent 0.16rem), radial-gradient(circle at 13% 18%, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0.11rem, transparent 0.15rem)',
                pixels: 'linear-gradient(90deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem, linear-gradient(rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 50%, transparent 0) 0 0 / 0.55rem 0.55rem',
                stripes: 'repeating-linear-gradient(135deg, rgb(255 255 255 / var(--profile-avatar-pattern-opacity)) 0 0.16rem, transparent 0.16rem 0.48rem)',
            };

            return patterns[pattern] || 'none';
        };

        var frameAnimationFor = function (config) {
            if (!config.animated) return 'none';
            var durationFor = function (seconds) {
                return Math.round((seconds * 100 / config.speed) * 100) / 100 + 's';
            };

            var animations = {
                spin: 'profile-avatar-rainbow-spin ' + durationFor(5.6) + ' linear infinite',
                pulse: 'profile-avatar-frame-pulse ' + durationFor(2.8) + ' ease-in-out infinite',
                sweep: 'profile-avatar-frame-sweep ' + durationFor(3.4) + ' linear infinite',
                still: 'none',
            };

            return animations[config.motion] || animations.spin;
        };

        var avatarEffectFor = function (config) {
            if (!config.animated || config.avatarEffect === 'none') {
                return ['transparent', 'none', '0'];
            }
            var durationFor = function (seconds) {
                return Math.round((seconds * 100 / config.speed) * 100) / 100 + 's';
            };

            var effects = {
                sheen: ['linear-gradient(115deg, transparent 18%, rgba(255, 255, 255, 0.62) 45%, transparent 72%)', 'profile-avatar-sheen ' + durationFor(3.6) + ' ease-in-out infinite'],
                scanner: ['linear-gradient(180deg, transparent 28%, rgba(34, 211, 238, 0.52) 50%, transparent 72%)', 'profile-avatar-scanner ' + durationFor(2.8) + ' ease-in-out infinite'],
                spark: ['radial-gradient(circle at 25% 35%, rgba(255, 255, 255, 0.86) 0 0.12rem, transparent 0.14rem), radial-gradient(circle at 68% 58%, rgba(255, 255, 255, 0.72) 0 0.1rem, transparent 0.12rem)', 'profile-avatar-spark ' + durationFor(2.4) + ' ease-in-out infinite'],
            };

            var effect = effects[config.avatarEffect] || effects.sheen;
            return effect.concat(String(Math.round(config.effectOpacity) / 100));
        };

        var labelFor = function (items, value, fallback) {
            return items[value] || fallback;
        };

        var setPresetActive = function (activeButton) {
            editor.querySelectorAll('[data-frame-preset]').forEach(function (item) {
                item.classList.toggle('is-active', item === activeButton);
            });
        };

        var applyPresetConfig = function (config, activeButton) {
            config = config || {};

            typeInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.type || defaultFrameConfig.type;
                    return;
                }
                input.checked = input.value === (config.type || defaultFrameConfig.type);
            });
            shapeInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.shape || defaultFrameConfig.shape;
                    return;
                }
                input.checked = input.value === (config.shape || defaultFrameConfig.shape);
            });
            motionInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.motion || defaultFrameConfig.motion;
                    return;
                }
                input.checked = input.value === (config.motion || defaultFrameConfig.motion);
            });
            patternInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.pattern || defaultFrameConfig.pattern;
                    return;
                }
                input.checked = input.value === (config.pattern || defaultFrameConfig.pattern);
            });
            avatarEffectInputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.value = config.avatar_effect || defaultFrameConfig.avatar_effect;
                    return;
                }
                input.checked = input.value === (config.avatar_effect || defaultFrameConfig.avatar_effect);
            });

            colorInputs.forEach(function (input, index) {
                input.value = sanitizeColor((config.colors && config.colors[index]) || defaultFrameConfig.colors[index] || defaultFrameConfig.colors[0]);
            });

            if (angleInput) angleInput.value = config.angle !== undefined ? config.angle : defaultFrameConfig.angle;
            if (widthInput) widthInput.value = config.width !== undefined ? config.width : defaultFrameConfig.width;
            if (glowInput) glowInput.value = config.glow !== undefined ? config.glow : defaultFrameConfig.glow;
            if (speedInput) speedInput.value = config.speed !== undefined ? config.speed : defaultFrameConfig.speed;
            if (patternOpacityInput) patternOpacityInput.value = config.pattern_opacity !== undefined ? config.pattern_opacity : defaultFrameConfig.pattern_opacity;
            if (effectOpacityInput) effectOpacityInput.value = config.effect_opacity !== undefined ? config.effect_opacity : defaultFrameConfig.effect_opacity;
            if (animatedInput) animatedInput.checked = config.animated !== undefined ? Boolean(config.animated) : defaultFrameConfig.animated;

            setPresetActive(activeButton || null);
            applyConfig();
        };

        var randomItem = function (items) {
            return items[Math.floor(Math.random() * items.length)];
        };

        var setPaletteActive = function (activeButton) {
            editor.querySelectorAll('[data-frame-palette]').forEach(function (item) {
                item.classList.toggle('is-active', item === activeButton);
            });
        };

        var applyPalette = function (colors, activeButton) {
            if (!Array.isArray(colors)) return;

            colorInputs.forEach(function (input, index) {
                if (colors[index]) input.value = sanitizeColor(colors[index]);
            });

            setPresetActive(null);
            setPaletteActive(activeButton || null);
            applyConfig();
        };

        var applyColorOrder = function (transform) {
            var colors = colorInputs.map(function (input) {
                return sanitizeColor(input.value);
            });

            colors = transform(colors);
            colorInputs.forEach(function (input, index) {
                if (colors[index]) input.value = colors[index];
            });

            setPresetActive(null);
            setPaletteActive(null);
            applyConfig();
        };

        var applyConfig = function () {
            var config = currentConfig();
            var glowColor = config.colors[1] || config.colors[0] || '#22d3ee';
            var shape = shapeFor(config.shape);
            var avatarEffect = avatarEffectFor(config);

            previews.forEach(function (preview) {
                preview.style.setProperty('--profile-avatar-frame-bg', gradientFor(config));
                preview.style.setProperty('--profile-avatar-frame-width', config.width + 'px');
                preview.style.setProperty('--profile-avatar-frame-radius', shape[0]);
                preview.style.setProperty('--profile-avatar-frame-inner-radius', shape[1]);
                preview.style.setProperty('--profile-avatar-frame-pattern', patternFor(config.pattern));
                preview.style.setProperty('--profile-avatar-pattern-opacity', config.patternOpacity + '%');
                preview.style.setProperty('--profile-avatar-frame-shadow', '0 0 0 0.12rem color-mix(in srgb, ' + config.colors[0] + ' 18%, transparent), 0 0.8rem 2rem color-mix(in srgb, ' + glowColor + ' ' + config.glow + '%, transparent)');
                preview.style.setProperty('--profile-avatar-frame-animation', frameAnimationFor(config));
                preview.style.setProperty('--profile-avatar-effect-bg', avatarEffect[0]);
                preview.style.setProperty('--profile-avatar-effect-animation', avatarEffect[1]);
                preview.style.setProperty('--profile-avatar-effect-opacity', avatarEffect[2]);
            });

            colorInputs.forEach(function (input) {
                var label = input.closest('.profile-avatar-frame-color');
                if (label) label.style.setProperty('--frame-color', sanitizeColor(input.value));
            });

            if (angleValue) angleValue.textContent = config.angle + '°';
            if (widthValue) widthValue.textContent = config.width + 'px';
            if (glowValue) glowValue.textContent = config.glow + '%';
            if (speedValue) speedValue.textContent = config.speed + '%';
            if (patternOpacityValue) patternOpacityValue.textContent = config.patternOpacity + '%';
            if (effectOpacityValue) effectOpacityValue.textContent = config.effectOpacity + '%';
            if (angleField) angleField.classList.toggle('d-none', config.type === 'radial');
            if (patternStrengthField) {
                var patternHidden = config.pattern === 'none';
                patternStrengthField.classList.toggle('d-none', patternHidden);
                patternStrengthField.querySelectorAll('input').forEach(function (input) {
                    setFrameInputDisabled(input, patternHidden);
                });
            }
            if (summaryType) {
                summaryType.textContent = labelFor({ linear: 'Линия', conic: 'Круг', radial: 'Сияние' }, config.type, 'Градиент');
            }
            if (summaryShape) {
                summaryShape.textContent = labelFor({ circle: 'Круглая', squircle: 'Сквиркл', badge: 'Жетон', soft: 'Мягкая' }, config.shape, 'Форма');
            }
            if (summaryPattern) {
                summaryPattern.textContent = config.pattern === 'none'
                    ? 'Без паттерна'
                    : labelFor({ sparkles: 'Искры', pixels: 'Пиксели', stripes: 'Штрихи' }, config.pattern, 'Паттерн') + ' · ' + config.patternOpacity + '%';
            }
            if (summaryAnimation) {
                summaryAnimation.textContent = config.animated
                    ? labelFor({ spin: 'Вращение', pulse: 'Пульс', sweep: 'Блик', still: 'Статика' }, config.motion, 'Живая') + ' · ' + config.speed + '%'
                    : 'Статичная';
            }
            if (submitButton) {
                var frameCost = frameCostFor(config);
                var frameType = config.animated ? 'живую' : 'статичную';
                var confirmTemplate = submitButton.dataset.frameConfirmTemplate || 'Купить свою {type} рамку за {cost} GC?';

                if (priceValue) priceValue.textContent = frameCost;
                submitButton.dataset.confirm = confirmTemplate
                    .replace('{type}', frameType)
                    .replace('{cost}', frameCost);
                submitButton.disabled = submitButton.dataset.frameInitiallyDisabled === '1'
                    || (!Number.isNaN(userBalance) && frameCost > userBalance);
            }

            animatedOptions.forEach(function (option) {
                var optionHidden = !config.animated || (option === avatarEffectStrengthField && config.avatarEffect === 'none');
                option.classList.toggle('d-none', optionHidden);
                option.querySelectorAll('input').forEach(function (input) {
                    setFrameInputDisabled(input, optionHidden);
                });
            });
            if (animatedOptionsPanel) {
                animatedOptionsPanel.classList.toggle('d-none', !config.animated);
            }
        };

        editor.querySelectorAll('[data-frame-preset]').forEach(function (button) {
            button.addEventListener('click', function () {
                var config;
                try {
                    config = JSON.parse(button.dataset.framePreset || '{}');
                } catch (error) {
                    config = {};
                }

                applyPresetConfig(config, button);
            });
        });

        var randomizeButton = editor.querySelector('[data-frame-randomize]');
        if (randomizeButton) {
            randomizeButton.addEventListener('click', function () {
                applyPresetConfig({
                    type: randomItem(['linear', 'conic', 'radial']),
                    shape: randomItem(['circle', 'squircle', 'badge', 'soft']),
                    motion: randomItem(['spin', 'pulse', 'sweep', 'still']),
                    pattern: randomItem(['none', 'sparkles', 'pixels', 'stripes']),
                    avatar_effect: randomItem(['none', 'sheen', 'scanner', 'spark']),
                    angle: Math.floor(Math.random() * 360),
                    width: randomItem([4, 5, 6, 7, 8, 9, 10]),
                    glow: randomItem([12, 18, 24, 30, 36, 42]),
                    speed: randomItem([70, 85, 100, 115, 130, 150]),
                    pattern_opacity: randomItem([42, 55, 68, 76, 88, 100]),
                    effect_opacity: randomItem([42, 55, 68, 76, 88, 100]),
                    animated: Math.random() > 0.18,
                    colors: randomItem(randomPalettes),
                }, null);
                setPaletteActive(null);
            });
        }

        var resetButton = editor.querySelector('[data-frame-reset]');
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                applyPresetConfig(defaultFrameConfig, editor.querySelector('[data-frame-preset]'));
                setPaletteActive(editor.querySelector('[data-frame-palette]'));
            });
        }

        editor.querySelectorAll('[data-frame-preview-bg]').forEach(function (button) {
            button.addEventListener('click', function () {
                var mode = button.dataset.framePreviewBg || 'soft';
                editor.querySelectorAll('[data-frame-preview-bg]').forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });

                if (!previewStage) return;
                previewBgClasses.forEach(function (className) {
                    previewStage.classList.remove(className);
                });

                if (mode === 'light') {
                    previewStage.classList.add('is-preview-light');
                } else if (mode === 'dark') {
                    previewStage.classList.add('is-preview-dark');
                }
            });
        });

        editor.querySelectorAll('[data-frame-palette]').forEach(function (button) {
            button.addEventListener('click', function () {
                var colors;
                try {
                    colors = JSON.parse(button.dataset.framePalette || '[]');
                } catch (error) {
                    colors = [];
                }

                applyPalette(colors, button);
            });
        });

        var rotateColorsButton = editor.querySelector('[data-frame-rotate-colors]');
        if (rotateColorsButton) {
            rotateColorsButton.addEventListener('click', function () {
                applyColorOrder(function (colors) {
                    return colors.length ? colors.slice(1).concat(colors[0]) : colors;
                });
            });
        }

        var reverseColorsButton = editor.querySelector('[data-frame-reverse-colors]');
        if (reverseColorsButton) {
            reverseColorsButton.addEventListener('click', function () {
                applyColorOrder(function (colors) {
                    return colors.slice().reverse();
                });
            });
        }

        typeInputs.concat(shapeInputs, motionInputs, patternInputs, avatarEffectInputs, colorInputs).forEach(function (input) {
            input.addEventListener('input', function () {
                setPresetActive(null);
                if (input.type === 'color') setPaletteActive(null);
                applyConfig();
            });
            input.addEventListener('change', function () {
                setPresetActive(null);
                if (input.type === 'color') setPaletteActive(null);
                applyConfig();
            });
        });
        [angleInput, widthInput, glowInput, speedInput, patternOpacityInput, effectOpacityInput, animatedInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('input', function () {
                setPresetActive(null);
                applyConfig();
            });
            input.addEventListener('change', function () {
                setPresetActive(null);
                applyConfig();
            });
        });

        applyConfig();
    });

    document.querySelectorAll('[data-report-student-search]').forEach(function (input) {
        var list = document.querySelector(input.dataset.reportStudentList);
        if (!list) return;
        var wrapper = input.closest('.report-students-card') || input.closest('.p-2');
        var counter = wrapper ? wrapper.querySelector('[data-report-student-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-report-student-clear]') : null;
        var sortSelect = wrapper ? wrapper.querySelector('[data-report-student-sort]') : null;
        var nameCollator = new Intl.Collator('ru', { numeric: true, sensitivity: 'base' });

        var emptyState = document.createElement('div');
        emptyState.className = 'text-muted small px-2 py-3 text-center d-none';
        emptyState.textContent = 'Ничего не найдено';
        list.after(emptyState);

        var getSortName = function (link) {
            return link.dataset.reportStudentSortName || link.dataset.reportStudentName || link.textContent || '';
        };

        var getSortNumber = function (link, key) {
            var value = Number(link.dataset[key]);
            return Number.isFinite(value) ? value : -1;
        };

        var sortStudentLinks = function () {
            var sortMode = sortSelect ? sortSelect.value : 'name';
            var valueMode = sortMode === 'name' ? 'percent' : sortMode;
            var links = Array.from(list.querySelectorAll('[data-report-student-name]'));

            links.sort(function (first, second) {
                if (sortMode === 'percent') {
                    return getSortNumber(second, 'reportStudentSortPercent') - getSortNumber(first, 'reportStudentSortPercent')
                        || nameCollator.compare(getSortName(first), getSortName(second));
                }

                if (sortMode === 'similarity') {
                    return getSortNumber(second, 'reportStudentSortSimilarity') - getSortNumber(first, 'reportStudentSortSimilarity')
                        || getSortNumber(second, 'reportStudentSortRisk') - getSortNumber(first, 'reportStudentSortRisk')
                        || nameCollator.compare(getSortName(first), getSortName(second));
                }

                if (sortMode === 'llm') {
                    return getSortNumber(second, 'reportStudentSortLlm') - getSortNumber(first, 'reportStudentSortLlm')
                        || getSortNumber(second, 'reportStudentSortRisk') - getSortNumber(first, 'reportStudentSortRisk')
                        || nameCollator.compare(getSortName(first), getSortName(second));
                }

                return nameCollator.compare(getSortName(first), getSortName(second));
            });

            links.forEach(function (link) {
                list.appendChild(link);
                link.querySelectorAll('[data-report-sort-value]').forEach(function (value) {
                    value.classList.toggle('d-none', value.dataset.reportSortValue !== valueMode);
                });
            });
        };

        var applyStudentFilter = function () {
            sortStudentLinks();

            var query = input.value.trim().toLowerCase();
            var links = Array.from(list.querySelectorAll('[data-report-student-name]'));
            var firstVisible = null;
            var visibleCount = 0;

            links.forEach(function (link) {
                var haystack = (link.dataset.reportStudentName || link.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                link.classList.toggle('d-none', !isVisible);
                if (isVisible) {
                    visibleCount += 1;
                    if (!firstVisible) firstVisible = link;
                }
            });

            emptyState.classList.toggle('d-none', Boolean(firstVisible));
            if (counter) counter.textContent = visibleCount + ' из ' + links.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);

            if (firstVisible && !list.querySelector('.nav-link.active:not(.d-none)')) {
                new bootstrap.Tab(firstVisible).show();
            }
        };

        input.addEventListener('input', applyStudentFilter);
        if (sortSelect) sortSelect.addEventListener('change', applyStudentFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyStudentFilter();
            });
        }

        applyStudentFilter();
    });

    document.querySelectorAll('[data-assessment-student-search]').forEach(function (input) {
        var table = document.querySelector(input.dataset.assessmentTable);
        if (!table) return;

        var wrapper = input.closest('.assessment-toolbar') || input.closest('.input-group');
        var rows = Array.from(table.querySelectorAll('[data-assessment-row]'));
        var counter = wrapper ? wrapper.querySelector('[data-assessment-student-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-assessment-student-clear]') : null;
        var tbody = table.querySelector('tbody');
        var emptyRow = document.createElement('tr');
        var firstRow = rows[0];
        var columnCount = firstRow ? firstRow.children.length : 1;

        emptyRow.className = 'assessment-empty-row d-none';
        emptyRow.innerHTML = '<td colspan="' + columnCount + '" class="text-center text-muted py-4">Ничего не найдено</td>';
        if (tbody) tbody.appendChild(emptyRow);

        var applyAssessmentFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            rows.forEach(function (row) {
                var haystack = (row.dataset.assessmentStudentName || row.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                row.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyRow.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + rows.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyAssessmentFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyAssessmentFilter();
            });
        }
    });

    document.querySelectorAll('[data-blocked-search]').forEach(function (input) {
        var table = document.querySelector(input.dataset.blockedTable);
        if (!table) return;

        var wrapper = input.closest('.blocked-toolbar') || input.closest('.input-group');
        var rows = Array.from(table.querySelectorAll('[data-blocked-row]'));
        var counter = wrapper ? wrapper.querySelector('[data-blocked-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-blocked-clear]') : null;
        var tbody = table.querySelector('tbody');
        var emptyRow = document.createElement('tr');
        var firstRow = rows[0];
        var columnCount = firstRow ? firstRow.children.length : 1;

        emptyRow.className = 'blocked-empty-row d-none';
        emptyRow.innerHTML = '<td colspan="' + columnCount + '" class="text-center text-muted py-4">Ничего не найдено</td>';
        if (tbody) tbody.appendChild(emptyRow);

        var applyBlockedFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            rows.forEach(function (row) {
                var haystack = (row.dataset.blockedSearchText || row.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                row.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyRow.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + rows.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyBlockedFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyBlockedFilter();
            });
        }
    });

    document.querySelectorAll('[data-step-progress-search]').forEach(function (input) {
        var grid = document.querySelector(input.dataset.stepProgressGrid);
        if (!grid) return;

        var wrapper = input.closest('.step-progress-card') || input.closest('.input-group');
        var cards = Array.from(grid.querySelectorAll('[data-step-progress-card]'));
        var counter = wrapper ? wrapper.querySelector('[data-step-progress-count]') : null;
        var emptyState = wrapper ? wrapper.querySelector('[data-step-progress-empty]') : null;

        var applyStepProgressFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            cards.forEach(function (card) {
                var haystack = (card.dataset.stepProgressText || card.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                card.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            if (counter) counter.textContent = visibleCount + ' из ' + cards.length;
            if (emptyState) emptyState.classList.toggle('d-none', visibleCount !== 0);
        };

        input.addEventListener('input', applyStepProgressFilter);
        applyStepProgressFilter();
    });

    document.querySelectorAll('[data-market-search]').forEach(function (input) {
        var grid = document.querySelector(input.dataset.marketGrid);
        if (!grid) return;

        var wrapper = input.closest('.market-goods-toolbar') || input.closest('.input-group');
        var goods = Array.from(grid.querySelectorAll('[data-market-good]'));
        var counter = wrapper ? wrapper.querySelector('[data-market-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-market-clear]') : null;
        var categoryButtons = Array.from(document.querySelectorAll('[data-market-category-filter]')).filter(function (button) {
            return button.dataset.marketGrid === input.dataset.marketGrid;
        });
        var emptyState = document.createElement('div');

        emptyState.className = 'gc-card market-search-empty text-center text-muted p-4 d-none';
        emptyState.innerHTML = '<div class="bg-body-tertiary text-muted rounded-circle d-inline-flex align-items-center justify-content-center fs-4 p-3 mb-3"><i class="fas fa-search"></i></div><h5>Ничего не найдено</h5><p class="mx-auto mb-0">Попробуйте изменить запрос или категорию.</p>';
        grid.after(emptyState);

        var applyMarketFilter = function () {
            var query = input.value.trim().toLowerCase();
            var activeCategory = categoryButtons.find(function (button) {
                return button.classList.contains('is-active');
            })?.dataset.marketCategoryFilter || 'all';
            var visibleCount = 0;

            goods.forEach(function (good) {
                var haystack = (good.dataset.marketGoodText || good.textContent || '').toLowerCase();
                var matchesQuery = !query || haystack.indexOf(query) !== -1;
                var matchesCategory = activeCategory === 'all' || good.dataset.marketGoodCategory === activeCategory;
                var isVisible = matchesQuery && matchesCategory;
                good.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyState.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + goods.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyMarketFilter);
        categoryButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                categoryButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                applyMarketFilter();
            });
        });

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyMarketFilter();
            });
        }

        applyMarketFilter();
    });

    document.querySelectorAll('[data-community-search]').forEach(function (input) {
        var grid = document.querySelector(input.dataset.communityGrid);
        if (!grid) return;

        var wrapper = input.closest('.community-toolbar') || input.closest('.input-group');
        var users = Array.from(grid.querySelectorAll('[data-community-user]'));
        var counter = wrapper ? wrapper.querySelector('[data-community-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-community-clear]') : null;
        var emptyState = document.createElement('div');

        emptyState.className = 'gc-card community-search-empty text-center text-muted p-4 d-none';
        emptyState.innerHTML = '<div class="bg-body-tertiary text-muted rounded-circle d-inline-flex align-items-center justify-content-center fs-4 p-3 mb-3"><i class="fas fa-users"></i></div><h5>Ничего не найдено</h5><p class="mx-auto mb-0">Попробуйте изменить запрос.</p>';
        grid.after(emptyState);

        var applyCommunityFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            users.forEach(function (user) {
                var haystack = (user.dataset.communityUserText || user.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                user.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyState.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + users.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyCommunityFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyCommunityFilter();
            });
        }
    });

    document.querySelectorAll('[data-background-image]').forEach(function (element) {
        element.style.backgroundImage = 'url(' + element.dataset.backgroundImage + ')';
    });

    document.querySelectorAll('img[data-image-fallback]').forEach(function (image) {
        var loadFallback = function () {
            if (image.dataset.fallbackLoaded) return;
            image.dataset.fallbackLoaded = '1';
            image.src = image.dataset.imageFallback;
        };
        image.addEventListener('error', loadFallback);
        if (image.complete && image.naturalWidth === 0) loadFallback();
    });

    if (window.nbv) {
        document.querySelectorAll('[data-notebook-content]').forEach(function (notebook) {
            if (notebook.dataset.notebookRendered === '1') return;
            try {
                window.nbv.render(JSON.parse(notebook.dataset.notebookContent), notebook);
                notebook.dataset.notebookRendered = '1';
            } catch (error) {
                console.error('Failed to render notebook:', error);
            }
        });
    }

    if (window.hljs) {
        document.querySelectorAll('pre code').forEach(function (block) {
            window.hljs.highlightElement(block);
        });
    }

    if (window.EasyMDE) {
        window.markdownEditors = window.markdownEditors || {};
        document.querySelectorAll('textarea[data-markdown-editor]').forEach(function (textarea) {
            if (textarea.dataset.markdownEditorReady === '1') return;
            textarea.dataset.markdownEditorReady = '1';
            var editorConfig = {
                spellChecker: false,
                element: textarea,
            };
            if (textarea.dataset.markdownAutosave === 'true') {
                editorConfig.autosave = {
                    enabled: true,
                    uniqueId: textarea.id || textarea.name || window.location.pathname,
                };
            }
            var editor = new window.EasyMDE(editorConfig);
            if (textarea.id) window.markdownEditors[textarea.id] = editor;
        });
    }

    // Sidebar toggle (mobile)
    var sidebar = document.getElementById('gcSidebar');
    var sidebarToggle = document.getElementById('gcSidebarToggle');
    var backdrop = document.getElementById('gcBackdrop');

    if (sidebar && sidebarToggle && backdrop) {
        var closeSidebar = function () {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            document.body.style.overflow = '';
        };

        var openSidebar = function () {
            sidebar.classList.add('show');
            backdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        };

        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });

        backdrop.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSidebar(); });
        window.addEventListener('resize', function () { if (window.innerWidth > 991) closeSidebar(); });
    }

    // Theme toggle
    var themeToggle = document.getElementById('gcThemeToggle');
    if (themeToggle) {
        var savedTheme = localStorage.getItem('gc-theme');
        if (savedTheme) document.documentElement.setAttribute('data-bs-theme', savedTheme);

        themeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-bs-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('gc-theme', next);
        });
    }

    // ChatGPT improve text
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-improve-text]');
        if (!button) return;

        var editor = window.markdownEditors && window.markdownEditors[button.dataset.fieldId];
        if (!editor) { alert('Редактор еще не загружен.'); return; }

        var currentText = editor.value();
        if (!currentText.trim()) { alert('Поле пустое.'); return; }

        var originalButtonContents = Array.from(button.childNodes).map(function (n) { return n.cloneNode(true); });
        button.disabled = true;
        replaceButtonText(button, /Исправить|Улучшить/, 'Обработка...');

        fetch('/insider/chatgpt/improve-text', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ text: currentText, action: button.dataset.improveText })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    if (confirm('Заменить текст на улучшенную версию?')) editor.value(data.improved_text);
                } else {
                    alert('Ошибка: ' + (data.error || 'Не удалось улучшить текст'));
                }
            })
            .catch(function () { alert('Ошибка при обращении к сервису'); })
            .finally(function () {
                button.disabled = false;
                button.replaceChildren.apply(button, originalButtonContents);
            });
    });

    // Plotly charts
    if (window.Plotly) {
        document.querySelectorAll('[data-plotly-histogram]').forEach(function (element) {
            element.style.height = '240px';
            window.Plotly.newPlot(element, [{
                x: JSON.parse(element.dataset.plotlyHistogram),
                type: 'histogram',
                autobinx: false,
                marker: { color: 'rgba(100, 200, 102, 0.7)', line: { color: 'rgba(100, 200, 102, 1)', width: 1 } },
                opacity: 0.75,
                xbins: { end: 110, size: 15, start: 0 }
            }], {
                autosize: true, height: 240,
                margin: { l: 32, r: 10, b: 28, t: 8, pad: 0 },
                showlegend: false
            }, { displayModeBar: false, responsive: true });
        });

        document.querySelectorAll('[data-plotly-report-chart]').forEach(function (element) {
            element.style.height = '150px';
            var data = [{
                x: JSON.parse(element.dataset.pulseKeys),
                y: JSON.parse(element.dataset.pulseValues),
                type: 'scatter',
                line: { color: '#2563eb', shape: 'spline', width: 2 },
                marker: { color: '#2563eb', size: 4 }
            }];
            if (element.dataset.taskKeys && element.dataset.taskValues) {
                data.push({
                    x: JSON.parse(element.dataset.taskKeys),
                    y: JSON.parse(element.dataset.taskValues),
                    type: 'scatter',
                    yaxis: 'y2',
                    line: { color: '#16a34a', shape: 'spline', width: 1.5 },
                    fill: 'tonexty',
                    fillcolor: 'rgba(22, 163, 74, 0.08)',
                    marker: { color: '#16a34a', size: 4 }
                });
            }
            window.Plotly.newPlot(element, data, {
                font: { color: '#64748b' },
                paper_bgcolor: 'rgba(0,0,0,0)',
                plot_bgcolor: 'rgba(0,0,0,0)',
                xaxis: { gridcolor: 'rgba(100, 116, 139, 0.10)', tickfont: { size: 10 }, zeroline: false, showline: false },
                yaxis: { gridcolor: 'rgba(100, 116, 139, 0.10)', tickfont: { size: 10 }, zeroline: false, showline: false },
                yaxis2: { side: 'right', gridcolor: 'rgba(0,0,0,0)', showgrid: false, showticklabels: false, ticks: '', zeroline: false, showline: false, overlaying: 'y' },
                margin: { l: 24, r: 6, b: 24, t: 2, pad: 0 },
                showlegend: false
            }, { staticPlot: false, displayModeBar: false, responsive: false });
        });
    }

    function showFullscreenLoading(message) {
        if (document.querySelector('.gc-fullscreen-loading')) return;

        var overlay = document.createElement('div');
        overlay.className = 'gc-fullscreen-loading';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        var panel = document.createElement('div');
        var spinner = document.createElement('div');
        var title = document.createElement('div');
        var hint = document.createElement('div');
        panel.className = 'gc-fullscreen-loading__panel';
        spinner.className = 'gc-fullscreen-loading__spinner';
        spinner.setAttribute('aria-hidden', 'true');
        title.className = 'gc-fullscreen-loading__title';
        title.textContent = message || 'Идет обработка';
        hint.className = 'gc-fullscreen-loading__hint';
        hint.textContent = 'Это может занять немного времени. Страница откроется автоматически.';
        panel.appendChild(spinner);
        panel.appendChild(title);
        panel.appendChild(hint);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
    }

    // Confirm dialogs
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-confirm], button[data-confirm]');
        if (link && !confirm(link.dataset.confirm)) event.preventDefault();
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-confirm], form[data-fullscreen-loading]')) return;

        if (form.dataset.confirm && !confirm(form.dataset.confirm)) {
            event.preventDefault();
            return;
        }

        if (form.hasAttribute('data-fullscreen-loading')) {
            showFullscreenLoading(form.dataset.loadingMessage);
        }
    });

    // Check task forms
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('[data-check-task]')) return;
        event.preventDefault();
        var taskId = form.dataset.taskId;
        var text = form.querySelector('[name=text]').value;
        window.axios.post(form.action, 'text=' + encodeURI(text))
            .then(function (response) {
                var taskScore = document.getElementById('TSK_' + taskId);
                var taskComment = document.getElementById('TSK_COM_' + taskId);
                var taskStatusRow = taskScore ? taskScore.closest('[data-task-status-row]') : null;
                if (taskStatusRow) taskStatusRow.classList.remove('d-none');
                if (taskScore) {
                    taskScore.textContent = response.data.mark + ' XP';
                    if (response.data.score_badge_class) {
                        taskScore.classList.remove('bg-body', 'bg-body-tertiary', 'solution-score-badge--special');
                        response.data.score_badge_class.split(' ').forEach(function (className) {
                            if (className) taskScore.classList.add(className);
                        });
                    }
                }
                if (taskComment) taskComment.textContent = response.data.comment || '';
            });
    });
});
