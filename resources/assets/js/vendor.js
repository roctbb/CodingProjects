import axios from 'axios';
import autosize from 'autosize';
import flatpickr from 'flatpickr';
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
    const linkifySelector = document.body.dataset.linkifySelector || 'div.markdown, [data-linkify]';
    const linkifySkipSelector = 'nav, aside, form, button, textarea, select, option, .dropdown-menu, .gc-sidebar';

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

    const replaceButtonText = function (button, pattern, replacement) {
        Array.from(button.childNodes).some(function (node) {
            if (node.nodeType !== Node.TEXT_NODE || !pattern.test(node.textContent)) return false;
            node.textContent = node.textContent.replace(pattern, replacement);
            return true;
        });
    };

    // Tab activation from URL hash
    if (url.match('#')) {
        var hash = '#' + url.split('#')[1];
        var tabEl = document.querySelector('a[href="' + hash + '"], [data-bs-target="' + hash + '"]');
        if (tabEl) new bootstrap.Tab(tabEl).show();
    }

    document.querySelectorAll('.nav-tabs a, [data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function (event) {
            var hash = event.target.getAttribute('href') || event.target.getAttribute('data-bs-target');
            if (hash && hash !== '#') window.location.hash = hash;
        });
    });

    if (window.flatpickr) {
        window.flatpickr('.date', { dateFormat: 'Y-m-d' });
    }

    applyLinkify(document);

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

    const stepTabs = document.querySelector('[data-step-content-tabs]');

    if (document.querySelector('[data-step-details-page]')) {
        document.querySelectorAll('blockquote').forEach(function (el) {
            el.classList.add('alert', 'alert-info', 'callout-border-info');
        });
        document.querySelectorAll('table').forEach(function (el) {
            el.classList.add('table', 'table-striped');
        });
    }

    if (document.querySelector('[data-perform-tabs]')) {
        var firstPane = document.querySelector('.tab-pane');
        if (firstPane) {
            firstPane.classList.remove('fade');
            firstPane.classList.add('show', 'active');
        }
    }

    if (stepTabs) {
        var firstTabPane = document.querySelector('.tab-pane');
        if (firstTabPane) firstTabPane.classList.add('active', 'show');

        if (stepTabs.dataset.zeroTheory === 'true') {
            var firstPill = document.querySelector('.task-pill');
            if (firstPill) firstPill.classList.add('active');
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

    // YandexGPT improve text
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

        fetch('/insider/yandexgpt/improve-text', {
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
                type: 'scatter', line: { shape: 'spline' }
            }];
            if (element.dataset.taskKeys && element.dataset.taskValues) {
                data.push({
                    x: JSON.parse(element.dataset.taskKeys),
                    y: JSON.parse(element.dataset.taskValues),
                    type: 'scatter', yaxis: 'y2', line: { shape: 'spline' }, fill: 'tonexty'
                });
            }
            window.Plotly.newPlot(element, data, {
                xaxis: { zeroline: false, showline: false },
                yaxis: { zeroline: false, showline: false },
                yaxis2: { side: 'right', zeroline: false, showline: false, overlaying: 'y' },
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
                document.getElementById('TSK_' + taskId).textContent = 'Очков опыта: ' + response.data.mark;
                document.getElementById('TSK_COM_' + taskId).textContent = response.data.comment;
            });
    });
});
