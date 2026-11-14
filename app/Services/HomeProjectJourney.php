<?php

namespace App\Services;

use App\Lesson;
use App\LessonStudentStats;
use App\ProgramChapter;
use App\Support\ProjectPath;
use App\User;
use Illuminate\Support\Collection;

final class HomeProjectJourney
{
    public function forStudent(Collection $courses, User $viewer): ?array
    {
        if ($viewer->role !== 'student' || $courses->isEmpty()) return null;
        $courses = new \Illuminate\Database\Eloquent\Collection($courses->all());
        $courses->loadMissing('students:id');
        $courses = $courses->filter(fn ($course) => $course->students->contains('id', $viewer->id));
        if ($courses->isEmpty()) return null;

        // Only navigation metadata is needed here, never theory, task bodies or solutions.
        $chapters = ProgramChapter::whereIn('program_id', $courses->pluck('program_id'))->orderBy('sort_index')->orderBy('id')->get(['id', 'name', 'program_id'])->keyBy('id');
        $lessons = Lesson::whereIn('program_id', $courses->pluck('program_id'))->with([
            'info' => fn ($q) => $q->whereIn('course_id', $courses->pluck('id')),
            'earlyAccesses' => fn ($q) => $q->whereIn('course_id', $courses->pluck('id'))->where('user_id', $viewer->id),
            'steps' => fn ($q) => $q->without('tasks')->select('id', 'name', 'lesson_id', 'sort_index'),
        ])->orderBy('sort_index')->orderBy('id')->get(['id', 'name', 'program_id', 'chapter_id', 'sort_index']);
        $stats = LessonStudentStats::whereIn('course_id', $courses->pluck('id'))->where('student_id', $viewer->id)->get()->groupBy('course_id');

        foreach ($courses->sortByDesc(fn ($course) => (bool) $course->current_lesson_id) as $course) {
            $programLessons = $lessons->where('program_id', $course->program_id)->whereIn('chapter_id', $chapters->keys())->filter(fn ($lesson) => $lesson->steps->isNotEmpty());
            $available = $programLessons->filter(fn ($lesson) => $lesson->isAvailableForUser($course, $viewer));
            $results = $stats->get($course->id, collect())->keyBy('lesson_id');
            $current = $available->firstWhere('id', $course->current_lesson_id)
                ?? $available->first(fn ($lesson) => ($results->get($lesson->id)->max_points ?? 0) === 0 || ($results->get($lesson->id)->percent ?? 0) <= 90);
            if (!$current) continue;
            $chapter = $chapters->get($current->chapter_id);
            $path = ProjectPath::forChapters($chapters->where('program_id', $course->program_id), $programLessons, $course, $viewer, $results, $chapter->id);
            return compact('course', 'chapter', 'current', 'path') + [
                'url' => url('/insider/courses/'.$course->id.'/steps/'.$current->steps->first()->id),
            ];
        }
        return null;
    }
}
