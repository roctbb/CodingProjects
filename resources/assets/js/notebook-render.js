import nbv from './nbv.js';

function renderNotebooks() {
    const renderer = window.nbv || nbv;
    const rendered = [];

    if (!renderer) {
        return;
    }

    document.querySelectorAll('[data-notebook-content]').forEach(function (notebook) {
        if (notebook.dataset.notebookRendered === '1') {
            return;
        }

        try {
            renderer.render(JSON.parse(notebook.dataset.notebookContent), notebook);
            notebook.dataset.notebookRendered = '1';
            rendered.push(notebook);
        } catch (error) {
            console.error('Failed to render notebook:', error);
        }
    });

    // Notebook cells are inserted after MathJax's initial page scan may finish.
    if (rendered.length && window.MathJax && window.MathJax.startup) {
        window.MathJax.startup.promise
            .then(function () { return window.MathJax.typesetPromise(rendered); })
            .catch(function (error) { console.error('Failed to typeset notebook:', error); });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderNotebooks);
} else {
    renderNotebooks();
}
