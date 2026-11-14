function initPasswords() {
    document.querySelectorAll('[data-password-target]').forEach(button => {
        const input = document.getElementById(button.dataset.passwordTarget);
        if (!input) return;
        const label = button.getAttribute('aria-label');
        button.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.setAttribute('aria-label', show ? label.replace('Показать', 'Скрыть') : label);
            button.querySelector('i')?.classList.toggle('fa-eye', !show);
            button.querySelector('i')?.classList.toggle('fa-eye-slash', show);
        });
    });
    document.querySelectorAll('[data-password-confirm]').forEach(confirmation => {
        const password = document.getElementById(confirmation.dataset.passwordConfirm);
        if (!password) return;
        const update = () => confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'Пароли не совпадают.' : '');
        password.addEventListener('input', update);
        confirmation.addEventListener('input', update);
        update();
    });
}

function initRegistration() {
    document.querySelectorAll('[data-registration-flow]').forEach(form => {
        const steps = [...form.querySelectorAll('[data-registration-step]')];
        const progress = form.querySelector('[data-registration-progress]');
        const next = form.querySelector('[data-registration-next]');
        const back = form.querySelector('[data-registration-back]');
        const submit = form.querySelector('[type=submit]');
        let current = 0;
        const show = (index, focus = false) => {
            current = index;
            steps.forEach((step, i) => step.hidden = i !== index);
            progress.querySelectorAll('button').forEach((button, i) => {
                if (i === index) button.setAttribute('aria-current', 'step');
                else button.removeAttribute('aria-current');
            });
            next.hidden = index !== 0;
            back.hidden = index === 0;
            submit.hidden = index === 0;
            if (focus) {
                const legend = steps[index].querySelector('legend');
                legend.tabIndex = -1;
                legend.focus({preventScroll:true});
                legend.scrollIntoView({block:'start'});
            }
        };
        const validate = step => {
            const invalid = [...step.querySelectorAll('input, select, textarea')].find(input => input.willValidate && !input.validity.valid);
            if (invalid) { invalid.focus(); invalid.reportValidity(); return false; }
            return true;
        };
        const advance = () => { if (validate(steps[0])) show(1, true); };
        next.addEventListener('click', advance);
        back.addEventListener('click', () => show(0, true));
        progress.querySelectorAll('[data-registration-go]').forEach(button => button.addEventListener('click', () => {
            Number(button.dataset.registrationGo) === 0 ? show(0, true) : advance();
        }));
        form.querySelectorAll('[data-field-error-link]').forEach(link => link.addEventListener('click', event => {
            const id = decodeURIComponent(link.hash.slice(1));
            const field = document.getElementById(id === 'g-recaptcha-response' ? 'captcha-field' : id);
            if (!field) return;
            event.preventDefault();
            const index = steps.findIndex(step => step.contains(field));
            if (index >= 0) show(index);
            field.scrollIntoView({block:'center'});
            field.focus({preventScroll:true});
        }));
        form.addEventListener('submit', event => {
            if (current === 0) { event.preventDefault(); advance(); return; }
            for (let i = 0; i < steps.length; i++) {
                if (steps[i].querySelector(':invalid')) {
                    event.preventDefault(); show(i); validate(steps[i]); return;
                }
            }
        });
        // Without JavaScript both sections and the ordinary submit button remain usable.
        form.noValidate = true;
        progress.hidden = false;
        show(0);
    });
}

function initProfilePhoto() {
    document.querySelectorAll('[data-profile-photo]').forEach(field => {
        const input = field.querySelector('[data-profile-image]');
        const preview = field.querySelector('[data-profile-image-preview]');
        const clear = field.querySelector('[data-profile-image-clear]');
        const original = preview.firstElementChild.cloneNode(true);
        let objectUrl;
        const update = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            const file = input.files[0];
            preview.replaceChildren(original.cloneNode(true));
            clear.hidden = !file;
            if (!file || !/^image\/(jpeg|png|gif|webp|bmp)$/.test(file.type) || file.size > 10 * 1024 * 1024) return;
            const image = document.createElement('img');
            image.className = 'gc-settings-photo-preview';
            image.alt = 'Новый аватар';
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            preview.replaceChildren(image);
        };
        input.addEventListener('change', update);
        clear.addEventListener('click', () => { input.value = ''; input.dispatchEvent(new Event('change', {bubbles:true})); });
        window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    });
}

export default function initAccountForms() {
    initPasswords();
    initRegistration();
    initProfilePhoto();
}
