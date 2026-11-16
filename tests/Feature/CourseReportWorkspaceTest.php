<?php

namespace Tests\Feature;

use App\Services\CourseReportWorkspace;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class CourseReportWorkspaceTest extends CourseCurrentLessonTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        DB::unprepared(<<<'SQL'
            ALTER TABLE courses ADD COLUMN state TEXT DEFAULT 'started';
            ALTER TABLE tasks ADD COLUMN name TEXT DEFAULT 'Task';
            ALTER TABLE tasks ADD COLUMN max_mark INTEGER DEFAULT 10;
            CREATE TABLE solutions (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER,
                submitted TEXT, checked TEXT, created_at TEXT, mark INTEGER, text TEXT, comment TEXT,
                review_skipped INTEGER DEFAULT 0, recheck_requested INTEGER DEFAULT 0,
                geekpaste_integrity_synced_at TEXT, geekpaste_llm_probability INTEGER, geekpaste_integrity_dismissed_at TEXT);
            CREATE TABLE blocked_tasks (id INTEGER PRIMARY KEY, course_id INTEGER, task_id INTEGER, user_id INTEGER);
            CREATE TABLE course_student_points (id INTEGER PRIMARY KEY, course_id INTEGER, student_id INTEGER, points REAL, max_points REAL, percent REAL);
            CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, student_id INTEGER, points REAL, max_points REAL, percent REAL);
            CREATE TABLE action_log (id INTEGER PRIMARY KEY, user_id INTEGER, type TEXT, object_id INTEGER, created_at TEXT);
            INSERT INTO tasks (id,step_id,sort_index) VALUES (1,1,1),(2,2,1);
            INSERT INTO solutions (id,course_id,task_id,user_id,submitted,created_at,mark,text,comment,geekpaste_integrity_synced_at,geekpaste_llm_probability)
                VALUES (1,1,1,3,'2026-11-11','2026-11-11',5,'private answer','private feedback','2026-11-11',85),
                (2,1,1,4,'2026-11-11','2026-11-11',NULL,'other answer','other feedback',NULL,NULL),
                (3,2,1,3,'2026-11-11','2026-11-11',999,'foreign answer',NULL,'2026-11-11',100);
            INSERT INTO course_student_points (course_id,student_id,points,max_points,percent) VALUES (1,3,5,20,25),(1,4,0,20,0);
            INSERT INTO lesson_student_stats (course_id,lesson_id,student_id,points,max_points,percent)
                VALUES (1,1,3,5,10,50),(1,2,3,0,10,0),(1,1,4,0,10,0),(1,2,4,0,10,0);
            SQL);
    }

    private function data(array $query = []): array
    {
        if (!DB::table('lesson_info')->exists()) DB::table('lesson_info')->insert([
            ['course_id' => 1, 'lesson_id' => 1, 'start_date' => '2025-01-01'],
            ['course_id' => 1, 'lesson_id' => 2, 'start_date' => '2025-01-01'],
        ]);
        return app(CourseReportWorkspace::class)->data(1, User::find(2), Request::create('/insider/courses/1/report', 'GET', $query));
    }

    public function test_only_selected_student_attempts_and_lesson_stats_reach_the_detail_view(): void
    {
        $data = $this->data(['student' => 4]);
        $this->assertSame(4, $data['selectedStudent']->id);
        $this->assertSame([2], $data['lessons']->first()->steps->first()->tasks->first()->solutions->pluck('id')->all());
        $this->assertSame([4], $data['lessonStats']->first()->keys()->all());
        $this->assertSame(1, $data['students']->firstWhere('id', 4)->pending_count);
        $this->assertSame('high', $data['geekPasteIntegrityStats'][3]['overall']['risk_level']);
        $this->assertSame(85, $data['geekPasteIntegrityStats'][3]['overall']['max_llm_probability']);
        $this->assertSame(['students' => 2, 'attention' => 2, 'pending' => 1], $data['reportSummary']);
        $serialized = serialize($data['lessons']);
        $this->assertStringNotContainsString('other answer', $serialized);
        $this->assertStringNotContainsString('other feedback', $serialized);
        $this->assertStringNotContainsString('foreign answer', $serialized);
    }

    public function test_integrity_keeps_dismissals_and_ignores_whitespace_sources_without_loading_text(): void
    {
        DB::table('solutions')->where('id', 1)->update(['text' => " \r\n\t"]);
        $this->assertSame('none', $this->data()['geekPasteIntegrityStats'][3]['overall']['risk_level']);
        DB::table('solutions')->where('id', 1)->update(['text' => 'real answer', 'geekpaste_integrity_dismissed_at' => now()]);
        $stats = $this->data()['geekPasteIntegrityStats'][3]['overall'];
        $this->assertSame('low', $stats['risk_level']);
        $this->assertSame(1, $stats['dismissed']);
    }

    public function test_hidden_students_and_foreign_students_cannot_be_requested(): void
    {
        DB::table('course_students')->where('user_id', 4)->update(['hidden_from_stats' => 1]);
        foreach ([4, 999] as $id) {
            $this->getJson('/insider/courses/1/report?report_detail=1&student='.$id)->assertNotFound();
        }
    }

    public function test_detail_endpoint_enforces_course_management_and_input_shape(): void
    {
        foreach ([3, 4] as $id) $this->actingAs(User::find($id))->getJson('/insider/courses/1/report?report_detail=1&student=3')->assertForbidden();
        $this->actingAs(User::find(2))->getJson('/insider/courses/1/report?student[]=3')->assertUnprocessable();
    }

    public function test_detail_response_contains_one_student_and_links_to_their_attempts(): void
    {
        $this->data();
        $response = $this->getJson('/insider/courses/1/report?report_detail=1&student=3')->assertOk()->assertJsonPath('student', 3);
        $html = $response->json('html');
        $this->assertSame(1, substr_count($html, 'id="cardbody'));
        $this->assertStringContainsString('/tasks/1/student/3', $html);
        $this->assertStringNotContainsString('private answer', $html);
        $this->assertStringNotContainsString('private feedback', $html);
        $this->assertStringNotContainsString('cardbody4', $html);
    }

    public function test_attention_filter_has_a_useful_empty_state_and_details_ignore_the_roster_filter(): void
    {
        DB::table('course_student_points')->update(['points' => 20, 'percent' => 100]);
        DB::table('solutions')->update(['geekpaste_llm_probability' => null, 'geekpaste_integrity_synced_at' => null]);
        $this->assertNull($this->data(['attention' => '1'])['selectedStudent']);
        $this->assertSame(3, $this->data(['student' => 3, 'attention' => '1', 'report_detail' => '1'])['selectedStudent']->id);
    }
}
