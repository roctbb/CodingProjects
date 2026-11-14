export default function initRewardNotices() {
    const host = document.querySelector('[data-reward-notices]');
    if (!host) return;
    let timer, dismissTimer, busy = false, current = null, suspended = false, animation;

    const schedule = (delay = 20000) => {
        clearTimeout(timer);
        if (!suspended) timer = setTimeout(poll, delay);
    };
    const close = () => {
        clearTimeout(dismissTimer);
        cancelAnimationFrame(animation);
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
        if (['sticker','achievement'].includes(notice.kind) && payload.image) {
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
            const icon = node('i', 'fas ' + ({xp:'fa-bolt',rank:'fa-medal',achievement:'fa-trophy',sticker:'fa-star',task_complete:'fa-circle-check'}[notice.kind] || 'fa-coins'));
            art.append(icon);
        }
        const body = node('div', 'gc-reward-notice__body');
        const titles = {sticker:'Вы получили наклейку!',xp:'+' + payload.amount + ' XP',rank:'Новое звание: ' + payload.title,achievement:'Достижение: ' + payload.title,task_complete:'Задача выполнена!'};
        body.append(node('strong', 'gc-reward-notice__title', titles[notice.kind] || '+' + payload.amount + ' GC'));
        body.append(node('span', 'gc-reward-notice__reason', notice.kind === 'sticker' ? payload.lesson : notice.kind === 'task_complete' ? payload.title : payload.reason));
        if (notice.kind === 'xp' && Number.isFinite(payload.before) && Number.isFinite(payload.after)) {
            const counter = node('span','gc-reward-notice__counter');
            counter.setAttribute('aria-hidden','true');
            body.append(counter);
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const began = performance.now();
            const advance = now => {
                const progress = reduced ? 1 : Math.min(1,(now-began)/900);
                const value = Math.round(payload.before + (payload.after-payload.before)*(1-Math.pow(1-progress,3)));
                counter.textContent = `${payload.before} → ${value} XP`;
                if (progress < 1 && current?.id === notice.id) animation = requestAnimationFrame(advance);
            };
            animation = requestAnimationFrame(advance);
        }
        if (['sticker','achievement'].includes(notice.kind)) {
            const link = node('a', 'gc-reward-notice__link', 'Моя коллекция');
            link.href = host.dataset.profileUrl + (notice.kind === 'sticker' ? '#profile-stickers' : '#achievement-' + Number(payload.achievement_id));
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
        if (['coins','rank','achievement','task_complete'].includes(notice.kind)) {
            for (let index = 0; index < 6; index++) {
                const spark = node('span','gc-reward-notice__spark');
                spark.setAttribute('aria-hidden','true');
                spark.style.setProperty('--spark-index',index);
                card.append(spark);
            }
        }
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
