<?php

namespace Tests\Feature;

use App\Http\Controllers\CoursesController;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseWorkspaceFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, image TEXT, custom_title TEXT, custom_title_expires_at TEXT, avatar_frame TEXT, avatar_frame_config TEXT, avatar_frame_expires_at TEXT);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, name TEXT, state TEXT);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, is_remote INTEGER DEFAULT 0, hidden_from_stats INTEGER DEFAULT 0);
            CREATE TABLE course_activities (id INTEGER PRIMARY KEY, course_id INTEGER, user_id INTEGER, lesson_id INTEGER, task_id INTEGER, type TEXT, payload TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE achievements (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, chapter_id INTEGER, sort_index INTEGER);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, name TEXT, max_mark INTEGER, step_id INTEGER DEFAULT 1);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER, submitted TEXT, mark INTEGER, recheck_requested INTEGER DEFAULT 0, review_skipped INTEGER DEFAULT 0, recheck_comment TEXT, updated_at TEXT);
            INSERT INTO users (id, name, role) VALUES (1,'Teacher','teacher'),(2,'Student','student');
            INSERT INTO courses VALUES (1,'Managed course','started'),(2,'Other managed course','started'),(3,'Private course','started'),(4,'Ended course','ended');
            INSERT INTO course_teachers (course_id,user_id) VALUES (1,1),(2,1),(4,1);
            INSERT INTO tasks (id,name,max_mark) VALUES (1,'Task',10);
            INSERT INTO solutions (id,course_id,task_id,user_id,submitted,mark) VALUES (1,1,1,2,'2026-11-11 10:00:00',NULL),(2,2,1,2,'2026-11-11 10:00:00',NULL),(3,3,1,2,'2026-11-11 10:00:00',NULL),(4,4,1,2,'2026-11-11 10:00:00',NULL);
            INSERT INTO course_activities (id,course_id,type,created_at) VALUES (1,1,'solution_submitted','2026-11-11 10:00:00'),(2,2,'solution_checked','2026-11-11 09:00:00'),(3,3,'solution_submitted','2026-11-11 11:00:00'),(4,4,'solution_submitted','2026-11-11 12:00:00'),(5,1,'market_purchase','2026-11-11 13:00:00');
            SQL);
        $this->actingAs(User::findOrFail(1));
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_pulse_filters_combine_without_exposing_other_courses(): void
    {
        $controller = new CoursesController();
        $view = $controller->pulse(new Request(['course' => 1, 'type' => 'learning']));
        $this->assertSame([1], $view->getData()['activities']->pluck('id')->all());
        $this->assertSame([1, 2], $view->getData()['pulseCourses']->pluck('id')->all());
        foreach ([3, 4] as $course) {
            $this->assertSame(0, $controller->pulse(new Request(['course' => $course]))->getData()['activities']->total());
        }
        $this->assertSame([5], $controller->pulse(new Request(['course' => 1, 'type' => 'store']))->getData()['activities']->pluck('id')->all());
    }

    public function test_filtered_review_queue_and_skip_all_have_the_same_course_scope(): void
    {
        $controller = new CoursesController();
        $this->assertSame([1], $controller->reviews(new Request(['course' => 1]))->getData()['pendingSolutions']->pluck('id')->all());
        $this->assertFalse($controller->reviews(new Request(['course' => 1]))->getData()['reviewQueueAll']);
        $all = $controller->reviews(new Request())->getData();
        $this->assertTrue($all['reviewQueueAll']);
        $this->assertSame([2, 1], $all['reviewQueue']->pluck('id')->all());
        foreach(['task','student','solutions','nextReview'] as $detail)$this->assertArrayNotHasKey($detail,$all);
        $this->assertSame('courses.reviews',$controller->reviews(new Request())->getName());
        $this->assertSame(url('/insider/courses/2/steps/1').'?student=2&task=1&attempt=solution-2#task1',app(\App\Services\TaskLocation::class)->solution($all['pendingSolutions']->first()));
        $this->assertSame(0, $controller->reviews(new Request(['course' => 3]))->getData()['pendingSolutions']->total());
        $controller->resetPendingReviews(new Request(['course' => 1]));
        $this->assertSame(1, (int) DB::table('solutions')->where('id', 1)->value('review_skipped'));
        $this->assertSame(0, DB::table('solutions')->whereIn('id', [2, 3, 4])->sum('review_skipped'));
        $this->assertSame([2], $controller->reviews(new Request())->getData()['pendingSolutions']->pluck('id')->all());
    }

    public function test_pulse_search_is_case_insensitive_literal_and_keeps_course_permissions(): void
    {
        DB::table('users')->where('id', 2)->update(['name' => 'Мария Орлова %_!']);
        DB::table('course_activities')->whereIn('id', [1, 3])->update(['user_id' => 2]);
        foreach (['орлова', '%_!'] as $search) {
            $data = (new CoursesController())->pulse(new Request(['q' => $search]))->getData();
            $this->assertSame([1], $data['activities']->pluck('id')->all());
        }
        $this->assertSame(0, (new CoursesController())->pulse(new Request(['q' => "%' OR 1=1 --"]))->getData()['activities']->total());
    }

    public function test_filtered_global_review_toolbar_renders_without_an_open_task_or_student():void
    {
        $request=Request::create('/insider/reviews?course=1');$this->app->instance('request',$request);
        $view=(new CoursesController())->reviews($request);
        $this->assertSame('courses.reviews',$view->getName());
        $html=view('steps.partials.review-filters',$view->getData())->render();
        $this->assertStringContainsString('value="1" selected',$html);
        $this->assertStringNotContainsString('Курс вне активной очереди',$html);
    }

    public function test_pulse_chart_uses_selected_accessible_course_instead_of_all_courses(): void
    {
        $pulse = $this->mock(\App\Services\CoursePulse::class);
        $pulse->shouldReceive('forCourses')->once()->withArgs(fn ($ids) => $ids->all() === [1])->andReturn(['current' => 12]);
        $pulse->shouldReceive('forCourses')->once()->withArgs(fn ($ids) => $ids->isEmpty())->andReturn(['current' => 0]);
        $this->assertSame(12, (new CoursesController())->pulse(new Request(['course' => 1]))->getData()['pulse']['current']);
        $this->assertSame(0, (new CoursesController())->pulse(new Request(['course' => 3]))->getData()['pulse']['current']);
    }

    public function test_review_search_status_and_bulk_skip_share_the_same_scope(): void
    {
        DB::table('users')->where('id', 2)->update(['name' => 'Мария Орлова %_!']);
        DB::table('solutions')->where('id', 1)->update(['recheck_requested' => 1, 'mark' => 3]);
        $controller = new CoursesController();
        $filters = ['q' => 'орлова', 'review_status' => 'recheck'];
        $this->assertSame([1], $controller->reviews(new Request($filters))->getData()['reviewQueue']->pluck('id')->all());
        $this->assertSame([2], $controller->reviews(new Request(['q' => '%_!', 'review_status' => 'new']))->getData()['reviewQueue']->pluck('id')->all());
        $controller->resetPendingReviews(new Request($filters));
        $this->assertSame(1, (int) DB::table('solutions')->where('id', 1)->value('review_skipped'));
        $this->assertSame(0, DB::table('solutions')->whereIn('id', [2, 3, 4])->sum('review_skipped'));
    }

    public function test_next_review_moves_forward_in_queue_before_wrapping(): void
    {
        DB::table('users')->insert(['id' => 3, 'name' => 'Another student', 'role' => 'student']);
        DB::table('solutions')->insert(['id' => 5, 'course_id' => 2, 'task_id' => 1, 'user_id' => 3, 'submitted' => '2026-11-11 08:00:00']);
        $service = app(\App\Services\ReviewWorkspace::class);
        $queue = $service->queue(auth()->user())->paginate(40);
        $data = $service->data(\App\Course::find(1), \App\Task::find(1), User::find(2), $queue, true);
        $this->assertSame(5, $data['nextReview']->id);
        $last = $service->data(\App\Course::find(2), \App\Task::find(1), User::find(3), $queue, true);
        $this->assertSame(2, $last['nextReview']->id);
    }

    public function test_orphaned_submissions_do_not_break_the_review_workspace(): void
    {
        DB::table('solutions')->insert(['id' => 99, 'course_id' => 1, 'task_id' => 1, 'user_id' => 999, 'submitted' => '2026-11-12 10:00:00']);
        $this->assertSame([2, 1], (new CoursesController())->reviews(new Request())->getData()['reviewQueue']->pluck('id')->all());
    }

    public function test_invalid_search_shape_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new CoursesController())->pulse(new Request(['q' => ['unexpected']]));
    }

    public function test_journal_scopes_scores_to_course_and_reuses_the_review_queue(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE courses ADD COLUMN program_id INTEGER;
            UPDATE courses SET program_id=1 WHERE id=1;
            CREATE TABLE programs (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE program_chapters (id INTEGER PRIMARY KEY, name TEXT, program_id INTEGER, sort_index INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, lesson_id INTEGER, sort_index INTEGER);
            CREATE TABLE blocked_tasks (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER);
            INSERT INTO programs VALUES (1,'Program');
            INSERT INTO program_chapters VALUES (1,'First',1,100),(2,'Second',1,200);
            INSERT INTO lessons VALUES (1,'First lesson',1,1,100),(2,'Second lesson',1,2,200);
            INSERT INTO program_steps VALUES (1,1,100);
            UPDATE tasks SET step_id=1 WHERE id=1;
            INSERT INTO course_students (course_id,user_id) VALUES (1,2);
            UPDATE solutions SET mark=10 WHERE id=2;
            SQL);
        $controller = new CoursesController();
        $data = $controller->assessments(1)->getData();
        $this->assertSame([1], $data['course']->students->first()->submissions->pluck('id')->all());
        $this->assertSame(1, $data['assessmentChapter']->id);
        $this->assertSame([1], $data['assessmentLessons']->pluck('id')->all());
        $this->assertTrue($data['pendingReviewPairs']->has('2:1'));
        DB::table('solutions')->insert(['id' => 5, 'course_id' => 1, 'task_id' => 1, 'user_id' => 2, 'submitted' => '2026-11-11 11:00:00', 'mark' => 10]);
        $this->assertFalse($controller->assessments(1)->getData()['pendingReviewPairs']->has('2:1'));
        request()->query->set('chapter', 2);
        $this->assertSame([2], $controller->assessments(1)->getData()['assessmentLessons']->pluck('id')->all());
        DB::statement('ALTER TABLE courses ADD COLUMN default_chapter_id INTEGER');
        DB::table('courses')->where('id', 1)->update(['default_chapter_id' => 2]);
        request()->query->remove('chapter');
        $this->assertSame(2, $controller->assessments(1)->getData()['assessmentChapter']->id);
        request()->query->set('chapter', 1);
        $this->assertSame([1], $controller->assessments(1)->getData()['assessmentLessons']->pluck('id')->all());
    }
}
