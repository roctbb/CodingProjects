const replaceButtonText = function (button, pattern, replacement) {
    Array.from(button.childNodes).some(function (node) {
        if (node.nodeType !== Node.TEXT_NODE || !pattern.test(node.textContent)) return false;
        node.textContent = node.textContent.replace(pattern, replacement);
        return true;
    });
};

function enhancePreview(preview) {
    preview.classList.add('markdown');
    preview.querySelectorAll('pre code').forEach(code => window.hljs?.highlightElement(code));
    window.MathJax?.startup?.promise?.then(() => window.MathJax.typesetPromise([preview])).catch(() => {});
}

function serverPreview() {
    let sequence = 0, timer, controller, cachedText, cachedHtml;
    const render = (text, preview) => {
        clearTimeout(timer); controller?.abort();
        const current = ++sequence;
        if (text === cachedText) { requestAnimationFrame(() => enhancePreview(preview)); return cachedHtml; }
        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const { data } = await window.axios.post('/insider/authoring/preview', { text }, { signal:controller.signal });
                if (current !== sequence) return;
                cachedText = text; cachedHtml = data.html;
                preview.innerHTML = data.html; enhancePreview(preview);
            } catch (error) {
                if (current !== sequence || error.code === 'ERR_CANCELED') return;
                const message = document.createElement('p'); message.className = 'gc-help-text'; message.textContent = 'Не удалось загрузить предпросмотр. Текст сохранён в редакторе.';
                const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary btn-sm'; retry.textContent = 'Повторить';
                retry.addEventListener('click', () => { preview.innerHTML = render(text, preview); });
                preview.replaceChildren(message, retry);
            }
        }, 200);
        return '<p class="gc-help-text" role="status">Загружаем предпросмотр…</p>';
    };
    return render;
}

export function initMarkdownEditors() {
    if (!window.EasyMDE) return;
    window.markdownEditors = window.markdownEditors || {};
    document.querySelectorAll('textarea[data-markdown-editor]').forEach(textarea => {
        if (textarea.dataset.markdownBound === '1') return;
        textarea.dataset.markdownBound = '1';
        const field = textarea.closest('[data-field]');
        const label = field?.querySelector('label');
        const notebook = textarea.hasAttribute('data-notebook-source') ? textarea.form?.querySelector('[data-notebook-toggle]') : null;
        window.autosize?.destroy(textarea);
        const activate = () => {
            const previous = window.markdownEditors[textarea.id];
            if (previous) { previous.toTextArea(); delete window.markdownEditors[textarea.id]; }
            const raw = notebook?.checked;
            textarea.classList.toggle('gc-code-source', !!raw);
            field?.querySelectorAll('[data-improve-text]').forEach(button => button.disabled = !!raw);
            if (raw) { textarea.dataset.markdownEditorReady = 'notebook'; return; }
            const mde = window.EasyMDE;
            const config = {
                element:textarea, spellChecker:false, forceSync:true, autoDownloadFontAwesome:false,
                minHeight:textarea.dataset.editorCompact === 'true' ? '7rem' : '15rem',
                status:false, previewRender:serverPreview(),
                toolbar:[
                    { name:'bold', action:mde.toggleBold, className:'fa fa-bold', title:'Полужирный' },
                    { name:'italic', action:mde.toggleItalic, className:'fa fa-italic', title:'Курсив' },
                    { name:'heading', action:mde.toggleHeadingSmaller, className:'fa fa-heading', title:'Заголовок' },
                    '|',
                    { name:'unordered-list', action:mde.toggleUnorderedList, className:'fa fa-list-ul', title:'Список' },
                    { name:'code', action:mde.toggleCodeBlock, className:'fa fa-code', title:'Код' },
                    { name:'link', action:mde.drawLink, className:'fa fa-link', title:'Ссылка' },
                    { name:'image', action:mde.drawImage, className:'fa fa-image', title:'Изображение' },
                    '|',
                    { name:'preview', action:mde.togglePreview, className:'fa fa-eye', noDisable:true, title:'Предпросмотр' },
                ],
            };
            if (textarea.dataset.markdownAutosave === 'true') config.autosave = { enabled:true, uniqueId:window.location.pathname+':'+(textarea.id || textarea.name), clearOnSubmit:true };
            const editor = new mde(config);
            window.markdownEditors[textarea.id] = editor; textarea.dataset.markdownEditorReady = '1';
            const input = editor.codemirror.getInputField();
            input.setAttribute('aria-label', textarea.dataset.editorLabel || label?.textContent.trim() || textarea.name);
            if (label?.id) input.setAttribute('aria-labelledby', label.id);
            const described=[document.getElementById(textarea.id+'-help')?.id, field?.querySelector('[data-field-error]:not([hidden])')?.id].filter(Boolean).join(' ');
            if(described)input.setAttribute('aria-describedby',described);
            editor.codemirror.on('change', () => textarea.dispatchEvent(new Event('input', {bubbles:true})));
            editor.codemirror.on('refresh', () => editor.codemirror.getWrapperElement().querySelectorAll('button').forEach(button => button.type = 'button'));
        };
        activate(); notebook?.addEventListener('change', activate);
        label?.addEventListener('click', event => {
            const editor = window.markdownEditors[textarea.id];
            if (editor) { event.preventDefault(); if(editor.isPreviewActive()) window.EasyMDE.togglePreview(editor); editor.codemirror.focus(); }
        });
        textarea.addEventListener('invalid', event => {
            const editor = window.markdownEditors[textarea.id];
            if (!editor) return;
            event.preventDefault();
            if(editor.isPreviewActive()) window.EasyMDE.togglePreview(editor);
            const error = field?.querySelector('[data-field-error]');
            if (error) { error.textContent = textarea.validationMessage; error.hidden = false; error.dataset.clientError = '1'; }
            editor.codemirror.getWrapperElement().scrollIntoView({block:'center'}); editor.codemirror.focus();
        });
    });
}

export function initImproveText() {
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
                    if (confirm(editor.value() === currentText ? 'Заменить текст на улучшенную версию?' : 'Текст изменился после отправки. Заменить текущий текст на полученную версию?')) editor.value(data.improved_text);
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
}
