<?php

namespace App\Queries;

use App\Lesson;
use App\Sticker;
use App\LessonStudentStats;
use App\Program;
use App\User;
use Illuminate\Support\Collection;

class StickerAlbum
{
    public function forProfile(User $student, bool $owner): Collection
    {
        $earnedLessons = Lesson::whereIn('id', LessonStudentStats::where('student_id', $student->id)
            ->where('percent', '>', 90)->select('lesson_id'))->whereHas('steps.tasks')->whereNotNull('sticker')
            ->orderBy('sort_index')->orderBy('id')->get(['id', 'name', 'program_id', 'sticker']);
        $slots = [];
        foreach ($earnedLessons as $lesson) {
            $slots[$lesson->program_id][$lesson->sticker] = ['state'=>'earned', 'lesson'=>$lesson->name];
        }
        if ($owner) {
            $courses = $student->courses->where('state', 'started');
            $lessons = Lesson::whereIn('program_id', $courses->pluck('program_id'))->whereNotNull('sticker')
                ->whereHas('steps.tasks')->with(['info', 'earlyAccesses' => fn ($query) => $query->where('user_id', $student->id)])
                ->withMin('steps', 'id')->orderBy('sort_index')->get()->groupBy('program_id');
            foreach ($courses as $course) {
                foreach ($lessons->get($course->program_id, collect()) as $lesson) {
                    $image = $lesson->sticker;
                    if (in_array($slots[$lesson->program_id][$image]['state'] ?? null, ['available','earned'], true)) continue;
                    $slots[$lesson->program_id][$image] ??= ['state' => 'locked'];
                    if ($lesson->isStarted($course, $student) || $lesson->hasEarlyAccess($course, $student)) {
                        $slots[$lesson->program_id][$image] = ['state' => 'available', 'lesson' => $lesson->name,
                            'href' => url("/insider/courses/{$course->id}/steps/{$lesson->steps_min_id}")];
                    }
                }
            }
        }
        $images = collect($slots)->flatMap(fn ($items) => array_keys($items))->unique();
        $catalog = Sticker::whereIn('image', $images)->get()->keyBy('image');
        $programs = Program::whereIn('id', array_keys($slots))->pluck('name', 'id');
        $titles = config('sticker_albums', []);
        $albums = collect();
        foreach ($slots as $program => $items) {
            $name = $programs->get($program);
            $title = $titles[$name] ?? $name;
            foreach ($items as $image => $slot) {
                if (!$sticker = $catalog->get($image)) continue;
                $album = $albums->getOrPut($title, fn () => collect());
                $album->put($image, ['image'=>$image, 'name'=>$sticker->name, 'collection'=>$title] + $slot);
            }
        }
        return $albums->map(fn ($items) => $items->values())
            ->sortByDesc(fn ($items) => $items->where('state', 'earned')->count());
    }
}
