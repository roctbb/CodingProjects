<?php

namespace App\Queries;

use App\CompletedCourse;
use App\User;
use Illuminate\Support\Collection;

final class StudentCompletedCourses
{
    public function forStudent(User $student): Collection
    {
        $records = $student->relationLoaded('completedCourses') ? $student->completedCourses->sortByDesc('id')->values()
            : $student->completedCourses()->with('course')->orderByDesc('id')->get();
        $enrolled = $student->relationLoaded('courses') ? $student->courses : $student->courses()->get();
        $linkedIds = $enrolled->pluck('id');
        $records->each(fn ($record) => $record->setAttribute('is_linked', $linkedIds->contains($record->course_id)));

        // Late invitations and older missing snapshots still belong in the archive.
        // Membership alone does not imply an earned grade or a completed programme.
        $missing = $enrolled->where('state', 'ended')->whereNotIn('id', $records->pluck('course_id'))
            ->sortByDesc('end_date')->map(function ($course) use ($student) {
                return (new CompletedCourse())->forceFill([
                    'course_id' => $course->id, 'user_id' => $student->id,
                    'name' => $course->name, 'mark' => null, 'is_linked' => true,
                ])->setRelation('course', $course);
            });

        return $records->concat($missing)->values();
    }
}
