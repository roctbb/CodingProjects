import test from 'node:test';
import assert from 'node:assert/strict';
import { createPlannerRequest } from '../../resources/assets/js/course-planner/request.js';

function mockBrowser(t, fetch) {
    const original = { fetch: globalThis.fetch, document: globalThis.document };
    globalThis.fetch = fetch;
    globalThis.document = { querySelector: () => ({ content: 'test-csrf' }) };
    t.after(() => {
        globalThis.fetch = original.fetch;
        if (original.document === undefined) delete globalThis.document;
        else globalThis.document = original.document;
    });
}

test('requests keep session credentials, CSRF and cancellation signal', async t => {
    const abort = new AbortController();
    mockBrowser(t, async (url, options) => {
        assert.equal(url, '/planner/api/state');
        assert.equal(options.credentials, 'same-origin');
        assert.equal(options.headers['X-CSRF-TOKEN'], 'test-csrf');
        assert.equal(options.headers.Accept, 'application/json');
        assert.equal(options.signal, abort.signal);
        return new Response('{"revision":3}');
    });
    assert.deepEqual(await createPlannerRequest('/planner/api')('/state', { signal: abort.signal }), { revision: 3 });
});

test('conflicts retain status and code for workspace recovery', async t => {
    mockBrowser(t, async () => new Response(JSON.stringify({ code: 'revision_conflict', message: 'Обновите план' }), { status: 409 }));
    await assert.rejects(createPlannerRequest('/api')('/commands/attach'), error => error.status === 409 && error.code === 'revision_conflict' && error.message === 'Обновите план');
});

test('field validation errors remain visible and unreadable errors have a fallback', async t => {
    mockBrowser(t, async () => new Response(JSON.stringify({ errors: { starts_on: ['Укажите начало'], ends_on: ['Укажите окончание'] } }), { status: 422 }));
    await assert.rejects(createPlannerRequest('/api')('/calendar/preview'), /Укажите начало Укажите окончание/);
    globalThis.fetch = async () => new Response('<html>Server unavailable</html>', { status: 503 });
    await assert.rejects(createPlannerRequest('/api')('/state'), error => error.status === 503 && error.message === 'Не удалось прочитать ответ сервера.');
});
