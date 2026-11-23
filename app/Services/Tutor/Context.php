<?php

namespace App\Services\Tutor;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final class Context
{
    public static function enabledForCourse(int $course): bool
    {
        $courses = config('tutor.courses', []);
        return config('tutor.enabled') && (!$courses || in_array((string)$course, $courses, true));
    }

    // Match the HTTP receiver. Reject oversize input before calling a paid provider.
    public const MAX_BYTES = 4 * 1024 * 1024;
    public const TOO_LARGE = 'Материалы урока и решения превышают размер одного запроса помощника.';

    public function materials(Course $course, ProgramStep $step, User $user, string $question, string $task, ?string $focus, bool $compact = false): array
    {
        $lessons = Lesson::query()->leftJoin('program_chapters as c', 'c.id', '=', 'lessons.chapter_id')
            ->where('lessons.program_id', $course->program_id)
            ->orderBy('c.sort_index')->orderBy('lessons.chapter_id')->orderBy('lessons.sort_index')->orderBy('lessons.id')
            ->get(['lessons.*'])->values();
        $position = $lessons->search(fn ($lesson) => $lesson->id == $step->lesson_id);
        abort_if($position === false, 404);
        $groups = [];
        foreach (['current' => $position, 'previous' => $position - 1, 'next' => $position + 1] as $side => $index) {
            if ($compact && $side !== 'current') continue;
            $item = $lessons->get($index);
            if (!$item || ($side !== 'current' && !$item->isAvailableForUser($course, $user))) continue;
            $groups[$side] = ['title' => $item->name, 'steps' => $item->steps()->without('tasks')->where('program_id', $course->program_id)
                ->select(['id','name','theory','is_notebook','video_url'])->cursor()];
        }
        return self::assemble($groups, $step->id, $question, $task, $focus, $compact);
    }

    public static function assemble(array $groups, int $stepId, string $question, string $task, ?string $focus = null, bool $compact = false): array
    {
        $lesson = ['title' => $groups['current']['title'], 'steps' => []];
        $neighbors = $sources = $blocks = [];
        $bytes = 0;
        foreach ($groups as $side => $group) {
            foreach ($group['steps'] as $source) {
                if ($compact && $source->id != $stepId) continue;
                $ref = 'S'.(count($sources) + 1);
                $revision = Reading::revision($source);
                $key = 'tutor:reading:v1:'.$source->id.':'.$revision.':'.md5($source->name.':'.$source->is_notebook);
                $parts = Cache::remember($key, 3600, fn () => Reading::fragments($source));
                $section = ['id' => $ref, 'title' => $source->name];
                // The whole step is a valid destination even when it contains no prose blocks.
                $entry = ['id' => 's'.$source->id.'-'.$revision, 'step' => $source->id,
                    'anchor' => 'theory', 'title' => $source->name, 'heading' => $source->name, 'text' => ''];
                if ($side === 'current') {
                    $section['text'] = $compact ? '' : Reading::fullText($source);
                    $section['current'] = $source->id == $stepId;
                    $lesson['steps'][] = $section;
                    array_push($blocks, ...$parts);
                } else {
                    [$section['text'], $excerpts] = Reading::summary($source, $parts);
                    $neighbors[] = $section + ['lesson' => $group['title'], 'side' => $side];
                    array_push($blocks, ...$excerpts);
                }
                $bytes += strlen($section['text']);
                abort_if($bytes > self::MAX_BYTES, 413, self::TOO_LARGE);
                $sources[$ref] = $entry;
            }
        }
        $readings = [];
        $selected = Reading::select($blocks, $question, $task, $stepId, max(300, min(8000, config('tutor.context_chars'))), $focus);
        foreach ($selected as $i => $part) {
            $ref = 'R'.($i + 1);
            $sources[$ref] = $part;
            $readings[] = ['id' => $ref] + Arr::only($part, ['title', 'heading', 'text']);
        }
        if ($compact && $lesson['steps']) $lesson['steps'][0]['text'] = implode("\n", array_column($readings, 'text'));
        return ['lesson' => $lesson, 'neighbors' => $neighbors, 'readings' => $readings, 'sources' => $sources];
    }
}

