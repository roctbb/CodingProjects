import '@material/web/button/filled-button.js';
import '@material/web/button/filled-tonal-button.js';
import '@material/web/button/outlined-button.js';
import '@material/web/chips/assist-chip.js';
import '@material/web/labs/card/elevated-card.js';
import '@material/web/labs/card/outlined-card.js';
import '@material/web/fab/fab.js';
import '@material/web/iconbutton/icon-button.js';
import '@material/web/list/list.js';
import '@material/web/list/list-item.js';
import '@material/web/menu/menu.js';
import '@material/web/menu/menu-item.js';
import '@material/web/progress/linear-progress.js';
import '@material/web/tabs/tabs.js';
import '@material/web/tabs/secondary-tab.js';
import '@material/web/labs/segmentedbuttonset/outlined-segmented-button-set.js';
import '@material/web/labs/segmentedbutton/outlined-segmented-button.js';
import '@material/web/textfield/outlined-text-field.js';

function initMaterialTabs() {
    var tabsHosts = Array.from(document.querySelectorAll('md-tabs[data-md-tabs]'));
    tabsHosts.forEach(function (tabsHost) {
        if (tabsHost.dataset.mdTabsReady === '1') {
            return;
        }

        var tabs = Array.from(tabsHost.querySelectorAll('md-secondary-tab[data-target], md-secondary-tab[data-href]'));
        if (!tabs.length) {
            tabsHost.dataset.mdTabsReady = '1';
            return;
        }

        var targetTabs = tabs.filter(function (tab) {
            return !!tab.getAttribute('data-target');
        });
        if (!targetTabs.length) {
            tabsHost.dataset.mdTabsReady = '1';
            return;
        }

        var panelsSelector = tabsHost.getAttribute('data-md-tabs-panels');
        var panes = panelsSelector ? Array.from(document.querySelectorAll(panelsSelector)) : [];
        var defaultTarget = tabsHost.getAttribute('data-md-tabs-default') || targetTabs[0].getAttribute('data-target');
        var useHash = tabsHost.getAttribute('data-md-tabs-hash') === 'true';
        var paneActiveClass = tabsHost.getAttribute('data-md-tabs-active-class') || '';

        var activate = function (target) {
            if (!target) {
                return;
            }

            targetTabs.forEach(function (tab) {
                if (tab.getAttribute('data-target') === target) {
                    tab.setAttribute('active', '');
                } else {
                    tab.removeAttribute('active');
                }
            });

            panes.forEach(function (pane) {
                var isActive = pane.id === target;
                pane.hidden = !isActive;
                pane.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                if (paneActiveClass) {
                    pane.classList.toggle(paneActiveClass, isActive);
                }
            });
        };

        var initialTarget = defaultTarget;
        if (useHash && window.location.hash) {
            var hashTarget = window.location.hash.replace('#', '');
            var hashExists = targetTabs.some(function (tab) {
                return tab.getAttribute('data-target') === hashTarget;
            });
            if (hashExists) {
                initialTarget = hashTarget;
            }
        }
        activate(initialTarget);

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (event) {
                var href = tab.getAttribute('data-href');
                if (href) {
                    event.preventDefault();
                    window.location.assign(href);
                    return;
                }

                var target = tab.getAttribute('data-target');
                if (!target) {
                    return;
                }

                event.preventDefault();
                activate(target);
                if (useHash) {
                    history.replaceState(null, '', '#' + target);
                }
            });
        });

        tabsHost.dataset.mdTabsReady = '1';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initMaterialTabs();
});

document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-md-menu-trigger]');
    if (!trigger) {
        return;
    }

    var menuId = trigger.getAttribute('data-md-menu-trigger');
    if (!menuId) {
        return;
    }

    var menu = document.getElementById(menuId);
    if (!menu || typeof menu.show !== 'function') {
        return;
    }

    event.preventDefault();
    menu.show();
});

document.addEventListener('click', function (event) {
    var menuItemLink = event.target.closest('md-menu-item[data-href]');
    if (!menuItemLink) {
        return;
    }

    var href = menuItemLink.getAttribute('data-href');
    if (!href) {
        return;
    }

    event.preventDefault();
    if (menuItemLink.getAttribute('data-href-target') === '_blank') {
        window.open(href, '_blank', 'noopener,noreferrer');
        return;
    }
    window.location.assign(href);
});
