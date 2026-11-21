<?php

namespace App\Queries;

use App\Lesson;
use App\Sticker;
use App\User;
use Illuminate\Support\Collection;

class StickerAlbum
{
    public function forProfile(User $student, bool $owner): Collection
    {
        $earned = $student->getStickers()->flip();
        $slots = [];
        if ($owner) {
            $courses = $student->courses->where('state', 'started');
            $lessons = Lesson::whereIn('program_id', $courses->pluck('program_id'))->whereNotNull('sticker')
                ->whereHas('steps.tasks')->with(['info', 'earlyAccesses' => fn ($query) => $query->where('user_id', $student->id)])
                ->withMin('steps', 'id')->orderBy('sort_index')->get()->groupBy('program_id');
            foreach ($courses as $course) {
                foreach ($lessons->get($course->program_id, collect()) as $lesson) {
                    $image = $lesson->sticker;
                    if ($earned->has($image) || isset($slots[$image]['href'])) continue;
                    $slots[$image] ??= ['state' => 'locked'];
                    if ($lesson->isStarted($course, $student) || $lesson->hasEarlyAccess($course, $student)) {
                        $slots[$image] = ['state' => 'available', 'lesson' => $lesson->name,
                            'href' => url("/insider/courses/{$course->id}/steps/{$lesson->steps_min_id}")];
                    }
                }
            }
        }
        $sources = $student->getStickerDescriptions();
        foreach ($earned as $image => $index) {
            $slots[$image] = ['state' => 'earned', 'lesson' => $sources[$image] ?? ''];
        }
        return Sticker::whereIn('image', array_keys($slots))->orderBy('collection')->orderBy('id')->get()
            ->map(fn ($item) => ['name'=>$item->name, 'image'=>$item->image, 'collection'=>$item->collection] + $slots[$item->image])
            ->groupBy('collection')->sortByDesc(fn ($items) => $items->where('state', 'earned')->count());
    }
}
