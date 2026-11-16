<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\DeadlineFeatureCase;

class CourseLessonCardsTest extends DeadlineFeatureCase
{
    private function cards(int $viewer): \DOMXPath
    {
        DB::table('courses')->where('id', 1)->update(['current_lesson_id' => 1]);
        DB::table('lesson_info')->where('course_id', 1)->where('lesson_id', 2)->update(['start_date' => '2027-01-01']);
        $response = $this->actingAs(User::findOrFail($viewer))->get('/insider/courses/1')->assertOk();
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        return new \DOMXPath($document);
    }

    public function test_compact_lessons_do_not_need_expansion_and_respect_material_access(): void
    {
        $xpath = $this->cards(1);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-1"]/details')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-1"]//a[@aria-current="step"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-1"]//*[@role="progressbar"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-1"]//ol')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-1"]//*[@data-course-stats-toggle]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-2"]//details')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-2"]//h3')->length);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-2"]//*[@role="progressbar"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-2"]//a[contains(@href,"/steps/2")]')->length);
        $this->assertGreaterThan(0, $xpath->query('//*[@id="lesson-1"]//a[contains(@href,"/steps/1")]')->length);
    }

    public function test_teacher_can_open_closed_lesson_materials_and_keeps_lesson_actions_and_statistics(): void
    {
        $xpath = $this->cards(2);
        $this->assertGreaterThan(0, $xpath->query('//*[@id="lesson-2"]//a[contains(@href,"/steps/2")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-2"]//button[@aria-label="Действия с уроком Выбор и условия"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-2"]//*[@data-course-stats-toggle]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-2"]//*[@role="progressbar"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-2"]//*[@role="img" and @aria-label="Урок 2"]')->length);
    }

    public function test_student_ring_shows_their_own_progress_and_the_title_opens_the_lesson(): void
    {
        DB::table('lesson_student_stats')->where('lesson_id', 3)->where('student_id', 1)
            ->update(['points' => 9.5, 'percent' => 47.5]);
        $xpath = $this->cards(1);
        $ring = $xpath->query('//*[@id="lesson-3"]//*[@role="progressbar"]')->item(0);
        $this->assertSame('48', $ring->getAttribute('aria-valuenow'));
        $this->assertStringContainsString('9.5 / 20 XP', $ring->getAttribute('aria-valuetext'));
        $this->assertSame(1, $xpath->query('//*[@id="lesson-3"]//h3/a[contains(@href,"/steps/3")]')->length);
        $personalStats = $xpath->query('//*[@id="lesson-3"]//*[@id="marks3"]')->item(0);
        $this->assertStringContainsString('9.5 / 20 XP', $personalStats->textContent);
        $this->assertStringNotContainsString('Средний прогресс', $personalStats->textContent);
        $this->assertFalse($personalStats->hasAttribute('data-course-stats-url'));
        $this->assertSame(1, $xpath->query('//*[@id="lesson-3"]//*[@data-course-stats-target="#marks3"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-3"]//form[contains(@action,"current-lesson")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-3"]//*[@data-copy-text]')->length);
    }

    public function test_calendar_for_different_task_rules_uses_the_next_date_and_lists_every_penalty_stage(): void
    {
        $this->policy('task', 11);
        $this->policy('task', 12, stages: [
            ['deadline_date' => '2026-11-16', 'retained_percent' => 50],
            ['deadline_date' => '2026-11-23', 'retained_percent' => 0],
        ]);
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $response = $this->actingAs(User::findOrFail(1))->get('/insider/courses/1')->assertOk();
        $response->assertSee('штраф 30%')->assertSee('штраф 50%')->assertSee('штраф 100%');
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $label = $xpath->query('//*[@id="lesson-1"]//button[@popovertarget]')->item(0)->textContent;
        $this->assertStringContainsString('22.11', $label);
        $this->assertStringNotContainsString('15.11', $label);
    }

    public function test_calendar_alerts_follow_student_work_and_are_not_shown_to_the_teacher(): void
    {
        $this->policy('task', 11);
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $student = $this->cards(1);
        $this->assertSame(1, $student->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
        $teacher = $this->cards(2);
        $this->assertSame(0, $teacher->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
        $pending = $this->solution('2026-11-14 12:00:00', task: 11);
        $this->assertSame(0, $this->cards(1)->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
        DB::table('solutions')->where('id', $pending->id)->update(['mark' => 10]);
        $this->assertSame(0, $this->cards(1)->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
    }

    public function test_calendar_is_yellow_today_and_neutral_after_a_full_penalty(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $this->policy('task', 11, stages: [['deadline_date' => '2026-11-17', 'retained_percent' => 50]]);
        $this->assertSame(1, $this->cards(1)->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="today"]')->length);
        $this->policy('task', 11, stages: [['deadline_date' => '2026-11-16', 'retained_percent' => 0]]);
        $xpath = $this->cards(1);
        $this->assertSame(0, $xpath->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue" or @data-lesson-deadline-status="today"]')->length);
        $this->assertStringContainsString('16.11', $xpath->query('//*[@id="lesson-1"]//button[@popovertarget]')->item(0)->textContent);
    }

    public function test_different_past_task_deadlines_stay_on_the_card_but_out_of_upcoming_previews(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-17 12:00:00 Europe/Moscow');
        $this->policy('task', 11, stages: [['deadline_date' => '2026-11-15', 'retained_percent' => 70]]);
        $this->policy('task', 12, stages: [['deadline_date' => '2026-11-14', 'retained_percent' => 50]]);
        $xpath = $this->cards(1);
        $this->assertStringContainsString('15.11', $xpath->query('//*[@id="lesson-1"]//button[@popovertarget]')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//*[@id="lesson-1"]//*[@data-lesson-deadline-status="overdue"]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"gc-course-learning-focus__deadlines")]//time')->length);
    }
}
