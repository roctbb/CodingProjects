<?php

namespace Tests\Feature;

use App\BlockedTask;
use App\Course;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LearningPresentationTest extends TestCase
{
    private function state(array $solutions = [], bool $blocked = false, bool $compact = false): string
    {
        $task = (new Task())->forceFill(['id' => 11, 'max_mark' => 10]);
        $task->setRelation('solutions', new Collection(array_map(fn ($attributes) => (new Solution())->forceFill([
            'user_id' => 1, 'course_id' => 133, 'submitted' => '2026-11-11 12:00:00', 'review_skipped' => false,
            ...$attributes,
        ]), $solutions)));
        $task->setRelation('deadlines', new Collection());
        $task->setRelation('blockedTasks', new Collection($blocked ? [(new BlockedTask())->forceFill(['user_id' => 1, 'course_id' => 133])] : []));
        $viewer = (new User())->forceFill(['id' => 1]);
        $course = (new Course())->forceFill(['id' => 133]);

        return Blade::render('<x-gc-task-state :task="$task" :viewer="$viewer" :course="$course" :compact="$compact" />', compact('task', 'viewer', 'course', 'compact'));
    }

    public function test_learning_state_explains_unsubmitted_checked_pending_and_blocked_work(): void
    {
        $this->assertStringContainsString('Нет решения', $this->state());
        $this->assertStringContainsString('Можно улучшить', $this->state([['mark' => 8]]));
        $this->assertStringContainsString('Выполнено', $this->state([['mark' => 10]]));
        $this->assertStringContainsString('На проверке', $this->state([['mark' => null]]));
        $this->assertStringContainsString('На проверке', $this->state([['mark' => 8, 'recheck_requested' => true]]));
        $this->assertStringContainsString('Заблокировано', $this->state([['mark' => null]], true));
        $this->assertStringContainsString('Нет решения', $this->state([['user_id' => 2, 'mark' => 10]]));
        $this->assertStringContainsString('Можно улучшить', $this->state([['mark' => 8], ['mark' => null, 'review_skipped' => true]]));
    }

    public function test_compact_task_status_keeps_a_text_alternative(): void
    {
        $html = $this->state([['mark' => null]], false, true);
        $this->assertStringContainsString('class="visually-hidden">На проверке', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_deadline_remains_current_until_the_end_of_the_day_and_settled_work_is_quiet(): void
    {
        Carbon::setTestNow('2026-11-11 23:59:00');
        try {
            $expiration = Carbon::parse('2026-11-11');
            $render = fn ($settled = false) => Blade::render('<x-gc-deadline-label :expiration="$expiration" :settled="$settled" />', ['expiration' => $expiration, 'settled' => $settled]);
            $this->assertStringContainsString('Сегодня', $render());
            $this->assertStringNotContainsString('Просрочено', $render());
            $this->assertStringContainsString('datetime="2026-11-11"', $render());
            Carbon::setTestNow('2026-11-12 00:00:00');
            $this->assertStringContainsString('Просрочено', $render());
            $this->assertStringNotContainsString('Просрочено', $render(true));
            $this->assertSame('2026-11-11 00:00:00', $expiration->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }
}
