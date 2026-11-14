import test from 'node:test';
import assert from 'node:assert/strict';
import { createSessionView } from '../../resources/assets/js/course-planner/session-view.js';

function fixture() {
    const entry = { id: 10, kind: 'text', text: '<script>bad()</script>', lesson_links: [{ relation: 'study', lesson_id: 1 }] };
    const session = { id: 2, title: 'Занятие', entries: [entry], plan_note: '<b>note</b>', comment: '<img src=x>', can_comment: true };
    const data = { lessons: [{ id: 1, name: 'Первый урок' }], providers: [], settings: { default_duration_minutes: 90 }, summary: { rows: [] } };
    return { session, state: { data, selected: 2, layer: 'plan' } };
}

test('session rendering reads fresh workspace state after a refresh', () => {
    const sample = fixture();
    let state = sample.state;
    const view = createSessionView({ getState: () => state });
    assert.equal(view.sessionTitle(sample.session), 'Первый урок');
    assert.match(view.sessionMarkup(sample.session), /is-selected/);
    state = { ...state, selected: 3, data: { ...state.data, lessons: [{ id: 1, name: 'Обновлённый урок' }] } };
    assert.equal(view.sessionTitle(sample.session), 'Обновлённый урок');
    assert.doesNotMatch(view.sessionMarkup(sample.session), /is-selected/);
});

test('text, notes, comments and lesson titles are escaped as content', () => {
    const { session, state } = fixture();
    state.data.lessons[0].name = '<svg onload=bad()>';
    const html = createSessionView({ getState: () => state }).sessionMarkup(session);
    assert.doesNotMatch(html, /<script>|<svg|<img|<b>/);
    for (const text of ['&lt;script&gt;', '&lt;svg', '&lt;img', '&lt;b&gt;']) assert.ok(html.includes(text));
});

test('legacy fact controls only appear when the actual layer requests them', () => {
    const { session, state } = fixture();
    const view = createSessionView({ getState: () => state });
    assert.doesNotMatch(view.sessionMarkup(session), /data-entry-state/);
    state.layer = 'fact';
    assert.match(view.sessionMarkup(session), /data-entry-state="text:10"/);
});
