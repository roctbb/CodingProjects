import * as bootstrap from 'bootstrap';
import { Russian } from 'flatpickr/dist/l10n/ru.js';

export default function initInterfaceControls() {
    if (window.flatpickr) {
        window.flatpickr('.date', { dateFormat: 'Y-m-d', locale: Russian });
    }

    // Initialize Bootstrap tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    // Initialize Bootstrap popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });

}
