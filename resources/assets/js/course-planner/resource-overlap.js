export function nativeLessonOverlap(first, second) {
    if (first.provider_key !== 'codingprojects' || second.provider_key !== 'codingprojects') return false;
    const firstType = first.type || first.resource_type;
    const secondType = second.type || second.resource_type;
    if (!((firstType === 'lesson' && secondType === 'step') || (firstType === 'step' && secondType === 'lesson'))) return false;
    const lesson = firstType === 'lesson' ? first : second;
    const step = firstType === 'step' ? first : second;
    return (step.public_metadata?.lesson_id && String(step.public_metadata.lesson_id) === String(lesson.external_id)) ||
        (lesson.children_refs || []).some(child => String(child.external_id) === String(step.external_id));
}
