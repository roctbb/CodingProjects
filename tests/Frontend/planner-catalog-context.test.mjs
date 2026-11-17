import test from 'node:test';
import assert from 'node:assert/strict';
import { initialCatalogChapter } from '../../resources/assets/js/course-planner/catalog-context.js';

function fixture() {
    return { data: { course: { catalog_chapter_id: 2 }, chapters: [{ id: 1 }, { id: 2 }],
        lessons: [{ id: 10, chapter_id: 1 }, { id: 20, chapter_id: 2 }, { id: 30, chapter_id: null }],
        sessions: [{ id: 5, entries: [{ resource: { provider_key: 'codingprojects', type: 'step', external_id: '100', public_metadata: { lesson_id: 10 } } }] }] },
        contextChapter: '2', sessionId: null };
}

test('new sessions inherit the course context and direct visits use the current course chapter', () => {
    const input = fixture();
    input.data.course.catalog_chapter_id = 1;
    assert.equal(initialCatalogChapter(input), '2');
    input.contextChapter = null;
    assert.equal(initialCatalogChapter(input), '1');
    input.contextChapter = '999';
    input.data.course.catalog_chapter_id = 999;
    assert.equal(initialCatalogChapter(input), '1');
});

test('existing sessions open their materials chapter, including external links and lessons without chapters', () => {
    const input = fixture(); input.sessionId = 5;
    assert.equal(initialCatalogChapter(input), '1');
    input.data.sessions[0].entries = [{ resource: { provider_key: 'external', public_metadata: { lesson_id: 10 } }, lesson_links: [{ lesson_id: 20 }] }];
    assert.equal(initialCatalogChapter(input), '2');
    input.data.sessions[0].entries = [{ resource: { provider_key: 'codingprojects', type: 'lesson', external_id: '30' } }];
    assert.equal(initialCatalogChapter(input), '0');
});

test('mixed chapters fall back to the current context and text entries do not choose a chapter', () => {
    const input = fixture(); input.sessionId = 5;
    input.data.sessions[0].entries.push({ lesson_links: [{ lesson_id: 20 }] }, { kind: 'text' });
    assert.equal(initialCatalogChapter(input), '2');
    input.data.sessions[0].entries = [{ kind: 'text' }];
    assert.equal(initialCatalogChapter(input), '2');
});

test('manual choices, including all chapters, survive reopening and changing the target session', () => {
    const input = fixture(); input.sessionId = 5;
    assert.equal(initialCatalogChapter({ ...input, rememberedChapter: '2' }), '2');
    assert.equal(initialCatalogChapter({ ...input, rememberedChapter: '' }), '');
    assert.equal(initialCatalogChapter({ ...input, rememberedChapter: '0' }), '0');
    assert.equal(initialCatalogChapter({ ...input, rememberedChapter: '999' }), '1');
});
