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

    document.querySelectorAll('[data-report-student-search]').forEach(function (input) {
        var list = document.querySelector(input.dataset.reportStudentList);
        if (!list) return;
        var wrapper = input.closest('.report-students-card') || input.closest('.p-2');
        var counter = wrapper ? wrapper.querySelector('[data-report-student-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-report-student-clear]') : null;

        var emptyState = document.createElement('div');
        emptyState.className = 'text-muted small px-2 py-3 text-center d-none';
        emptyState.textContent = 'Ничего не найдено';
        list.after(emptyState);

        var applyStudentFilter = function () {
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

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyStudentFilter();
            });
        }
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

    document.querySelectorAll('[data-market-search]').forEach(function (input) {
        var grid = document.querySelector(input.dataset.marketGrid);
        if (!grid) return;

        var wrapper = input.closest('.market-goods-toolbar') || input.closest('.input-group');
        var goods = Array.from(grid.querySelectorAll('[data-market-good]'));
        var counter = wrapper ? wrapper.querySelector('[data-market-count]') : null;
        var clearButton = wrapper ? wrapper.querySelector('[data-market-clear]') : null;
        var emptyState = document.createElement('div');

        emptyState.className = 'gc-card market-search-empty text-center text-muted p-4 d-none';
        emptyState.innerHTML = '<div class="bg-body-tertiary text-muted rounded-circle d-inline-flex align-items-center justify-content-center fs-4 p-3 mb-3"><i class="fas fa-search"></i></div><h5>Ничего не найдено</h5><p class="mx-auto mb-0">Попробуйте изменить запрос.</p>';
        grid.after(emptyState);

        var applyMarketFilter = function () {
            var query = input.value.trim().toLowerCase();
            var visibleCount = 0;

            goods.forEach(function (good) {
                var haystack = (good.dataset.marketGoodText || good.textContent || '').toLowerCase();
                var isVisible = !query || haystack.indexOf(query) !== -1;
                good.classList.toggle('d-none', !isVisible);
                if (isVisible) visibleCount += 1;
            });

            emptyState.classList.toggle('d-none', visibleCount !== 0);
            if (counter) counter.textContent = visibleCount + ' из ' + goods.length;
            if (clearButton) clearButton.classList.toggle('d-none', !query);
        };

        input.addEventListener('input', applyMarketFilter);

        if (clearButton) {
            clearButton.addEventListener('click', function () {
                input.value = '';
                input.focus();
                applyMarketFilter();
            });
        }
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
            element.style.height = '200px';
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
                xaxis: { gridcolor: 'rgba(100, 116, 139, 0.12)', zeroline: false, showline: false },
                yaxis: { gridcolor: 'rgba(100, 116, 139, 0.12)', zeroline: false, showline: false },
                yaxis2: { side: 'right', gridcolor: 'rgba(100, 116, 139, 0.08)', zeroline: false, showline: false, overlaying: 'y' },
                margin: { l: 15, r: 20, b: 30, t: 3, pad: 0 },
                showlegend: false
            }, { staticPlot: false, displayModeBar: false, responsive: false });
        });
    }

    // Confirm dialogs
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-confirm]');
        if (link && !confirm(link.dataset.confirm)) event.preventDefault();
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
