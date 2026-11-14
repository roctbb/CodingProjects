import * as bootstrap from 'bootstrap';

export default function initProfileLinks() {
    const roomSettings = document.getElementById('room-settings');
    // Preserve bookmarks after moving personal history and room controls.
    const syncProfileHash = function () {
        if (window.location.hash === '#gc-history') {
            const destination = document.querySelector('[data-profile-history-url]');
            const historyDialog = document.getElementById('gc-history');
            if (destination) window.location.replace(destination.dataset.profileHistoryUrl);
            else if (historyDialog) bootstrap.Modal.getOrCreateInstance(historyDialog).show();
        }
        if (roomSettings && window.location.hash === '#room-settings') {
            bootstrap.Offcanvas.getOrCreateInstance(roomSettings).show();
        }
    };
    syncProfileHash();
    window.addEventListener('hashchange', syncProfileHash);
    if (roomSettings && roomSettings.hasAttribute('data-room-settings-open')) {
        bootstrap.Offcanvas.getOrCreateInstance(roomSettings).show();
    }
    if (roomSettings) {
        roomSettings.addEventListener('hidden.bs.offcanvas', function () {
            if (window.location.hash === '#room-settings') {
                history.replaceState(null, '', window.location.pathname + window.location.search + '#learning-avatar');
            }
            document.querySelector('[data-bs-target="#room-settings"]')?.focus();
        });
    }

}
