export function initialCatalogChapter({ data, sessionId, contextChapter, rememberedChapter }) {
  const chapters = new Set((data.chapters || []).map(chapter => String(chapter.id)));
  if (data.lessons.some(lesson => !lesson.chapter_id)) chapters.add('0');
  const valid = value => value != null && chapters.has(String(value));
  if (rememberedChapter === '' || valid(rememberedChapter)) return String(rememberedChapter);

  const session = data.sessions.find(item => item.id === sessionId);
  const sessionChapters = new Set();
  for (const entry of session?.entries || []) {
    const resource = entry.resource;
    const lessonIds = (entry.lesson_links || []).map(link => link.lesson_id);
    if (resource?.provider_key === 'codingprojects') {
      lessonIds.push(resource.type === 'lesson' ? resource.external_id : resource.public_metadata?.lesson_id);
    }
    for (const id of lessonIds) {
      const lesson = data.lessons.find(item => String(item.id) === String(id));
      if (lesson && valid(lesson.chapter_id || 0)) sessionChapters.add(String(lesson.chapter_id || 0));
    }
  }
  if (sessionChapters.size === 1) return [...sessionChapters][0];
  if (valid(contextChapter)) return String(contextChapter);
  if (valid(data.course.catalog_chapter_id)) return String(data.course.catalog_chapter_id);
  return chapters.values().next().value ?? '';
}
