<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class CourseDeadlineWorkspaceTest extends DeadlineFeatureCase
{
    public function test_home_deadline_cards_open_the_corresponding_course_deadlines(): void
    {
        $this->policy('task', 11);
        DB::table('lesson_info')->insert(['course_id' => 2, 'lesson_id' => 1, 'start_date' => '2026-09-01']);
        DB::table('task_deadlines')->insert(['course_id' => 2, 'task_id' => 12, 'expiration' => '2026-11-16', 'penalty' => 0.7]);

        $response = $this->actingAs(User::findOrFail(1))->get('/insider/courses')->assertOk()->assertDontSee('Все сроки');
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//a[contains(@class, "gc-deadline-preview-link")]');

        $this->assertSame(2, $cards->length);
        $this->assertStringEndsWith('/insider/courses/1/deadlines', $cards->item(0)->getAttribute('href'));
        $this->assertStringEndsWith('/insider/courses/2/deadlines', $cards->item(1)->getAttribute('href'));
        $this->assertStringContainsString('Приветствие', $cards->item(0)->textContent);
        $this->assertStringContainsString('Небольшое исследование', $cards->item(1)->textContent);
    }

    public function test_student_deadline_tab_requires_an_upcoming_cutoff(): void
    {
        $courseUrl = '/insider/courses/1';
        $badge = 'data-course-deadline-badge';
        $this->actingAs(User::findOrFail(1))->get($courseUrl)->assertOk()->assertDontSee($badge, false);

        $this->policy('task', 11, [['deadline_date' => '2026-11-15', 'retained_percent' => 70]]);
        $this->get($courseUrl)->assertOk()->assertSee($badge, false);

        Carbon::setTestNow('2026-11-16 12:00:00 Europe/Moscow');
        $this->get($courseUrl)->assertOk()->assertDontSee($badge, false);
        $this->get($courseUrl.'/deadlines')->assertOk()->assertDontSee($badge, false)
            ->assertSee('Пропущенные дедлайны')->assertSee('Приветствие');

        $this->actingAs(User::findOrFail(2))->get($courseUrl)->assertOk()
            ->assertSee('fa-flag" aria-hidden="true"></i>Дедлайны', false);
    }

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

    public function test_completed_work_and_deadlines_with_full_penalty_are_hidden_from_students(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12);
        $this->solution(mark: 10);
        $url = '/insider/courses/1/deadlines';
        Carbon::setTestNow('2026-11-22 23:59:59 Europe/Moscow');
        $this->actingAs(User::find(1))->get($url)->assertOk()
            ->assertDontSee('data-deadline-task-id="11"', false)
            ->assertSee('data-deadline-task-id="12"', false);

        Carbon::setTestNow('2026-11-23 00:00:00 Europe/Moscow');
        $response = $this->get($url)->assertOk()->assertDontSee('data-deadline-row', false);
        $this->assertMatchesRegularExpression('/data-deadline-empty\s*>/', $response->getContent());
        $this->assertEmpty($response->viewData('deadlineItems'));

        $this->actingAs(User::find(2))->get($url)->assertOk()
            ->assertSee('data-deadline-task-id="12"', false)
            ->assertSee('Сейчас −100%')->assertSee('Последний срок')->assertSee('22.11 23:59')
            ->assertSee('Сдавать можно и после последнего срока');
    }

    public function test_deadline_visibility_follows_current_retained_percentage(): void
    {
        $this->policy('task', 11, [
            ['deadline_date' => '2026-11-15', 'retained_percent' => 0],
            ['deadline_date' => '2026-11-22', 'retained_percent' => 70],
        ]);
        $this->policy('task', 12, [['deadline_date' => '2026-11-15', 'retained_percent' => 0.004]]);
        Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $url = '/insider/courses/1/deadlines';
        $response = $this->actingAs(User::find(1))->get($url)->assertOk()->assertSee('data-deadline-task-id="12"', false);
        $this->assertMatchesRegularExpression('/data-deadline-task-id="11"[^>]* hidden[ ]*>/', $response->getContent());

        Carbon::setTestNow('2026-11-23 00:00:00 Europe/Moscow');
        $this->get($url)->assertOk()->assertSee('data-deadline-task-id="11"', false)->assertSee('Сейчас −30%');
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
        $expired = $this->get('/insider/courses/1/deadlines')->assertOk();
        $summary = \App\Support\CourseDeadlineWorkspace::navigation(\App\Course::find(1), User::find(1), $expired->viewData('deadlineItems'));
        $this->assertSame(0, $summary['count']);
        $expired->assertDontSee('data-deadline-task-id="12"', false);
        DB::table('blocked_tasks')->delete();
        $this->get('/insider/courses/1/deadlines')->assertOk()->assertDontSee('data-deadline-task-id="12"', false);
    }
}
