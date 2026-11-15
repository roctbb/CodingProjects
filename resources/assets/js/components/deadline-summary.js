export function deadlineState(stages, legacy, now) {
    let percent = 100;
    let next = null;
    for (const stage of stages) {
        if (legacy ? now > stage.cutoff : now >= stage.cutoff) percent = stage.percent;
        else { next = stage; break; }
    }
    return {penalty: Math.round((100 - percent) * 100) / 100, next};
}

export default function initDeadlineSummaries() {
    const start = performance.now();
    const summaries = [...document.querySelectorAll('[data-deadline-summary]')].map(root => ({
        root, serverNow: Number(root.dataset.serverNow), legacy: root.dataset.legacyBoundary === 'true',
        stages: [...root.querySelectorAll('[data-deadline-stage]')].map(row => ({
            cutoff: Number(row.dataset.cutoff), percent: Number(row.dataset.percent), date: row.dataset.date, datetime: row.dataset.datetime,
        })),
    }));
    if (!summaries.length) return;
    let timer;
    const update = () => {
        clearTimeout(timer);
        let delay = Infinity;
        for (const {root, serverNow, legacy, stages} of summaries) {
            const now = serverNow + performance.now() - start;
            const {penalty, next} = deadlineState(stages, legacy, now);
            root.querySelector('[data-deadline-current]').textContent = penalty ? `Сейчас −${penalty}%` : 'Без штрафа';
            root.querySelector('[data-deadline-next]').hidden = !next;
            if (next) {
                const date = root.querySelector('[data-deadline-next-date]');
                date.textContent = next.date;
                date.dateTime = next.datetime;
                root.querySelector('[data-deadline-next-penalty]').textContent = `−${Math.round((100-next.percent)*100)/100}%`;
                delay = Math.min(delay, Math.max(1, next.cutoff - now + (legacy ? 1 : 0)));
            }
        }
        // One timer for the page, capped at the browser's maximum timeout.
        if (Number.isFinite(delay)) timer = setTimeout(update, Math.min(delay, 2147483647));
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
    window.addEventListener('pageshow', update);
    update();
}
