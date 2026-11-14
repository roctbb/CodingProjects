export default function initCodeChecking() {
    document.querySelectorAll('[data-execution-workspace]').forEach(root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = 'true';
        const load = () => import('../code-checking/workspace').then(module => module.workspace(root)).catch(() => {
            root.textContent = 'Не удалось загрузить форму сдачи. Обновите страницу.';
        });
        if (!globalThis.IntersectionObserver) {load(); return;}
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {observer.disconnect(); load();}
        });
        observer.observe(root);
    });
    document.querySelectorAll('[data-checker-editor]').forEach(root => {
        import('../code-checking/authoring').then(module => module.authoring(root)).catch(() => {
            root.textContent = 'Не удалось загрузить настройки проверки. Обновите страницу.';
        });
    });
}
