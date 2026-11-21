<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_completed_work_is_hidden_and_last_stage_does_not_close_submissions(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12);
        $this->solution(mark: 10);
        Carbon::setTestNow('2026-11-24 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk();
        $response->assertSee('Сейчас −100%')->assertSee('Последний срок')->assertSee('22.11 23:59');
        $items = $response->viewData('deadlineItems');
        $this->assertNull($items->firstWhere('task.id', 11));
        $this->assertNull($items->firstWhere('task.id', 12)['next']);
        $this->assertStringContainsString('Сдавать можно и после последнего срока', $response->getContent());
    }

    public function test_cards_show_due_dates_for_pending_and_partial_work(): void
    {
        $this->policy('task', 11); $this->policy('task', 12);
        $this->solution(); $this->solution(mark: 5, task: 12);
        Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk();
        $items = $response->viewData('deadlineItems');
        $this->assertSame('pending', $items->firstWhere('task.id', 11)['status']);
        $this->assertSame('revision', $items->firstWhere('task.id', 12)['status']);
        $response->assertDontSee('data-deadline-filter=',false)->assertDontSee('5 / 10 XP')->assertSee('data-deadline-calendar',false)->assertSee('Сейчас −30%');
        $response->assertDontSee('План занятий')->assertDontSee('data-deadline-filter="done"', false);
    }

    public function test_correct_solution_with_a_deadline_deduction_is_still_excluded(): void
    {
        $this->policy('task', 11);
        $solution = $this->solution('2026-11-16 12:00:00', 10);
        $this->assertSame(7, (int) $solution->mark);
        $response = $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk();
        $this->assertEmpty($response->viewData('deadlineItems'));
        $response->assertSee('Нет невыполненных задач со сроками');
    }

    public function test_current_native_check_state_is_shown_before_a_solution_is_graded(): void
    {
        config(['services.geekexecutor_secret' => str_repeat('test', 16), 'code_checking.ai_detection_enabled' => false]);
        \Illuminate\Support\Facades\Storage::fake('code_checking');
        \Illuminate\Support\Facades\Storage::fake('code_originals');
        $this->policy('task', 11);
        $base = '/insider/courses/1/tasks/11';
        $this->actingAs(User::find(2))->putJson($base.'/checker/private', ['language' => 'python', 'check_type' => 'gpt', 'private_instructions' => 'Review this solution'])->assertOk();
        $created = $this->actingAs(User::find(1))->postJson($base.'/code-submissions', [
            'request_id' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'print(1)',
        ])->assertCreated()->json();
        foreach (['testing' => 'checking', 'awaiting_review' => 'pending', 'error' => 'check_error'] as $state => $expected) {
            \App\CodeExecutionRun::whereKey($created['execution_id'])->update(['state' => $state]);
            $response = $this->get('/insider/courses/1/deadlines')->assertOk();
            $this->assertSame($expected, $response->viewData('deadlineItems')->firstWhere('task.id', 11)['status']);
        }
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

    public function test_navigation_counts_tasks_once_and_excludes_complete_blocked_and_fully_penalized_work(): void
    {
        $this->policy('task', 11); $this->policy('task', 12);
        Carbon::setTestNow('2026-11-15 12:00:00 Europe/Moscow');
        $today = $this->actingAs(User::find(1))->get('/insider/courses/1/deadlines')->assertOk();
        $this->assertStringContainsString('data-deadline-badge-status="today"', $today->getContent());
        $summary = \App\Support\CourseDeadlineWorkspace::navigation(\App\Course::find(1), User::find(1), $today->viewData('deadlineItems'));
        $this->assertSame(2, $summary['count']);
        $this->assertSame('today', $summary['status']);
        Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $overdue = $this->get('/insider/courses/1/deadlines')->assertOk();
        $this->assertStringContainsString('data-deadline-badge-status="overdue"', $overdue->getContent());
        DB::table('blocked_tasks')->insert(['course_id' => 1, 'task_id' => 12, 'user_id' => 1]);
        $blocked = $this->get('/insider/courses/1/deadlines')->assertOk();
        $summary = \App\Support\CourseDeadlineWorkspace::navigation(\App\Course::find(1), User::find(1), $blocked->viewData('deadlineItems'));
        $this->assertSame(1, $summary['count']);
        $this->solution('2026-11-14 12:00:00', 10);
        $done = $this->get('/insider/courses/1/deadlines')->assertOk();
        $summary = \App\Support\CourseDeadlineWorkspace::navigation(\App\Course::find(1), User::find(1), $done->viewData('deadlineItems'));
        $this->assertSame(0, $summary['count']);
        Carbon::setTestNow('2026-11-24 12:00:00 Europe/Moscow');
        DB::table('blocked_tasks')->delete();
        $expired = $this->get('/insider/courses/1/deadlines')->assertOk();
        $summary = \App\Support\CourseDeadlineWorkspace::navigation(\App\Course::find(1), User::find(1), $expired->viewData('deadlineItems'));
        $this->assertSame(0, $summary['count']);
    }
}
