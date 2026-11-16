<?php

namespace App\Services;

use App\CodeSubmission;
use App\CodeTaskChecker;
use App\Course;
use App\CourseActivity;
use App\Services\CodeChecking\HistoricalReport;
use App\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class StudentLessonFeedback
{
    public function visible(Collection $rows, Course $course, User $student): Collection
    {
        $withFeedback = $rows->filter(fn ($row) => $row['feedback'] !== '');
        if ($withFeedback->isEmpty()) return $rows;
        $taskIds = $withFeedback->pluck('task.id');
        $native = Schema::hasTable('code_task_checkers') ? CodeTaskChecker::whereIn('task_id', $taskIds)->pluck('task_id') : collect();
        if (Schema::hasTable('code_submissions')) $native = $native->merge(CodeSubmission::where('course_id', $course->id)
            ->where('user_id', $student->id)->whereIn('task_id', $taskIds)->distinct()->pluck('task_id'));
        $activities = Schema::hasTable('course_activities') ? CourseActivity::where('course_id', $course->id)->where('user_id', $student->id)
            ->whereIn('solution_id', $withFeedback->pluck('feedbackSolutionId'))->where('type', CourseActivity::TYPE_SOLUTION_CHECKED)
            ->orderByDesc('id')->get(['solution_id', 'task_id', 'payload'])->unique('solution_id')->keyBy('solution_id') : collect();

        return $rows->map(function ($row) use ($native, $activities) {
            $comment = $row['feedback'];
            $row['feedbackAvailable'] = $comment !== '';
            if ($comment === '') return $row;
            $activity = $activities->get($row['feedbackSolutionId']);
            $payload = $activity?->payload ?? [];
            $teacher = $activity && (int) $activity->task_id === (int) $row['task']->id
                && ($payload['feedback_source'] ?? null) === 'teacher' && is_string($payload['feedback_sha256'] ?? null)
                && hash_equals(hash('sha256', $comment), $payload['feedback_sha256'])
                && is_string($payload['editable_comment'] ?? null) && mb_strlen($payload['editable_comment']) <= 20000;
            if ($teacher) $row['feedback'] = $payload['editable_comment'];
            elseif ($row['task']->is_code || $native->contains($row['task']->id) || app(HistoricalReport::class)->parse($comment) !== null) {
                // Automatic reports are opened through the existing viewer, which controls test disclosure.
                $row['feedback'] = '';
            }
            return $row;
        });
    }
}
