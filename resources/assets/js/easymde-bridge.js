(function () {
    if (window.EasyMDE) {
        return;
    }

    if (window.module && window.module.exports) {
        window.EasyMDE = window.module.exports.default || window.module.exports;
        return;
    }

    if (window.exports && (window.exports.default || window.exports.EasyMDE)) {
        window.EasyMDE = window.exports.default || window.exports.EasyMDE;
    }
})();
