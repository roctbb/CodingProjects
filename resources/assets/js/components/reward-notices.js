export default function initRewardNotices() {
    const host = document.querySelector('[data-reward-notices]');
    if (!host) return;
    let timer, dismissTimer, busy = false, current = null, suspended = false;

    const schedule = (delay = 20000) => {
        clearTimeout(timer);
        if (!suspended) timer = setTimeout(poll, delay);
    };
    const close = () => {
        clearTimeout(dismissTimer);
        host.replaceChildren();
        current = null;
        schedule(500);
    };
    const node = (tag, className, text) => {
        const element = document.createElement(tag);
        element.className = className;
        if (text) element.textContent = text;
        return element;
    };
    const show = (notice) => {
        current = notice;
        const payload = notice.payload || {};
        const card = node('section', 'gc-reward-notice gc-reward-notice--' + notice.kind);
        const art = node('span', 'gc-reward-notice__art');
        art.setAttribute('aria-hidden', 'true');
        if (notice.kind === 'sticker') {
            try {
                const imageUrl = new URL(payload.image, location.origin);
                if (['http:', 'https:'].includes(imageUrl.protocol)) {
                    const image = node('img', 'gc-reward-notice__sticker');
                    image.src = imageUrl.href;
                    image.alt = '';
                    image.addEventListener('error', () => { art.textContent = '★'; }, {once: true});
                    art.append(image);
                }
            } catch (_) { art.textContent = '★'; }
        } else {
            const icon = node('i', 'fas fa-coins');
            art.append(icon);
        }
        const body = node('div', 'gc-reward-notice__body');
        body.append(node('strong', 'gc-reward-notice__title', notice.kind === 'sticker' ? 'Вы получили наклейку!' : '+' + payload.amount + ' GC'));
        body.append(node('span', 'gc-reward-notice__reason', notice.kind === 'sticker' ? payload.lesson : payload.reason));
        if (notice.kind === 'sticker') {
            const link = node('a', 'gc-reward-notice__link', 'Моя коллекция');
            link.href = host.dataset.profileUrl + '#profile-stickers';
            body.append(link);
        }
        const dismiss = node('button', 'gc-reward-notice__close');
        dismiss.type = 'button';
        dismiss.setAttribute('aria-label', 'Закрыть уведомление о награде');
        const icon = node('i', 'fas fa-xmark');
        icon.setAttribute('aria-hidden', 'true');
        dismiss.append(icon);
        dismiss.addEventListener('click', close);
        card.append(art, body, dismiss);
        host.replaceChildren(card);
        let hovered = false, focused = false;
        const startDismiss = () => {
            clearTimeout(dismissTimer);
            if (!hovered && !focused) dismissTimer = setTimeout(close, 8000);
        };
        card.addEventListener('mouseenter', () => { hovered = true; clearTimeout(dismissTimer); });
        card.addEventListener('mouseleave', () => { hovered = false; startDismiss(); });
        card.addEventListener('focusin', () => { focused = true; clearTimeout(dismissTimer); });
        card.addEventListener('focusout', event => { focused = card.contains(event.relatedTarget); startDismiss(); });
        startDismiss();
    };

    async function poll() {
        if (busy || current || suspended || document.hidden || document.querySelector('dialog[open], .modal.show')) {
            schedule();
            return;
        }
        busy = true;
        try {
            const {data} = await window.axios.post(host.dataset.endpoint, {}, {timeout: 8000});
            if (data.notice && !suspended) show(data.notice);
            else schedule();
        } catch (error) {
            // A failed request never triggers congratulations or retries a reward grant.
            if ([401, 403, 419].includes(error.response?.status)) suspended = true;
            schedule(45000);
        } finally {
            busy = false;
        }
    }
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) schedule(500);
    });
    document.addEventListener('gc:task-checked', () => schedule(300));
    window.addEventListener('focus', () => schedule(500));
    window.addEventListener('pagehide', () => {
        suspended = true;
        clearTimeout(timer);
        clearTimeout(dismissTimer);
        host.replaceChildren();
        current = null;
    });
    window.addEventListener('pageshow', event => { if (event.persisted) { suspended = false; schedule(500); } });
    schedule(1200);
}
