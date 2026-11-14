export default function initNavigation() {
    const sidebar = document.getElementById('gcSidebar');
    const sidebarToggle = document.getElementById('gcSidebarToggle');
    const backdrop = document.getElementById('gcBackdrop');

    if (sidebar && sidebarToggle && backdrop) {
        let previousOverflow = '';
        const closeSidebar = function () {
            if (!sidebar.classList.contains('show')) return;
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
            sidebarToggle.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = previousOverflow;
            if (window.innerWidth <= 991) sidebarToggle.focus();
            sidebar.inert = window.innerWidth <= 991;
        };

        const openSidebar = function () {
            previousOverflow = document.body.style.overflow;
            sidebar.inert = false;
            sidebar.classList.add('show');
            backdrop.classList.add('show');
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
            sidebar.setAttribute('aria-label', 'Главная навигация');
            sidebarToggle.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('[data-sidebar-close]'))?.focus();
        };

        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
        });

        backdrop.addEventListener('click', closeSidebar);
        sidebar.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function (event) {
            if (!sidebar.classList.contains('show')) return;
            if (event.key === 'Escape') closeSidebar();
            if (event.key !== 'Tab') return;
            const controls = [...sidebar.querySelectorAll('a[href], button, input, select, [tabindex="0"]')].filter(element => element.getClientRects().length && !element.disabled);
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        });
        const syncSidebar = function () {
            if (window.innerWidth > 991) closeSidebar();
            sidebar.inert = window.innerWidth <= 991 && !sidebar.classList.contains('show');
        };
        window.addEventListener('resize', syncSidebar);
        syncSidebar();
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
}
