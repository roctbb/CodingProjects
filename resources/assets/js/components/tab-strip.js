export default function initTabStrips() {
    document.querySelectorAll('[data-tab-strip]').forEach(strip => {
        const viewport = strip.querySelector('.gc-page-tabs, .gc-segmented-tabs');
        const previous = strip.querySelector('[data-tab-scroll="previous"]');
        const next = strip.querySelector('[data-tab-scroll="next"]');
        if (!viewport || !previous || !next) return;

        const update = () => {
            const overflowing = viewport.scrollWidth > strip.clientWidth + 2;
            previous.hidden = next.hidden = !overflowing;
            previous.disabled = viewport.scrollLeft <= 2;
            next.disabled = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 2;
        };
        const reveal = target => {
            if (!target) return;
            const bounds = viewport.getBoundingClientRect();
            const current = target.getBoundingClientRect();
            if (current.left < bounds.left + 2) viewport.scrollLeft += current.left - bounds.left - 2;
            else if (current.right > bounds.right - 2) viewport.scrollLeft += current.right - bounds.right + 2;
            update();
        };
        let frame;
        const refresh = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                update();
                reveal(viewport.querySelector('[aria-current="page"], .active'));
            });
        };
        [previous, next].forEach(button => button.addEventListener('click', () => {
            const direction = button === previous ? -1 : 1;
            viewport.scrollBy({ left: direction * viewport.clientWidth * .8, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        }));
        viewport.addEventListener('scroll', update, { passive: true });
        viewport.addEventListener('focusin', event => reveal(event.target.closest('.nav-link')));
        viewport.addEventListener('shown.bs.tab', event => reveal(event.target));
        if (window.ResizeObserver) new ResizeObserver(refresh).observe(strip);
        window.addEventListener('resize', refresh);
        document.fonts?.ready.then(refresh);
        refresh();
    });
}
