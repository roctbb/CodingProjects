import test from 'node:test';
import assert from 'node:assert/strict';
import { createProviderSettings } from '../../resources/assets/js/course-planner/provider-settings.js';
import { createEntryActions } from '../../resources/assets/js/course-planner/entry-actions.js';
import { createWorkspaceNavigation } from '../../resources/assets/js/course-planner/workspace-navigation.js';

test('provider settings reconcile the fresh server state after saving', async () => {
    let state = { data: { providers: [{ key: 'example', name: '<Example>', enabled: true, configured: true }] } };
    let submit, reconciled;
    const updated = [{ key: 'example', enabled: false, configured: true }];
    const open = createProviderSettings({
        getState: () => state,
        root: { querySelectorAll: () => [{ dataset: { providerSettings: 'example' }, querySelector: () => ({ checked: false }) }] },
        dialog: { modal: (title, html, callback) => { assert.match(html, /&lt;Example&gt;/); submit = callback; } },
        mutate: async (operation, payload) => {
            assert.equal(operation, 'providers');
            assert.deepEqual(payload.providers, [{ key: 'example', enabled: false, show_in_summary: false }]);
            state = { data: { providers: updated } };
        },
        catalog: { reconcileProviders: async providers => { reconciled = providers; } },
    });
    open(); await submit();
    assert.equal(reconciled, updated);
});

test('material reordering uses the latest entries after a state replacement', async () => {
    let state = { data: { sessions: [] } };
    const mutations = [];
    const actions = createEntryActions({ getState: () => state, mutate: async (...args) => mutations.push(args), dialog: {}, entryDialogs: {} });
    const entry = id => ({ id, kind: 'text' });
    state = { data: { sessions: [{ id: 7, entries: [entry(1), entry(2), entry(3), entry(4)] }] } };
    await actions.handle({ dataset: { action: 'entry-down', entry: 'text:2' } });
    assert.deepEqual(mutations[0].slice(0, 2), ['move_entry', { kind: 'text', entry_id: 2, target_session_id: 7, before: { kind: 'text', id: 4 } }]);
    await actions.handle({ dataset: { action: 'entry-down', entry: 'text:4' } });
    assert.equal(mutations.length, 1);
});

function navigationFixture() {
    const nodes = new Map();
    const root = { dataset: { today: '2026-11-11' }, querySelectorAll: () => [], querySelector: selector => {
        if (!nodes.has(selector)) nodes.set(selector, { value: '', classList: { contains: () => false }, close() {} });
        return nodes.get(selector);
    } };
    let state = { from: '2026-11-09', to: '2026-11-15', data: { sessions: [], slots: [], summary: { rows: [] } } };
    const calls = [];
    const options = { root, getState: () => state, setRange: (from, to) => { state = { ...state, from, to }; },
        dialog: {}, catalog: { close() {} }, status() {}, loadState: async () => calls.push([state.from, state.to]) };
    return { root, options, calls, state: () => state };
}

test('period navigation keeps a complete week and failed custom ranges restore the previous period', async () => {
    const fixture = navigationFixture();
    let fail = false;
    const navigation = createWorkspaceNavigation({ ...fixture.options, loadState: async () => {
        if (fail) throw new Error('offline');
    } });
    await navigation.handle({ dataset: { action: 'next-range' } });
    assert.equal(fixture.state().from, '2026-11-16');
    assert.equal(fixture.state().to, '2026-11-22');
    fixture.root.querySelector('#planner-from').value = '2026-12-01';
    fixture.root.querySelector('#planner-to').value = '2026-12-31';
    fail = true;
    await assert.rejects(navigation.handle({ dataset: { action: 'apply-range' } }), /offline/);
    assert.equal(fixture.state().from, '2026-11-16');
    assert.equal(fixture.root.querySelector('#planner-range-picker').open, true);
    fail = false;
    await navigation.handle({ dataset: { action: 'today' } });
    assert.equal(fixture.state().from, '2026-11-09');
    assert.equal(fixture.state().to, '2026-11-15');
});

test('summary drilldown escapes material and session names and keeps provider pagination', async () => {
    const fixture = navigationFixture();
    fixture.state().data.summary.rows.push({ lesson_id: 3, name: 'Lesson', providers: { external: { materials: 2, uses: 3 } }, study_sessions: [] });
    let html;
    const navigation = createWorkspaceNavigation({ ...fixture.options,
        request: async path => { assert.match(path, /lesson_id=3&provider=external&page=2/); return { items: [{ title: '<img src=x>', session_title: '<script>', session_id: 7, date: null }], has_more: true }; },
        dialog: { modal: (title, markup) => { html = markup; } },
    });
    await navigation.handle({ dataset: { action: 'summary-detail', lesson: '3', provider: 'external', page: '2' } });
    assert.match(html, /&lt;img src=x&gt;/); assert.match(html, /&lt;script&gt;/);
    assert.doesNotMatch(html, /<img|<script/);
    assert.match(html, /data-page="1"/); assert.match(html, /data-page="3"/);
});
