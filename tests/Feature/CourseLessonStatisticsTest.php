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
}
