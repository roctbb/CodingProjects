export default function initAvatarFrameDialogs() {
    var closeMarketFrameModal = function (modal) {
        if (!modal) return;

        modal.close();
        modal.classList.remove('is-open');
        document.body.classList.remove('market-digital-frame-modal-open');
    };

    document.querySelectorAll('[data-market-frame-modal]').forEach(function (modal) {
        var wrapper = modal.closest('[data-avatar-frame-editor]') || document;
        var openButton = wrapper.querySelector('[data-market-frame-modal-open]');
        var focusTarget = modal.querySelector('.market-digital-frame-modal__body select, .market-digital-frame-modal__body input, .market-digital-frame-modal__body button');

        if (openButton) {
            openButton.addEventListener('click', function () {
                modal.showModal();
                modal.classList.add('is-open');
                document.body.classList.add('market-digital-frame-modal-open');
                if (focusTarget) focusTarget.focus({ preventScroll: true });
            });
        }

        modal.querySelectorAll('[data-market-frame-modal-close]').forEach(function (button) {
            button.addEventListener('click', function () {
                closeMarketFrameModal(modal);
                if (openButton) openButton.focus({ preventScroll: true });
            });
        });
        modal.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') return;
            const controls = [...modal.querySelectorAll('button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled)')].filter(el => el.getClientRects().length);
            const target = event.shiftKey ? controls.at(-1) : controls[0];
            if (document.activeElement === (event.shiftKey ? controls[0] : controls.at(-1))) {
                event.preventDefault(); target?.focus();
            }
        });
        modal.addEventListener('cancel', function (event) {
            event.preventDefault();
            closeMarketFrameModal(modal);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        document.querySelectorAll('[data-market-frame-modal].is-open').forEach(function (modal) {
            closeMarketFrameModal(modal);
            var wrapper = modal.closest('[data-avatar-frame-editor]');
            var openButton = wrapper ? wrapper.querySelector('[data-market-frame-modal-open]') : null;
            if (openButton) openButton.focus({ preventScroll: true });
        });
    });

}
