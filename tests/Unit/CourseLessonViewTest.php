<?php

namespace Tests\Unit;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\Solution;
use App\Support\CourseLessonView;
use App\Support\CourseLessonStatistics;
use App\Task;
use App\User;
use Tests\TestCase;

class CourseLessonViewTest extends TestCase
{
    private function state(array $marks, float $percent, string $courseState = 'started', bool $pending = false): array
    {
        $user = (new User())->forceFill(['id' => 1]);
        $course = (new Course())->forceFill(['id' => 1, 'state' => $courseState]);
        $course->setRelation('teachers', collect());
        $tasks = collect($marks)->map(function ($mark, $index) use ($pending) {
            $task = (new Task())->forceFill(['id' => $index + 1, 'max_mark' => 10, 'is_hidden' => false, 'is_star' => false]);
            $task->setRelation('solutions', collect($mark === null && !$pending ? [] : [
                (new Solution())->forceFill(['user_id' => 1, 'course_id' => 1, 'mark' => $mark, 'submitted' => now(), 'review_skipped' => false, 'recheck_requested' => $pending]),
            ]));
            return $task;
        });
        $steps = $tasks->map(function ($task) {
            $step = (new ProgramStep())->forceFill(['id' => $task->id]);
            $step->setRelation('tasks', collect([$task]));
            return $step;
        });
        $lesson = new Lesson();
        $lesson->setRelation('steps', $steps);
        return CourseLessonView::forStudent($lesson, $course, $user, (object) ['max_points' => count($marks) * 10, 'percent' => $percent]);
    }

    public function test_reward_threshold_does_not_claim_that_all_tasks_are_fully_solved(): void
    {
        $state = $this->state([9, 10], 95);
        $this->assertTrue($state['rewardEarned']);
        $this->assertFalse($state['allTasksDone']);
        $this->assertSame('Улучшить результат', $state['action']);
        $this->assertSame('Улучшить', $state['hint']);
        $this->assertSame(1, $state['nextStep']->id);
        $this->assertSame('Наклейка получена · можно улучшить', $state['status']);
    }

    public function test_fully_solved_and_ongoing_work_have_consistent_actions(): void
    {
        $done = $this->state([10, 10], 100);
        $this->assertTrue($done['allTasksDone']);
        $this->assertSame('Повторить урок', $done['action']);
        $this->assertSame('Повторить', $done['hint']);
        $ongoing = $this->state([10, null], 50);
        $this->assertFalse($ongoing['rewardEarned']);
        $this->assertSame('Продолжить урок', $ongoing['action']);
        $this->assertSame(2, $ongoing['nextStep']->id);
    }

    public function test_pending_work_is_not_presented_as_an_instruction_to_resubmit(): void
    {
        $state = $this->state([9], 95, pending: true);
        $this->assertSame('Посмотреть работу', $state['action']);
        $this->assertSame('На проверке', $state['hint']);
    }

    public function test_ended_courses_and_unscored_reading_have_explicit_actions(): void
    {
        $this->assertSame('Повторить материалы', $this->state([null], 0, 'ended')['action']);
        $reading = $this->state([], 0);
        $this->assertFalse($reading['hasTasks']);
        $this->assertFalse($reading['rewardEarned']);
        $this->assertSame('Читать материалы', $reading['action']);
    }

    public function test_statistics_include_missing_students_as_zero_and_clamp_bonus_scores(): void
    {
        $summary = CourseLessonStatistics::summary(collect([
            (object) ['percent' => 140, 'max_points' => 10],
            (object) ['percent' => 50, 'max_points' => 10],
        ]), 3);
        $this->assertSame(['average' => 50, 'rewarded' => 1, 'total' => 3], $summary);
        $this->assertSame(0, CourseLessonStatistics::summary(collect(), 0)['average']);
    }
}
