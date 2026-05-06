import nbv from './nbv.js';

function renderNotebooks() {
    const renderer = window.nbv || nbv;

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
        } catch (error) {
            console.error('Failed to render notebook:', error);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderNotebooks);
} else {
    renderNotebooks();
}
