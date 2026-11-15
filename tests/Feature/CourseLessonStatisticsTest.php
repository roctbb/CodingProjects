<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;

class CourseLessonStatisticsTest extends CourseCurrentLessonTest
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::unprepared('CREATE TABLE lesson_student_stats (id INTEGER PRIMARY KEY, course_id INTEGER, lesson_id INTEGER, student_id INTEGER, points REAL, max_points REAL, percent REAL);');
        for ($id = 10; $id < 78; $id++) {
            DB::table('users')->insert(['id' => $id, 'name' => sprintf('Ученик %03d', $id), 'role' => 'student', 'email_verified_at' => '2026-01-01']);
            DB::table('course_students')->insert(['course_id' => 1, 'user_id' => $id]);
        }
        DB::table('lesson_student_stats')->insert(['course_id' => 1, 'lesson_id' => 1, 'student_id' => 3, 'points' => 10, 'max_points' => 10, 'percent' => 100]);
    }

    public function test_large_class_statistics_are_paginated_and_keep_the_full_summary(): void
    {
        $response = $this->getJson('/insider/courses/1/lessons/1/statistics')->assertOk();
        $html = $response->json('html');
        $this->assertSame(25, substr_count($html, 'class="course-stat-row small"'));
        $this->assertStringContainsString('1–25 из 70', $html);
        $this->assertStringContainsString('1 / 70', $html);
        $this->assertStringContainsString('/insider/courses/1/report?chapter=1&amp;student=3&amp;lesson=1#student3', $html);
        $this->assertStringContainsString('отчёт по уроку', $html);
        $this->assertStringContainsString('course-stat-track', $html);
        $last = $this->getJson('/insider/courses/1/lessons/1/statistics?page=3')->assertOk()->json('html');
        $this->assertSame(20, substr_count($last, 'class="course-stat-row small"'));
        $this->assertStringContainsString('51–70 из 70', $last);
        $this->getJson('/insider/courses/1/lessons/1/statistics?page=0')->assertUnprocessable();
        $this->getJson('/insider/courses/1/lessons/1/statistics?page=4')->assertNotFound();
    }

    public function test_statistics_require_management_of_this_course_and_a_matching_lesson(): void
    {
        foreach ([3, 4] as $id) $this->actingAs(User::find($id))->getJson('/insider/courses/1/lessons/1/statistics')->assertForbidden();
        $this->actingAs(User::find(2))->getJson('/insider/courses/1/lessons/3/statistics')->assertNotFound();
        $this->actingAs(User::find(1))->getJson('/insider/courses/1/lessons/1/statistics')->assertOk();
    }

    public function test_preloaded_lesson_panels_render_the_first_page_without_queries_and_hide_non_roster_scores():void
    {
        DB::table('course_students')->where('course_id',1)->where('user_id',10)->update(['hidden_from_stats'=>true]);
        DB::table('course_students')->insert(['course_id'=>1,'user_id'=>2]);
        foreach([2,10] as $user)DB::table('lesson_student_stats')->insert(['course_id'=>1,'lesson_id'=>1,'student_id'=>$user,'points'=>999999,'max_points'=>999999,'percent'=>100]);
        $course=\App\Course::findOrFail(1);$students=$course->statsStudents()->get(['users.id','users.name']);
        $stats=\App\LessonStudentStats::where('course_id',1)->where('lesson_id',1)->get()->keyBy('student_id');
        $render=function($lesson,$stats)use($course,$students){return view('courses.partials.lesson-statistics-panel',compact('course','students','stats','lesson'))->render();};
        $first=\App\Lesson::findOrFail(1);$second=\App\Lesson::findOrFail(2);
        DB::enableQueryLog();DB::flushQueryLog();
        $html=$render($first,$stats).$render($second,collect());
        $queries=DB::getQueryLog();DB::disableQueryLog();
        $this->assertSame([],$queries);
        $this->assertSame(2,substr_count($html,'data-course-stats-loaded="true"'));
        $this->assertSame(50,substr_count($html,'class="course-stat-row small"'));
        $this->assertStringContainsString('id="marks1"',$html);$this->assertStringContainsString('id="marks2"',$html);
        $this->assertStringContainsString('1–25 из 69',$html);
        $this->assertStringContainsString('1 / 69',$html);$this->assertStringContainsString('10 / 10 XP',$html);
        $this->assertStringNotContainsString('999999',$html);$this->assertStringNotContainsString('Ученик 010',$html);
        $ajax=$this->getJson('/insider/courses/1/lessons/1/statistics')->assertOk()->json('html');
        $this->assertStringContainsString('1–25 из 69',$ajax);$this->assertStringNotContainsString('999999',$ajax);
    }
}
