window._ = require('lodash');

const bootstrap = window.bootstrap || {};
const linkifyElement = require('linkifyjs/element');

window.bootstrap = bootstrap;

function onDomReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

function queryElements(selector, context) {
    var root = context || document;
    if (!selector) {
        return [];
    }
    if (typeof selector === 'string') {
        return Array.from(root.querySelectorAll(selector));
    }
    if (selector === window || selector === document || selector instanceof Element) {
        return [selector];
    }
    if (
        selector instanceof NodeList ||
        (typeof HTMLCollection !== 'undefined' && selector instanceof HTMLCollection) ||
        Array.isArray(selector)
    ) {
        return Array.from(selector);
    }
    return [];
}

function migrateLegacyBootstrapDataApi() {
    var selectors = [
        '[data-toggle]',
        '[data-target]',
        '[data-dismiss]',
        '[data-content]',
        '[data-placement]',
        '[data-html]',
        '[data-trigger]',
        '[data-container]'
    ].join(',');

    document.querySelectorAll(selectors).forEach(function (node) {
        if (node.hasAttribute('data-toggle') && !node.hasAttribute('data-bs-toggle')) {
            node.setAttribute('data-bs-toggle', node.getAttribute('data-toggle'));
        }
        if (node.hasAttribute('data-target') && !node.hasAttribute('data-bs-target')) {
            node.setAttribute('data-bs-target', node.getAttribute('data-target'));
        }
        if (node.hasAttribute('data-dismiss') && !node.hasAttribute('data-bs-dismiss')) {
            node.setAttribute('data-bs-dismiss', node.getAttribute('data-dismiss'));
        }
        if (node.hasAttribute('data-content') && !node.hasAttribute('data-bs-content')) {
            node.setAttribute('data-bs-content', node.getAttribute('data-content'));
        }
        if (node.hasAttribute('data-placement') && !node.hasAttribute('data-bs-placement')) {
            node.setAttribute('data-bs-placement', node.getAttribute('data-placement'));
        }
        if (node.hasAttribute('data-html') && !node.hasAttribute('data-bs-html')) {
            node.setAttribute('data-bs-html', node.getAttribute('data-html'));
        }
        if (node.hasAttribute('data-trigger') && !node.hasAttribute('data-bs-trigger')) {
            node.setAttribute('data-bs-trigger', node.getAttribute('data-trigger'));
        }
        if (node.hasAttribute('data-container') && !node.hasAttribute('data-bs-container')) {
            node.setAttribute('data-bs-container', node.getAttribute('data-container'));
        }
    });
}

function toBsPlacement(options) {
    var placement = options && (options.placement || options['data-bs-placement']);
    return placement || 'top';
}

function toBsTrigger(options) {
    var trigger = options && (options.trigger || options['data-bs-trigger']);
    return trigger || 'click';
}

function getRawContainerOption(el, options) {
    if (options && Object.prototype.hasOwnProperty.call(options, 'container')) {
        return options.container;
    }
    if (el && el.hasAttribute('data-bs-container')) {
        return el.getAttribute('data-bs-container');
    }
    if (el && el.hasAttribute('data-container')) {
        return el.getAttribute('data-container');
    }
    return undefined;
}

function normalizeBsContainer(container) {
    if (container === null || container === undefined) {
        return false;
    }
    if (typeof container === 'string') {
        var value = container.trim();
        if (!value || value === 'null' || value === 'undefined') {
            return false;
        }
        return value;
    }
    return container;
}

function hasBootstrapComponent(name) {
    return Boolean(bootstrap && bootstrap[name] && typeof bootstrap[name].getOrCreateInstance === 'function');
}

function initPopoverForElement(el, options) {
    if (!el || !hasBootstrapComponent('Popover')) {
        return;
    }
    var existing = bootstrap.Popover.getInstance(el);
    if (!existing) {
        new bootstrap.Popover(el, {
            html: Boolean(options && (options.html || options['data-bs-html'] || el.getAttribute('data-bs-html') === 'true')),
            content: (options && options.content) || el.getAttribute('data-bs-content') || el.getAttribute('data-content') || '',
            placement: toBsPlacement(options),
            trigger: toBsTrigger(options),
            container: normalizeBsContainer(getRawContainerOption(el, options))
        });
    }
}

