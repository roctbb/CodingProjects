import jq from 'jquery';
import axios from 'axios';
import autosize from 'autosize';
import flatpickr from 'flatpickr';
import hljs from 'highlight.js/lib/common';
import linkifyHtml from 'linkify-html';
import 'bootstrap';
import 'bootstrap-select';

const $ = window.jQuery || window.$ || jq;
window.$ = window.jQuery = $;
window.axios = axios;
window.autosize = autosize;
window.flatpickr = flatpickr;
window.hljs = hljs;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

var token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

document.addEventListener('DOMContentLoaded', function () {
    var url = document.location.toString();
    const linkifySelector = document.body.dataset.linkifySelector || 'div.markdown, [data-linkify]';
    const linkifySkipSelector = 'nav, aside, form, button, textarea, select, option, .dropdown-menu, .app-material-nav, .app-material-user';

    const applyLinkify = function (root) {
        root.querySelectorAll(linkifySelector).forEach(function (element) {
            if (element.dataset.linkifyReady === '1' || element.closest(linkifySkipSelector)) {
                return;
            }

            element.innerHTML = linkifyHtml(element.innerHTML, {
                target: '_blank'
            });
            element.dataset.linkifyReady = '1';
        });

        root.querySelectorAll(linkifySelector + ' a').forEach(function (link) {
            link.target = '_blank';
        });
    };

    const appendSubmittedSolution = function (target, date, text) {
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September',
            'October', 'November', 'December'
        ];

        const row = document.createElement('div');
        row.className = 'row my-3';

        const col = document.createElement('div');
        col.className = 'col';

        const card = document.createElement('div');
        card.className = 'card';

        const header = document.createElement('div');
        header.className = 'card-header';
        header.append('Дата сдачи: ' + date.getDate() + '.' + months[date.getMonth()] + '.' + date.getFullYear() + ' ' + date.getHours() + ':' + date.getMinutes());

        const status = document.createElement('div');
        status.className = 'float-right';
        const badge = document.createElement('span');
        badge.className = 'badge badge-secondary';
        badge.textContent = 'Решение еще не проверено';
        status.appendChild(badge);
        header.appendChild(status);

        const body = document.createElement('div');
        body.className = 'card-body p-3';
        body.style.whiteSpace = 'pre-wrap';
        body.dataset.linkify = '';
        body.textContent = text;

        card.appendChild(header);
        card.appendChild(body);
        col.appendChild(card);
        row.appendChild(col);
        target.appendChild(row);
        applyLinkify(row);
    };

    const replaceButtonText = function (button, pattern, replacement) {
        Array.from(button.childNodes).some(function (node) {
            if (node.nodeType !== Node.TEXT_NODE || !pattern.test(node.textContent)) {
                return false;
            }

            node.textContent = node.textContent.replace(pattern, replacement);
            return true;
        });
    };

    if (url.match('#')) {
        $('a[href="#' + url.split('#')[1] + '"]').tab('show');
    }

    $('.nav-tabs a').on('shown.bs.tab', function (event) {
        window.location.hash = event.target.hash;
    });

    $('.nav-link').on('click', function () {
        $('.nav-link.active').removeClass('active');
    });

    if (window.flatpickr) {
        window.flatpickr('.date', {
            dateFormat: 'Y-m-d'
        });
    }

    applyLinkify(document);

    if ($.fn.selectpicker) {
        $('.selectpicker').selectpicker();

        $('[data-selectpicker-value]').each(function () {
            const value = JSON.parse(this.dataset.selectpickerValue);
            $(this).selectpicker();
            $(this).selectpicker('val', value);
        });
    }

    if ($.fn.popover) {
        $(document).popover({
            selector: '[data-toggle=popover]',
            trigger: 'hover'
        });
        $('.popover-dismiss').popover({
            trigger: 'focus'
        });
    }

    if ($.fn.tooltip) {
        $('[data-toggle="tooltip"]').tooltip();
    }

    if ($.fn.dropdown) {
        $('[data-toggle="dropdown"]').dropdown();
    }

    if (window.MathJax && window.MathJax.typesetPromise) {
        window.MathJax.typesetPromise();
    }

    const stepTabs = document.querySelector('[data-step-content-tabs]');

    if (document.querySelector('[data-step-details-page]')) {
        $('blockquote').addClass('alert alert-info callout-border-info');
        $('table').addClass('table table-striped');
    }

    if (document.querySelector('[data-perform-tabs]')) {
        $('.tab-pane').first().removeClass('fade');
        $('.tab-pane').first().addClass('show active');
    }

    if (stepTabs) {
        $('.tab-pane').first().addClass('active show');

        if (stepTabs.dataset.zeroTheory === 'true') {
            $('.task-pill').first().addClass('active');
        }

        $('a[data-toggle="pill"]').on('shown.bs.tab', function (event) {
            if (!window.MathJax || !window.MathJax.typesetPromise) {
                return;
            }

            const targetPane = $(event.target.getAttribute('href'));

            if (targetPane.length) {
                window.MathJax.typesetPromise([targetPane[0]]).catch(function (error) {
                    console.warn('MathJax typeset error: ' + error.message);
                });
            }
        });

        $('.collapse').on('shown.bs.collapse', function () {
            if (!window.MathJax || !window.MathJax.typesetPromise) {
                return;
            }

            window.MathJax.typesetPromise([this]).catch(function (error) {
                console.warn('MathJax typeset error: ' + error.message);
            });
        });
    }

    if (window.autosize) {
        window.autosize(document.querySelectorAll('textarea'));
    }

    document.querySelectorAll('[data-progress-width]').forEach(function (progressBar) {
        const width = parseFloat(progressBar.dataset.progressWidth);

        if (Number.isNaN(width)) {
            return;
        }

        progressBar.style.width = Math.max(0, Math.min(100, width)) + '%';
    });

    document.querySelectorAll('[data-progress-height]').forEach(function (progressBar) {
        progressBar.style.height = progressBar.dataset.progressHeight;
    });

    document.querySelectorAll('[data-background-image]').forEach(function (element) {
        element.style.backgroundImage = 'url(' + element.dataset.backgroundImage + ')';
    });

    document.querySelectorAll('img[data-image-fallback]').forEach(function (image) {
        const loadFallback = function () {
            if (image.dataset.fallbackLoaded) {
                return;
            }

            image.dataset.fallbackLoaded = '1';
            image.src = image.dataset.imageFallback;
        };

        image.addEventListener('error', loadFallback);

        if (image.complete && image.naturalWidth === 0) {
            loadFallback();
        }
    });

    if (window.nbv) {
        document.querySelectorAll('[data-notebook-content]').forEach(function (notebook) {
            if (notebook.dataset.notebookRendered === '1') {
                return;
            }

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
            if (textarea.dataset.markdownEditorReady === '1') {
                return;
            }

            textarea.dataset.markdownEditorReady = '1';
            const editor = new window.EasyMDE({
                spellChecker: false,
                autosave: textarea.dataset.markdownAutosave === 'true',
                element: textarea,
            });

            if (textarea.id) {
                window.markdownEditors[textarea.id] = editor;
            }
        });
    }

    const materialNav = document.getElementById('appMaterialNav');
    const materialNavToggle = document.querySelector('[data-ui-nav-toggle]');
    const materialNavBackdrop = document.querySelector('[data-ui-nav-backdrop]');

    if (materialNav && materialNavToggle && materialNavBackdrop) {
        const closeMenu = function () {
            materialNav.classList.remove('is-open');
            materialNavBackdrop.hidden = true;
            materialNavToggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('app-material-nav-open');
        };

        const openMenu = function () {
            materialNav.classList.add('is-open');
            materialNavBackdrop.hidden = false;
            materialNavToggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('app-material-nav-open');
        };

        materialNavToggle.addEventListener('click', function () {
            if (materialNav.classList.contains('is-open')) {
                closeMenu();
                return;
            }

            openMenu();
        });

        materialNavBackdrop.addEventListener('click', closeMenu);
        materialNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1180) {
                closeMenu();
            }
        });
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-improve-text]');

        if (!button) {
            return;
        }

        const editor = window.markdownEditors && window.markdownEditors[button.dataset.fieldId];

        if (!editor) {
            alert('Редактор еще не загружен. Обновите страницу и попробуйте снова.');
            return;
        }

        const currentText = editor.value();

        if (!currentText.trim()) {
            alert('Поле пустое. Введите текст для улучшения.');
            return;
        }

        const originalButtonContents = Array.from(button.childNodes).map(function (node) {
            return node.cloneNode(true);
        });
        button.disabled = true;
        replaceButtonText(button, /Исправить|Улучшить/, 'Обработка...');

        fetch('/insider/yandexgpt/improve-text', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                text: currentText,
                action: button.dataset.improveText
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data.success) {
                    if (confirm('Текст был улучшен. Заменить оригинальный текст на улучшенную версию?')) {
                        editor.value(data.improved_text);
                    }
                    return;
                }

                alert('Ошибка: ' + (data.error || 'Не удалось улучшить текст'));
            })
            .catch(function (error) {
                console.error('Error:', error);
                alert('Произошла ошибка при обращении к сервису улучшения текста');
            })
            .finally(function () {
                button.disabled = false;
                button.replaceChildren.apply(button, originalButtonContents);
            });
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-plotly-resize-target]');

        if (!link || !window.Plotly) {
            return;
        }

        const content = document.getElementById('v-pills-tabContent');

        if (!content) {
            return;
        }

        const wrapper = document.createElement('span');

        while (content.firstChild) {
            wrapper.appendChild(content.firstChild);
        }

        content.appendChild(wrapper);
        const width = wrapper.offsetWidth;
        content.removeChild(wrapper);

        while (wrapper.firstChild) {
            content.appendChild(wrapper.firstChild);
        }

        window.Plotly.relayout(link.dataset.plotlyResizeTarget, {
            width: 1.5 * width + 'px',
            height: ''
        });
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-confirm]');

        if (link && !confirm(link.dataset.confirm)) {
            event.preventDefault();
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form.matches('[data-check-task]')) {
            return;
        }

        event.preventDefault();

        const taskId = form.dataset.taskId;
        const text = form.querySelector('[name=text]').value;

        window.axios.post(form.action, 'text=' + encodeURI(text))
            .then(function (response) {
                document.getElementById('TSK_' + taskId).textContent = 'Очков опыта: ' + response.data.mark;
                document.getElementById('TSK_COM_' + taskId).textContent = response.data.comment;
            });
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!form.matches('[data-send-solution]')) {
            return;
        }

        event.preventDefault();

        const date = new Date();
        const taskId = form.dataset.taskId;
        let text = form.querySelector('[name=text]').value;

        if (text === '') {
            alert('Нельзя сдать пустое решение!');
            return;
        }

        form.querySelector('[name=text]').value = '';
        const submitButton = form.querySelector('[type=submit]');
        submitButton.classList.remove('btn-success');
        submitButton.classList.add('btn-disabled');
        submitButton.disabled = true;
        submitButton.textContent = 'Подождите ...';

        window.axios.post(form.action, 'text=' + encodeURIComponent(text))
            .then(function () {
                submitButton.classList.add('btn-success');
                submitButton.classList.remove('btn-disabled');
                submitButton.removeAttribute('disabled');
                submitButton.textContent = 'Ответить';

                appendSubmittedSolution(document.getElementById('solutions_ajax' + taskId), date, text);
            });
    });

    if (window.Plotly) {
        document.querySelectorAll('[data-plotly-histogram]').forEach(function (element) {
            window.Plotly.newPlot(element, [{
                x: JSON.parse(element.dataset.plotlyHistogram),
                type: 'histogram',
                autobinx: false,
                marker: {
                    color: 'rgba(100, 200, 102, 0.7)',
                    line: {
                        color: 'rgba(100, 200, 102, 1)',
                        width: 1
                    }
                },
                opacity: 0.75,
                xbins: {
                    end: 110,
                    size: 15,
                    start: 0
                }
            }], {}, { displayModeBar: false });
        });

        document.querySelectorAll('[data-plotly-report-chart]').forEach(function (element) {
            element.style.height = '200px';

            const data = [{
                x: JSON.parse(element.dataset.pulseKeys),
                y: JSON.parse(element.dataset.pulseValues),
                type: 'scatter',
                line: { shape: 'spline' }
            }];

            if (element.dataset.taskKeys && element.dataset.taskValues) {
                data.push({
                    x: JSON.parse(element.dataset.taskKeys),
                    y: JSON.parse(element.dataset.taskValues),
                    type: 'scatter',
                    yaxis: 'y2',
                    line: { shape: 'spline' },
                    fill: 'tonexty'
                });
            }

            window.Plotly.newPlot(element, data, {
                xaxis: {
                    zeroline: false,
                    showline: false
                },
                yaxis: {
                    zeroline: false,
                    showline: false
                },
                yaxis2: {
                    side: 'right',
                    zeroline: false,
                    showline: false,
                    overlaying: 'y'
                },
                margin: {
                    l: 15,
                    r: 20,
                    b: 30,
                    t: 3,
                    pad: 0
                },
                showlegend: false
            }, { staticPlot: false, displayModeBar: false, responsive: false });
        });
    }
});
