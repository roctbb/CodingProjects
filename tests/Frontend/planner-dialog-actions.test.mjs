import test from 'node:test';
import assert from 'node:assert/strict';
import { createScheduleSettings } from '../../resources/assets/js/course-planner/schedule-settings.js';
import { createSessionActions } from '../../resources/assets/js/course-planner/session-actions.js';

function dialogFixture() {
    const modals = [];
    let values = {};
    const dialog = {
        field: () => '', select: () => '', values: () => values,
        modal: (title, html, submit) => modals.push({ title, html, submit }),
    };
    return { dialog, modals, fill: next => { values = next; } };
}

test('schedule preview leaves the calendar unchanged until explicit application', async () => {
    const { dialog, modals, fill } = dialogFixture();
    const mutations = [], ranges = [];
    const state = { data: { settings: { rules: [] } } };
    const rule = { dataset: { ruleKey: 'saved-rule', validFrom: '2026-11-01', validUntil: '' }, querySelector: selector => ({ value: { '[data-rule-weekday]': '4', '[data-rule-time]': '14:00', '[data-rule-duration]': '90' }[selector] }) };
    const root = { dataset: { today: '2026-11-11' }, querySelector: () => ({}), querySelectorAll: () => [rule] };
    let payload;
    const open = createScheduleSettings({ root, getState: () => state, setRange: (...range) => ranges.push(range), dialog,
        request: async (path, options) => { assert.equal(path, '/calendar/preview'); payload = JSON.parse(options.body); return { available: 3, excluded: 1, affected_sessions: [] }; },
        mutate: async (...args) => mutations.push(args),
    });
    open();
    fill({ starts_on: '2026-11-12', ends_on: '2026-12-01', timezone: 'Europe/Moscow', default_duration_minutes: '90' });
    assert.equal(await modals[0].submit(), false);
    assert.equal(mutations.length, 0);
    assert.equal(ranges.length, 0);
    assert.deepEqual(payload.rules[0], { rule_key: 'saved-rule', valid_from: '2026-11-01', valid_until: null, weekday: 4, start_time: '14:00', duration_minutes: 90 });
    await modals[1].submit();
    assert.equal(mutations[0][0], 'configure');
    assert.deepEqual(mutations[0][1], payload);
    assert.deepEqual(ranges[0], ['2026-11-12', '2026-11-18']);
});

test('session comments keep text intact and are unavailable before the session ends', async () => {
    const { dialog, modals, fill } = dialogFixture();
    const state = { data: { sessions: [{ id: 7, can_comment: false }], slots: [] } };
    const mutations = [];
    const actions = createSessionActions({ root: { querySelector: () => ({}) }, getState: () => state, dialog, mutate: async (...args) => mutations.push(args) });
    const button = { dataset: { action: 'record', session: '7' } };
    await assert.rejects(actions.handle(button), /после окончания/);
    state.data.sessions[0] = { id: 7, can_comment: true, comment: '<b>Разбор</b>', calendar_slot_id: 1 };
    state.data.slots = [{ id: 1, local_date: '2026-11-09' }];
    await actions.handle(button);
    assert.match(modals[0].html, /&lt;b&gt;Разбор&lt;\/b&gt;/);
    fill({ comment: 'Следующий шаг & практика' });
    await modals[0].submit();
    assert.deepEqual(mutations[0].slice(0, 2), ['comment', { session_id: 7, comment: 'Следующий шаг & практика' }]);
});

test('an additional session outside the visible period opens its new week after saving', async () => {
    const { dialog, modals, fill } = dialogFixture();
    const calls = [];
    const state = { from: '2026-11-09', to: '2026-11-15', selected: 3, data: { sessions: [], settings: {} } };
    const actions = createSessionActions({ root: { dataset: { today: '2026-11-11' }, querySelector: () => ({}) }, getState: () => state, dialog,
        mutate: async (operation, payload) => { calls.push([operation, payload]); return { session_id: 42 }; },
        setTarget: id => { state.selected = id; }, setRange: (from, to) => Object.assign(state, { from, to }),
        loadState: async () => calls.push(['reload', state.from]), renderCalendar: () => calls.push(['render']), revealSession: id => calls.push(['reveal', id]),
    });
    await actions.handle({ dataset: { action: 'add-slot' } });
    fill({ date: '2026-12-04', start_time: '', duration_minutes: '75', title: 'Практика' });
    await modals[0].submit();
    assert.equal(calls[0][0], 'add_slot');
    assert.equal(calls[0][1].duration_minutes, 75);
    assert.equal(calls[0][1].start_time, null);
    assert.equal(state.selected, 42);
    assert.deepEqual([state.from, state.to], ['2026-12-04', '2026-12-10']);
    assert.deepEqual(calls.slice(1), [['reload', '2026-12-04'], ['render'], ['reveal', 42]]);
});