function initTooltipForElement(el, options) {
    if (!el || !hasBootstrapComponent('Tooltip')) {
        return;
    }
    var existing = bootstrap.Tooltip.getInstance(el);
    if (!existing) {
        new bootstrap.Tooltip(el, {
            placement: toBsPlacement(options),
            trigger: toBsTrigger(options),
            container: normalizeBsContainer(getRawContainerOption(el, options))
        });
    }
}

function normalizeDateFormat(format) {
    if (!format) {
        return 'Y-m-d';
    }
    if (format === 'yy-mm-dd' || format === 'yyyy-mm-dd') {
        return 'Y-m-d';
    }
    return format;
}

function initDatepickerForElement(el, options) {
    if (!el) {
        return;
    }
    if (typeof window.flatpickr !== 'function') {
        return;
    }
    var opts = options || {};
    var yearRange = (opts.yearRange || '').split(':');
    var minDate = yearRange[0] ? new Date(yearRange[0] + '-01-01') : undefined;
    var maxDate = yearRange[1] ? new Date(yearRange[1] + '-12-31') : undefined;
    var dateFormat = normalizeDateFormat(opts.dateFormat);

    if (el.__cpuiDatepicker && typeof el.__cpuiDatepicker.destroy === 'function') {
        el.__cpuiDatepicker.destroy();
    }

    el.__cpuiDatepicker = window.flatpickr(el, {
        allowInput: true,
        dateFormat: dateFormat,
        minDate: minDate,
        maxDate: maxDate,
        defaultDate: el.value || null
    });

    if (el.value) {
        var selected = new Date(el.value);
        if (!Number.isNaN(selected.getTime()) && typeof el.__cpuiDatepicker.setDate === 'function') {
            el.__cpuiDatepicker.setDate(selected, false);
        }
    }

}

window.axios = require('axios');
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

const EasyMDE = require('easymde');
window.EasyMDE = EasyMDE.default || EasyMDE;

window.linkify = require('linkifyjs');
window.linkifyElement = linkifyElement;

window.hljs = require('highlight.js');
if (window.hljs && !window.hljs.initHighlightingOnLoad) {
    window.hljs.initHighlightingOnLoad = function initHighlightingOnLoad() {
        window.addEventListener('DOMContentLoaded', function onReady() {
            if (window.hljs.highlightAll) {
                window.hljs.highlightAll();
            }
        });
    };
}

window.Prism = require('prismjs');
require('prismjs/components/prism-python');
window.marked = require('marked');

window.autosize = require('autosize');
window.Dropzone = require('dropzone');
window.List = require('list.js');

window.CPUI = {
    migrateLegacyBootstrapDataApi: migrateLegacyBootstrapDataApi,
    initDatepickers: function initDatepickers(selector) {
        queryElements(selector || '.date').forEach(function (el) {
            initDatepickerForElement(el, {
                changeMonth: true,
                changeYear: true,
                yearRange: '1940:2025',
                dateFormat: 'yy-mm-dd'
            });
        });
    },
    initPopovers: function initPopovers(selector, options) {
        var opts = options || {};
        var nodes = opts.selector ? queryElements(opts.selector) : queryElements(selector || '[data-toggle="popover"],[data-bs-toggle="popover"]');
        nodes.forEach(function (el) {
            initPopoverForElement(el, opts);
        });
    },
    initTooltips: function initTooltips(selector, options) {
        var opts = options || {};
        var nodes = opts.selector ? queryElements(opts.selector) : queryElements(selector || '[data-toggle="tooltip"],[data-bs-toggle="tooltip"]');
        nodes.forEach(function (el) {
            initTooltipForElement(el, opts);
        });
    },
    applyLinkify: function applyLinkify(selector) {
        queryElements(selector || 'div').forEach(function (el) {
            linkifyElement(el, { target: '_blank' });
        });
    },
    activateTabByHash: function activateTabByHash() {},
    enableHashSyncForTabs: function enableHashSyncForTabs() {}
};

onDomReady(function () {
    migrateLegacyBootstrapDataApi();
    window.CPUI.initDatepickers();
    window.CPUI.initPopovers();
    window.CPUI.initTooltips();
});
