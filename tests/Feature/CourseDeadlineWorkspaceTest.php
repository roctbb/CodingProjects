<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Tests\Support\DeadlineFeatureCase;

class CourseDeadlineWorkspaceTest extends DeadlineFeatureCase
{
    public function test_each_task_has_one_row_with_current_penalty_and_all_stages(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12);
        Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::find(2))->get('/insider/courses/1/deadlines')->assertOk();
        $response->assertSee('Сейчас −30%')->assertSee('22.11 23:59');
        $response->assertDontSee('Срок прошёл')->assertDontSee('70% баллов после срока');
        $this->assertSame(2, substr_count($response->getContent(), 'data-deadline-row '));
        $this->assertSame(1, substr_count($response->getContent(), 'data-deadline-task-id="11"'));
        $this->assertSame(4, substr_count($response->getContent(), 'data-deadline-stage '));
    }

    public function test_completed_work_keeps_its_state_and_last_stage_does_not_close_submissions(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12);
        $this->solution(mark: 10);
        Carbon::setTestNow('2026-11-24 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk();
        $response->assertSee('Выполнено')->assertSee('Сейчас −100%')->assertSee('Последний срок')->assertSee('22.11.2026 23:59');
        $items = $response->viewData('deadlineItems');
        $this->assertTrue($items->firstWhere('task.id', 11)['done']);
        $this->assertNull($items->firstWhere('task.id', 12)['next']);
        $this->assertStringContainsString('Сдавать можно и после последнего срока', $response->getContent());
    }

    public function test_fractional_penalties_and_next_year_dates_are_preserved(): void
    {
        $this->policy('task', 11, [['deadline_date' => '2026-11-15', 'retained_percent' => 62.5],
            ['deadline_date' => '2027-01-15', 'retained_percent' => 0]]);
        Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::find(2))->get('/insider/courses/1/deadlines')->assertOk();
        $response->assertSee('Сейчас −37.5%')->assertSee('2027-01-15', false);
        $this->assertCount(1, $response->viewData('deadlineItems'));
    }
}
