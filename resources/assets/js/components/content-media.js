export function initContentMedia() {
    document.querySelectorAll('[data-background-image]').forEach(function (element) {
        element.style.backgroundImage = 'url(' + element.dataset.backgroundImage + ')';
    });

    document.querySelectorAll('img[data-image-fallback]').forEach(function (image) {
        var loadFallback = function () {
            if (image.dataset.fallbackLoaded) return;
            image.dataset.fallbackLoaded = '1';
            image.src = image.dataset.imageFallback;
        };
        image.addEventListener('error', loadFallback);
        if (image.complete && image.naturalWidth === 0) loadFallback();
    });

}

export function initCodeHighlighting() {
    if (window.hljs) {
        document.querySelectorAll('pre code').forEach(function (block) {
            if (block.dataset.highlighted || block.closest('.notebook-container') || block.querySelector('.token')) return;
            window.hljs.highlightElement(block);
        });
    }
}
