import linkifyHtml from 'linkify-html';

export default function initReadingWorkspace() {
    const stepDetailsPage = document.querySelector('[data-step-details-page]');
    const stepTabs = document.querySelector('[data-step-content-tabs]');
    const linkifySelector = document.body.dataset.linkifySelector || 'div.markdown, [data-linkify]';
    const linkifySkipSelector = 'nav, aside, form, button, textarea, select, option, .dropdown-menu, .gc-sidebar';

    const applyLinkify = function (root) {
        root.querySelectorAll(linkifySelector).forEach(function (element) {
            if (element.dataset.linkifyReady === '1' || element.closest(linkifySkipSelector)) return;
            element.innerHTML = linkifyHtml(element.innerHTML, { target: '_blank', rel: 'noopener noreferrer', ignoreTags: ['script', 'style', 'pre', 'code'] });
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
            image.decoding = 'async';

            const imageSource = image.currentSrc || image.src;
            const isSvg = function () {
                if (!imageSource) return false;

                try {
                    return new URL(imageSource, document.baseURI).pathname.toLowerCase().endsWith('.svg');
                } catch (error) {
                    return false;
                }
            }();
            const imageLink = image.closest('a');
            const singleImageLink = imageLink && imageLink.children.length === 1 ? imageLink : null;
            const mediaNode = singleImageLink || image;
            const figure = document.createElement('figure');
            figure.className = 'step-media-figure';

            mediaNode.before(figure);

            if (singleImageLink) {
                singleImageLink.classList.add('step-media-link');
                if (isSvg) singleImageLink.classList.add('step-media-link--svg');
                singleImageLink.target = '_blank';
                singleImageLink.rel = 'noopener';
                figure.appendChild(singleImageLink);
            } else if (!imageLink && imageSource) {
                const link = document.createElement('a');
                link.className = 'step-media-link';
                if (isSvg) link.classList.add('step-media-link--svg');
                link.href = imageSource;
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
        const outline = document.querySelector('[data-reading-outline]');
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
            if (outline) outline.hidden = true;
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
        if (outline) {
            outline.querySelector('nav').innerHTML = links.join('');
            outline.hidden = false;
        }
        layout.classList.add('has-toc');
        updateStepReadingState();
    };

    const enhanceStepCodeBlocks = function (root) {
        root.querySelectorAll('.markdown pre:not([data-step-code-ready]), .markdown-big pre:not([data-step-code-ready])').forEach(function (pre) {
            const code = pre.querySelector('code');
            if (!code || pre.closest('.notebook-container, .editor-preview')) return;
            pre.dataset.stepCodeReady = '1';
            pre.tabIndex = 0;
            pre.setAttribute('role', 'region');
            pre.setAttribute('aria-label', 'Пример кода, прокручивается по горизонтали');
            const wrapper = document.createElement('div');
            wrapper.className = 'gc-code-example';
            const toolbar = document.createElement('div');
            toolbar.className = 'gc-code-example__toolbar';
            const label = document.createElement('span');
            const language = Array.from(code.classList).find(name => name.startsWith('language-'));
            label.textContent = language ? language.slice(9) : 'Пример кода';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm';
            button.dataset.copyCode = '';
            button.textContent = 'Копировать';
            button.setAttribute('aria-live', 'polite');
            button.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(code.textContent);
                    button.textContent = 'Скопировано';
                } catch {
                    button.textContent = 'Выделите код вручную';
                }
                window.setTimeout(() => button.textContent = 'Копировать', 1800);
            });
            toolbar.append(label, button);
            pre.before(wrapper);
            wrapper.append(toolbar, pre);
        });
        root.querySelectorAll('.markdown table, .markdown-big table').forEach(function (table) {
            if (table.closest('.gc-table-scroll, .notebook-container')) return;
            const scroll = document.createElement('div');
            scroll.className = 'gc-table-scroll';
            scroll.tabIndex = 0;
            scroll.setAttribute('role', 'region');
            scroll.setAttribute('aria-label', table.caption?.textContent || 'Таблица, прокручивается по горизонтали');
            table.before(scroll);
            scroll.append(table);
        });
    };

    const updateStepReadingState = function () {
        const layout = document.querySelector('[data-step-content-layout]');
        const progress = document.querySelector('[data-step-reading-progress]');
        const fill = progress ? progress.querySelector('span') : null;
        const activePane = layout ? layout.querySelector('.tab-pane.active.markdown') : null;

        if (!progress || !fill) return;
        if (!activePane) { progress.hidden = true; return; }

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

    applyLinkify(document);
    enhanceStepMedia(document);
    enhanceStepCodeBlocks(document);
    syncStepReadingToc();
    document.querySelector('[data-reading-outline] nav')?.addEventListener('click', event => {
        if (event.target.closest('a')) event.currentTarget.closest('details').open = false;
    });
    updateStepReadingState();

    document.addEventListener('gc:notebook-rendered', () => { syncStepReadingToc(); updateStepReadingState(); });
    window.addEventListener('scroll', updateStepReadingState, { passive: true });
    window.addEventListener('resize', updateStepReadingState);

    if (window.MathJax && window.MathJax.typesetPromise) {
        window.MathJax.typesetPromise();
    }

    if (stepDetailsPage) {
        document.querySelectorAll('table').forEach(function (el) {
            el.classList.add('table', 'table-striped');
        });
    }

    if (stepTabs) {
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

    return {
        refresh() { syncStepReadingToc(); updateStepReadingState(); },
    };
}
