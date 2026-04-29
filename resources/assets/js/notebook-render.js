document.addEventListener('DOMContentLoaded', function () {
    if (!window.nbv) {
        return;
    }

    document.querySelectorAll('[data-notebook-content]').forEach(function (notebook) {
        window.nbv.render(JSON.parse(notebook.dataset.notebookContent), notebook);
    });
});
