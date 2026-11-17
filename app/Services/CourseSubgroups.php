<?php

namespace App\Services;

use App\Course;
use App\CourseSubgroup;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CourseSubgroups
{
    private ?bool $available = null;
    private array $current = [];
    private array $lessonDates = [];
    public function available(): bool { return $this->available ??= Schema::hasTable('course_subgroups'); }

    public function forget(): void { $this->current = $this->lessonDates = []; }

    public function lessonOverride(int $groupId, int $lessonId): ?object
    {
        $this->lessonDates[$groupId] ??= DB::table('course_subgroup_lessons')->where('subgroup_id', $groupId)->get()->keyBy('lesson_id');
        return $this->lessonDates[$groupId]->get($lessonId);
    }

    public function groupFor(int $courseId, int $userId, ?CarbonInterface $at = null): ?int
    {
        if (!$this->available()) return null;
        if (!$at) {
            $this->current[$courseId] ??= DB::table('course_subgroup_memberships')->where('course_id', $courseId)->whereNotNull('active_user_id')->pluck('subgroup_id', 'user_id');
            $id = $this->current[$courseId]->get($userId);
            return $id === null ? null : (int) $id;
        }
        $query = DB::table('course_subgroup_memberships')->where('course_id', $courseId)->where('user_id', $userId);
        if ($at) $query->where('started_at', '<=', $at)->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>', $at));
        else $query->whereNotNull('active_user_id');
        $id = $query->orderByDesc('id')->value('subgroup_id');
        return $id === null ? null : (int) $id;
    }

    public function assign(Course $course, array $studentIds, ?int $groupId, ?int $actorId): void
    {
        abort_unless($this->available(), 503);
        DB::transaction(function () use ($course, $studentIds, $groupId, $actorId) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($groupId) CourseSubgroup::where('course_id', $course->id)->findOrFail($groupId);
            $ids = collect($studentIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();
            if ($course->students()->whereIn('users.id', $ids)->count() !== $ids->count()) {
                throw ValidationException::withMessages(['students' => 'Выберите учеников этого курса.']);
            }
            $changed = [];
            foreach ($ids as $id) {
                if ($this->groupFor($course->id, $id) === $groupId) continue;
                $changed[] = $id;
                $now = now();
                DB::table('course_subgroup_memberships')->where('course_id', $course->id)->where('active_user_id', $id)
                    ->update(['ended_at' => $now, 'active_user_id' => null]);
                if ($groupId) DB::table('course_subgroup_memberships')->insert(['course_id' => $course->id,
                    'subgroup_id' => $groupId, 'user_id' => $id, 'active_user_id' => $id, 'started_at' => $now, 'updated_by' => $actorId]);
            }
            $this->forget();
            // Keep XP visible immediately; refresh only derived progress, without replaying rewards.
            if ($changed && Schema::hasTable('course_student_points')) \App\CourseStudentPoints::recalculateForStudents($course->id, $changed);
            if ($changed && Schema::hasTable('lesson_student_stats')) \App\LessonStudentStats::recalculateForStudents($course->id, $changed, false);
        });
        $this->forget();
    }

    public function closeRemoved(Course $course): void
    {
        if (!$this->available()) return;
        DB::table('course_subgroup_memberships')->where('course_id', $course->id)->whereNotNull('active_user_id')
            ->whereNotIn('user_id', $course->students()->select('users.id'))
            ->update(['active_user_id' => null, 'ended_at' => now()]);
        $this->forget();
    }
}
