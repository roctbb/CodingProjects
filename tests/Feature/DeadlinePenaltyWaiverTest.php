<?php

namespace Tests\Feature;

use App\Course;
use App\CourseActivity;
use App\Solution;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeadlinePenaltyWaiverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Notification::fake();

        DB::unprepared(<<<'SQL'
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, role TEXT, email_verified_at TEXT);
            CREATE TABLE programs (id INTEGER PRIMARY KEY);
            CREATE TABLE courses (id INTEGER PRIMARY KEY, program_id INTEGER, name TEXT, state TEXT);
            CREATE TABLE course_teachers (course_id INTEGER, user_id INTEGER, hidden_from_stats BOOLEAN DEFAULT 0);
            CREATE TABLE course_students (course_id INTEGER, user_id INTEGER, hidden_from_stats BOOLEAN DEFAULT 0, is_remote BOOLEAN DEFAULT 0);
            CREATE TABLE lessons (id INTEGER PRIMARY KEY, program_id INTEGER, sort_index INTEGER DEFAULT 0, name TEXT);
            CREATE TABLE lesson_info (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, start_date TEXT);
            CREATE TABLE lesson_early_accesses (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, user_id INTEGER);
            CREATE TABLE program_steps (id INTEGER PRIMARY KEY, program_id INTEGER, lesson_id INTEGER, sort_index INTEGER DEFAULT 0);
            CREATE TABLE tasks (id INTEGER PRIMARY KEY, step_id INTEGER, name TEXT, max_mark INTEGER, price INTEGER DEFAULT 0, is_hidden BOOLEAN DEFAULT 0, is_star BOOLEAN DEFAULT 0, sort_index INTEGER DEFAULT 0, generates_ai_achievement BOOLEAN DEFAULT 0);
            CREATE TABLE task_deadlines (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, expiration TEXT, penalty REAL);
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER, teacher_id INTEGER, text TEXT, comment TEXT, mark INTEGER, raw_mark INTEGER, submitted TEXT, checked TEXT, deadline_penalty_amount INTEGER DEFAULT 0, deadline_penalty_days INTEGER DEFAULT 0, deadline_penalty_paid_at TEXT, xp_booster_used_at TEXT, xp_booster_amount INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT);
            CREATE TABLE course_student_points (id INTEGER PRIMARY KEY, course_id INTEGER, student_id INTEGER, points INTEGER, max_points INTEGER, percent REAL, created_at TEXT, updated_at TEXT);
            CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, student_id INTEGER, points INTEGER, max_points INTEGER, percent REAL, created_at TEXT, updated_at TEXT, UNIQUE (course_id, lesson_id, student_id));
            CREATE TABLE ranks (id INTEGER PRIMARY KEY, "from" INTEGER, "to" INTEGER);
            CREATE TABLE achievements (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT);
            CREATE TABLE completed_courses (id INTEGER PRIMARY KEY, user_id INTEGER, mark TEXT);
            CREATE TABLE coin_transactions (id INTEGER PRIMARY KEY, user_id INTEGER, price INTEGER, comment TEXT, created_at TEXT, updated_at TEXT);
            CREATE TABLE course_activities (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, step_id INTEGER, task_id INTEGER, solution_id INTEGER, user_id INTEGER, type TEXT, payload TEXT, created_at TEXT, updated_at TEXT);
            INSERT INTO users VALUES (1, 'Teacher', 'teacher', '2026-01-01'), (2, 'Student', 'student', '2026-01-01'), (3, 'Other teacher', 'teacher', '2026-01-01'), (4, 'Admin', 'admin', '2026-01-01');
            INSERT INTO programs VALUES (1);
            INSERT INTO courses VALUES (1, 1, 'Course', 'started'), (2, 1, 'Other course', 'started');
            INSERT INTO course_teachers (course_id, user_id) VALUES (1, 1);
            INSERT INTO course_students (course_id, user_id) VALUES (1, 2), (1, 3);
            INSERT INTO lessons (id, program_id, name) VALUES (1, 1, 'Lesson');
            INSERT INTO lesson_info VALUES (1, 1, 1, '2026-01-01');
            INSERT INTO program_steps (id, program_id, lesson_id) VALUES (1, 1, 1);
            INSERT INTO tasks (id, step_id, name, max_mark) VALUES (1, 1, 'Task', 20), (2, 1, 'Other task', 20);
            INSERT INTO task_deadlines VALUES (1, 1, 1, '2026-01-01', 0.5);
            INSERT INTO solutions (id, course_id, task_id, user_id, teacher_id, text, comment, mark, raw_mark, submitted, checked, deadline_penalty_amount, deadline_penalty_days) VALUES (1, 1, 1, 2, 1, 'Answer', 'Teacher feedback', 8, 16, '2026-01-05 12:00:00', '2026-01-06 12:00:00', 8, 4);
            INSERT INTO ranks VALUES (1, 0, 1000);
            SQL);
        (require database_path('migrations/2026_10_06_190000_add_deadline_penalty_waiver_to_solutions.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function waive(int $actor = 1, int $course = 1, int $task = 1)
    {
        return $this->actingAs(User::findOrFail($actor))
            ->post('/insider/courses/' . $course . '/tasks/' . $task . '/solution/1/waive-deadline-penalty');
    }

    public function testTeacherRestoresXpWithoutChargingAndRecalculatesCachedStats(): void
    {
        $this->waive()->assertRedirect('/insider/courses/1/tasks/1/student/2#solution-1');
        $solution = Solution::findOrFail(1);
        $this->assertSame(16, $solution->mark);
        $this->assertSame(1, $solution->deadline_penalty_waived_by);
        $this->assertNotNull($solution->deadline_penalty_waived_at);
        $this->assertNull($solution->deadline_penalty_paid_at);
        $this->assertSame('Teacher feedback', $solution->comment);
        $this->assertFalse($solution->hasActiveDeadlinePenalty());
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertDatabaseHas('course_student_points', ['course_id' => 1, 'student_id' => 2, 'points' => 16]);
        $this->assertDatabaseHas('lesson_student_stats', ['course_id' => 1, 'lesson_id' => 1, 'student_id' => 2, 'points' => 16]);
        $this->assertEquals(16, $solution->user->score());
        $activity = CourseActivity::where('type', CourseActivity::TYPE_DEADLINE_PENALTY_WAIVED)->firstOrFail();
        $this->assertSame(1, $activity->payload['teacher_id']);
    }

    public function testAdminCanWaiveAndRetryDoesNotRepeatTheAction(): void
    {
        $this->waive(4)->assertRedirect();
        $waivedAt = Solution::find(1)->deadline_penalty_waived_at;
        $this->waive(4)->assertRedirect();
        $this->assertTrue($waivedAt->eq(Solution::find(1)->deadline_penalty_waived_at));
        $this->assertDatabaseCount('course_activities', 1);
        $this->assertDatabaseCount('coin_transactions', 0);
    }

    public function testStudentAndTeacherWithoutTeachingMembershipCannotWaive(): void
    {
        $this->waive(2)->assertForbidden();
        $this->waive(3)->assertForbidden();
        $this->assertSame(8, Solution::find(1)->mark);
        $this->assertNull(Solution::find(1)->deadline_penalty_waived_at);
    }

    public function testRouteCannotTargetSolutionFromDifferentTaskOrCourse(): void
    {
        $this->waive(4, 2)->assertNotFound();
        $this->waive(4, 1, 2)->assertNotFound();
        $this->assertSame(8, Solution::find(1)->mark);
    }

    public function testRegradingAndBoosterKeepThePenaltyWaived(): void
    {
        $this->waive()->assertRedirect();
        $solution = Solution::find(1);
        $solution->applyDeadlinePenalty(10);
        $this->assertSame(10, $solution->mark);
        $solution->xp_booster_used_at = now();
        $solution->applyDeadlinePenalty(10);
        $this->assertSame(15, $solution->mark);
        $this->assertSame(10, $solution->markWithoutXpBooster());
        $this->assertFalse($solution->hasActiveDeadlinePenalty());
        $this->assertTrue($solution->hasScoreModifier());
    }

    public function testExistingBoosterAndLegacyMissingRawMarkAreRestoredCorrectly(): void
    {
        DB::table('solutions')->where('id', 1)->update([
            'raw_mark' => null, 'mark' => 9, 'deadline_penalty_amount' => 9,
            'xp_booster_used_at' => now(), 'xp_booster_amount' => 5,
        ]);
        $this->waive()->assertRedirect();
        $solution = Solution::find(1);
        $this->assertSame(13, $solution->raw_mark);
        $this->assertSame(18, $solution->mark);
    }

    public function testAlreadyPaidOrUnpenalizedSolutionsAreNotChanged(): void
    {
        DB::table('solutions')->where('id', 1)->update(['deadline_penalty_paid_at' => now(), 'mark' => 16]);
        $this->waive()->assertRedirect();
        $this->assertNull(Solution::find(1)->deadline_penalty_waived_at);
        DB::table('solutions')->where('id', 1)->update(['deadline_penalty_paid_at' => null, 'deadline_penalty_amount' => 0]);
        $this->waive()->assertRedirect();
        $this->assertNull(Solution::find(1)->deadline_penalty_waived_at);
        $this->assertDatabaseCount('course_activities', 0);
    }

    public function testButtonOnlyAppearsForCourseTeacherOrAdminWithActivePenalty(): void
    {
        foreach ([1 => true, 2 => false, 3 => false, 4 => true] as $actor => $visible) {
            $this->actingAs(User::find($actor));
            $html = view('steps.partials.waive_deadline_penalty', ['solution' => Solution::find(1), 'course' => Course::find(1)])->render();
            $this->assertSame($visible, str_contains($html, 'Убрать штраф'));
        }
        $this->waive()->assertRedirect();
        $html = view('steps.partials.waive_deadline_penalty', ['solution' => Solution::find(1), 'course' => Course::find(1)])->render();
        $this->assertStringNotContainsString('Убрать штраф', $html);
        $this->assertStringContainsString('Штраф снят преподавателем', $html);
    }

    public function testGuestCannotWaiveAndStudentCannotPayAfterWaiver(): void
    {
        $this->post('/insider/courses/1/tasks/1/solution/1/waive-deadline-penalty')->assertRedirect('/login');
        $this->waive()->assertRedirect();
        $this->actingAs(User::find(2))->post('/insider/courses/1/tasks/1/solution/1/deadline-penalty')->assertRedirect();
        $this->assertDatabaseCount('coin_transactions', 0);
        $this->assertSame(16, Solution::find(1)->mark);
    }

    public function testRestoringFullMarksAwardsTaskCoinsOnlyOnce(): void
    {
        $this->withoutExceptionHandling();
        DB::table('tasks')->where('id', 1)->update(['price' => 3]);
        DB::table('solutions')->where('id', 1)->update(['raw_mark' => 20, 'mark' => 10, 'deadline_penalty_amount' => 10]);
        $this->waive()->assertRedirect();
        $this->waive()->assertRedirect();
        $this->assertDatabaseCount('coin_transactions', 1);
        $this->assertDatabaseHas('coin_transactions', ['user_id' => 2, 'price' => 3, 'comment' => 'Task #1']);
    }

    public function testFailedStatsUpdateRollsBackWaiver(): void
    {
        DB::unprepared("CREATE TRIGGER fail_stats BEFORE INSERT ON course_student_points BEGIN SELECT RAISE(ABORT, 'Test failure'); END;");
        $this->waive()->assertStatus(500);
        $this->assertSame(8, Solution::find(1)->mark);
        $this->assertNull(Solution::find(1)->deadline_penalty_waived_at);
        $this->assertDatabaseCount('course_activities', 0);
        $this->assertDatabaseCount('coin_transactions', 0);
    }
}
