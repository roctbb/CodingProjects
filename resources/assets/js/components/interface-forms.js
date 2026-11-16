export default function initInterfaceForms() {
    const revealLinkedDisclosure = () => {
        let id;
        try { id = decodeURIComponent(window.location.hash.slice(1)); } catch { return; }
        const target = document.getElementById(id);
        let revealed = false;
        for (let node = target; node; node = node.parentElement) {
            if (node.tagName === 'DETAILS' && !node.open) { node.open = true; revealed = true; }
        }
        if (revealed) requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }));
    };
    revealLinkedDisclosure();
    window.addEventListener('load', revealLinkedDisclosure, { once: true });
    window.addEventListener('hashchange', revealLinkedDisclosure);

    document.querySelectorAll('[data-copy-text]').forEach(button => {
        const label = button.getAttribute('aria-label');
        const status = button.parentElement.querySelector('[data-copy-status]');
        const icon = button.querySelector('i');
        let timer;
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copyText);
                clearTimeout(timer);
                button.setAttribute('aria-label', 'Скопировано');
                icon?.classList.replace('fa-copy', 'fa-check');
                if (status) status.textContent = 'Скопировано';
                timer = setTimeout(() => {
                    button.setAttribute('aria-label', label);
                    icon?.classList.replace('fa-check', 'fa-copy');
                    if (status) status.textContent = '';
                }, 2000);
            } catch {
                if (status) status.textContent = 'Не удалось скопировать. Попробуйте ещё раз.';
                button.setAttribute('title', 'Не удалось скопировать. Попробуйте ещё раз.');
            }
        });
    });

    document.querySelectorAll('[data-sticker-field]').forEach(field => {
        const input = field.querySelector('[name="sticker"]');
        const preview = field.querySelector('#sticker-preview');
        const image = field.querySelector('#sticker-preview-image');
        const error = field.querySelector('#sticker-preview-error');
        const isCurrent = () => input.value.trim() !== '' && image.getAttribute('src') === input.value.trim();
        const update = () => {
            const value = input.value.trim();
            preview.hidden = true;
            error.hidden = true;
            image.removeAttribute('src');
            if (/^https?:\/\//i.test(value) || /^\/stickers\/[1-9][0-9]*\.png$/.test(value)) image.src = value;
        };
        image.addEventListener('load', () => { if (isCurrent()) { preview.hidden = false; error.hidden = true; } });
        image.addEventListener('error', () => { if (isCurrent()) { preview.hidden = true; error.hidden = false; } });
        input.addEventListener('input', update);
        update();
    });

    document.querySelectorAll('[data-icon-picker]').forEach(picker => {
        picker.addEventListener('change', event => {
            const option = event.target.closest('.gc-icon-option');
            if (!option) return;
            picker.querySelector('[data-icon-preview]').replaceChildren(option.querySelector('svg').cloneNode(true));
            picker.querySelector('[data-icon-label]').textContent = option.querySelector('.gc-icon-option__content > span').textContent;
            picker.open = false;
            picker.querySelector('summary').focus();
        });
    });

    document.querySelectorAll('[data-achievement-choice]').forEach(choice => {
        choice.addEventListener('change', () => {
            const form = document.getElementById(choice.dataset.achievementForm);
            form.elements.title.value = choice.dataset.title;
            form.elements.description.value = choice.dataset.description;
        });
    });

    // Browser validation must reveal required inputs placed in a disclosure.
    document.addEventListener('invalid', event => {
        for (let node = event.target.parentElement; node; node = node.parentElement) {
            if (node.tagName === 'DETAILS') node.open = true;
        }
    }, true);
}
