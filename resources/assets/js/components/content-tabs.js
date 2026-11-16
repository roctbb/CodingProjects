import * as bootstrap from 'bootstrap';

export default function initContentTabs({ onChange = () => {} } = {}) {
    const stepTabs = document.querySelector('[data-step-content-tabs]');
    const performTabs = document.querySelector('[data-perform-tabs]');
    const url = document.location.toString();
    const getTabTriggerForHash = function (hash) {
        if (!hash || hash === '#') return null;

        return Array.from(document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"]')).find(function (el) {
            return (el.getAttribute('data-bs-target') || el.getAttribute('href')) === hash;
        });
    };

    // Tab activation from URL hash. Step and perform pages initialize their tabs below
    // so they can avoid the browser's eager anchor scroll to hidden tab panes.
    if (url.match('#') && !stepTabs && !performTabs) {
        var hash = window.location.hash;
        var tabEl = getTabTriggerForHash(hash);
        if (tabEl) new bootstrap.Tab(tabEl).show();
    }

    document.querySelectorAll('[data-market-tab-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            const trigger = getTabTriggerForHash(link.getAttribute('href'));
            if (!trigger) return;
            event.preventDefault();
            bootstrap.Tab.getOrCreateInstance(trigger).show();
        });
    });
    if (document.getElementById('marketTabs')) {
        // A tab hash selects a section, not a position below the page header.
        const showMarketSection = function () {
            const trigger = getTabTriggerForHash(window.location.hash);
            if (!trigger) return;
            bootstrap.Tab.getOrCreateInstance(trigger).show();
            window.scrollTo(0, 0);
        };
        window.addEventListener('hashchange', function () {
            showMarketSection();
        });
        if (window.location.hash) window.addEventListener('load', showMarketSection, { once: true });
    }

    var syncTabContentHeight = function (container) {
        if (!container) return;
        var activePane = container.querySelector('.tab-pane.active');
        if (!activePane) return;

        window.requestAnimationFrame(function () {
            var minimumHeight = Math.min(activePane.offsetHeight, Math.max(320, window.innerHeight * 0.62));
            var nextMinHeight = Math.max(0, Math.round(minimumHeight));
            container.style.minHeight = nextMinHeight + 'px';
        });
    };

    document.querySelectorAll('.tab-content').forEach(syncTabContentHeight);

    document.querySelectorAll('.nav-tabs a, [data-bs-toggle="tab"], [data-bs-toggle="pill"]').forEach(function (el) {
        el.addEventListener('shown.bs.tab', function (event) {
            var hash = event.target.getAttribute('data-bs-target') || event.target.getAttribute('href');
            var targetPane = null;

            if (hash && hash !== '#') {
                if (!event.target.hasAttribute('data-no-tab-history')) history.replaceState(null, '', window.location.pathname + window.location.search + hash);
                targetPane = document.querySelector(hash);
            }

            syncTabContentHeight(targetPane ? targetPane.closest('.tab-content') : null);
            onChange();
        });
    });

    if (performTabs) {
        var performHash = window.location.hash;
        var performHashTab = getTabTriggerForHash(performHash);
        var performHashPane = performHash ? performTabs.querySelector(performHash) : null;

        if (performHashTab && performHashPane) {
            new bootstrap.Tab(performHashTab).show();
        } else {
            var activePerformPane = performTabs.querySelector('.tab-pane.active');
            if (!activePerformPane) {
                activePerformPane = performTabs.querySelector('.tab-pane');
                if (activePerformPane) activePerformPane.classList.add('show', 'active');
            }

            if (activePerformPane) {
                var activePerformTab = getTabTriggerForHash('#' + activePerformPane.id);
                if (activePerformTab) {
                    activePerformTab.classList.add('active');
                    activePerformTab.setAttribute('aria-selected', 'true');
                }
            }
        }

        syncTabContentHeight(performTabs);
    }

    if (stepTabs) {
        var stepHash = window.location.hash;
        var stepHashTab = getTabTriggerForHash(stepHash);

        if (stepHashTab) {
            new bootstrap.Tab(stepHashTab).show();

            window.requestAnimationFrame(function () {
                window.scrollTo({ top: 0, behavior: 'auto' });
                syncTabContentHeight(document.querySelector('.step-content'));
                onChange();
            });
        } else {
            var activeStepPane = document.querySelector('.step-content .tab-pane.active');
            if (!activeStepPane) {
                var firstTabPane = document.querySelector('.step-content .tab-pane');
                if (firstTabPane) firstTabPane.classList.add('active', 'show');
            }

            if (stepTabs.dataset.zeroTheory === 'true') {
                var activePill = document.querySelector('.step-top-tabs .nav-link.active');
                var firstPill = document.querySelector('.task-pill');
                if (!activePill && firstPill) firstPill.classList.add('active');
            }

            syncTabContentHeight(document.querySelector('.step-content'));
        }
    }
}
