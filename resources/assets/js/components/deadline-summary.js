export function deadlineState(stages, legacy, now) {
    let percent = 100;
    let next = null;
    for (const stage of stages) {
        if (legacy ? now > stage.cutoff : now >= stage.cutoff) percent = stage.percent;
        else { next = stage; break; }
    }
    return {penalty: Math.round((100 - percent) * 100) / 100, next};
}

const dayFormatters = new Map();
export function lessonDeadlineState(rules, now) {
    let today = false;
    for (const rule of rules) {
        const passed = rule.stages.filter(stage => rule.legacy ? now > stage.cutoff : now >= stage.cutoff);
        if ((passed.at(-1)?.percent ?? 100) <= 0) continue;
        if (passed.length) return 'overdue';
        if (!dayFormatters.has(rule.timezone)) dayFormatters.set(rule.timezone, new Intl.DateTimeFormat('en', {
            timeZone: rule.timezone, year: 'numeric', month: '2-digit', day: '2-digit',
        }));
        const parts = Object.fromEntries(dayFormatters.get(rule.timezone).formatToParts(now).map(part => [part.type, part.value]));
        const localDay = `${parts.year}-${parts.month}-${parts.day}`;
        today ||= rule.stages.some(stage => stage.day === localDay);
    }
    return today ? 'today' : null;
}

function initAnchoredPopovers() {
    let active;
    const position = () => {
        if (!active?.panel.matches(':popover-open')) return;
        const {trigger, panel} = active;
        const padding = 16, gap = 8;
        const bottomNav = document.querySelector('.gc-bottom-nav')?.getBoundingClientRect();
        const bottom = bottomNav?.height ? bottomNav.top - padding : window.innerHeight - padding;
        const anchor = trigger.getBoundingClientRect();
        const below = bottom - anchor.bottom - gap;
        const above = anchor.top - padding - gap;
        const useBelow = below >= Math.min(panel.scrollHeight, 240) || below >= above;
        panel.style.maxHeight = `${Math.max(80, useBelow ? below : above)}px`;
        const bounds = panel.getBoundingClientRect();
        panel.style.left = `${Math.max(padding, Math.min(anchor.left, window.innerWidth - bounds.width - padding))}px`;
        panel.style.top = `${Math.max(padding, useBelow ? anchor.bottom + gap : anchor.top - bounds.height - gap)}px`;
    };
    document.querySelectorAll('[data-deadline-popover], [data-lesson-progress-popover]').forEach(root => {
        const trigger = root.querySelector('.gc-deadline-popover__trigger, [data-lesson-progress-trigger]');
        const panel = root.querySelector('[popover]');
        panel.addEventListener('beforetoggle', event => {
            trigger.setAttribute('aria-expanded', String(event.newState === 'open'));
            if (event.newState === 'open') {
                active = {trigger, panel};
                requestAnimationFrame(position);
            }
        });
        panel.addEventListener('toggle', event => {
            trigger.setAttribute('aria-expanded', String(event.newState === 'open'));
            if (event.newState === 'open') position();
        });
    });
    window.addEventListener('resize', position);
    document.addEventListener('scroll', position, true);
}

export default function initDeadlineSummaries() {
    initAnchoredPopovers();
    const start = performance.now();
    const summaries = [...document.querySelectorAll('[data-deadline-summary]')].map(root => ({
        root, serverNow: Number(root.dataset.serverNow), legacy: root.dataset.legacyBoundary === 'true',
        stages: [...root.querySelectorAll('[data-deadline-stage]')].map(row => ({
            cutoff: Number(row.dataset.cutoff), percent: Number(row.dataset.percent), date: row.dataset.date, datetime: row.dataset.datetime,
        })),
    }));
    const alerts = [...document.querySelectorAll('[data-lesson-deadline-rules]')].map(root => ({
        root, rules: JSON.parse(root.dataset.lessonDeadlineRules), serverNow: Number(root.dataset.deadlineServerNow),
    })).filter(alert => alert.rules.length);
    if (!summaries.length && !alerts.length) return;
    let timer;
    const update = () => {
        clearTimeout(timer);
        let delay = Infinity;
        for (const {root, serverNow, legacy, stages} of summaries) {
            const now = serverNow + performance.now() - start;
            const {penalty, next} = deadlineState(stages, legacy, now);
            root.querySelector('[data-deadline-current]').textContent = penalty ? `Сейчас −${penalty}%` : 'Без штрафа';
            const calendar = root.querySelector('[data-deadline-calendar]');
            if (!calendar) root.querySelector('[data-deadline-next]').hidden = !next;
            const displayed = next ?? (calendar ? stages.at(-1) : null);
            if (displayed) {
                const date = root.querySelector('[data-deadline-next-date]');
                date.textContent = displayed.date;
                date.dateTime = displayed.datetime;
            }
            if (next) {
                const nextPenaltyAmount = Math.round((100-next.percent)*100)/100;
                const nextPenalty = root.querySelector('[data-deadline-next-penalty]');
                if (nextPenalty) nextPenalty.textContent = nextPenaltyAmount ? `−${nextPenaltyAmount}%` : 'без штрафа';
                const prefix = root.querySelector('[data-deadline-next-prefix]');
                if (prefix) prefix.textContent = nextPenaltyAmount === penalty ? 'Срок' : 'После';
                const effect = root.querySelector('[data-deadline-next-effect]');
                if (effect) effect.hidden = nextPenaltyAmount === penalty;
                delay = Math.min(delay, Math.max(1, next.cutoff - now + (legacy ? 1 : 0)));
            }
            const state = `${penalty}:${next?.cutoff ?? ''}`;
            if (root.dataset.deadlineState !== state) {
                root.dataset.deadlineState = state;
                root.dispatchEvent(new CustomEvent('gc:deadline-change', { bubbles: true, detail: { penalty, next } }));
            }
        }
        for (const {root, rules, serverNow} of alerts) {
            const status = lessonDeadlineState(rules, serverNow + performance.now() - start);
            root.dataset.lessonDeadlineStatus = status ?? '';
            const label = status === 'overdue' ? 'Есть несданные задачи с прошедшим сроком'
                : status === 'today' ? 'Срок сдачи несданных задач сегодня' : 'Сроки сдачи';
            root.setAttribute('aria-label', label);
            root.title = label;
        }
        // Also update the local calendar day while the page remains open.
        if (alerts.length) delay = Math.min(delay, 60000);
        // One timer for the page, capped at the browser's maximum timeout.
        if (Number.isFinite(delay)) timer = setTimeout(update, Math.min(delay, 2147483647));
    };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
    window.addEventListener('pageshow', update);
    update();
}
