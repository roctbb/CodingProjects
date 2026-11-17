<?php

namespace Tests\Feature;

use App\Course;
use App\User;
use Tests\Support\DeadlineFeatureCase;

class CourseLeaderboardVisibilityTest extends DeadlineFeatureCase
{
    public function test_teacher_can_hide_and_restore_the_leaderboard_for_students(): void
    {
        (require database_path('migrations/2026_11_17_130000_add_hide_leaderboard_to_courses_table.php'))->up();
        $course = Course::findOrFail(1);
        $student = User::findOrFail(1);
        $teacher = User::findOrFail(2);
        $settings = ['name' => $course->name, 'description' => $course->description, 'students' => [1, 3, 4]];

        $this->actingAs($student)->get('/insider/courses/1')->assertOk()->assertSee('id="course-leaderboard"', false);
        $this->post('/insider/courses/1/edit', $settings + ['hide_leaderboard' => '1'])->assertForbidden();

        $this->actingAs($teacher)->get('/insider/courses/1/edit')->assertOk()->assertSee('Скрыть рейтинг от учеников');
        $this->post('/insider/courses/1/edit', $settings + ['hide_leaderboard' => '1'])->assertRedirect('/insider/courses/1');
        $this->post('/insider/courses/1/edit', $settings)->assertRedirect('/insider/courses/1');
        $this->assertTrue($course->refresh()->hide_leaderboard);
        foreach ([$teacher, User::findOrFail(5)] as $manager) {
            $this->actingAs($manager)->get('/insider/courses/1')->assertOk()
                ->assertSee('id="course-leaderboard"', false)->assertSee('Скрыт от учеников')
                ->assertSee('1 место')->assertSee('id="course-histogram"', false);
        }

        $this->actingAs($student)->get('/insider/courses/1')->assertOk()
            ->assertDontSee('id="course-leaderboard"', false)->assertDontSee('Скрыт от учеников')
            ->assertDontSee('gc-person-row__position')->assertSee('Баллы за главу');
        $this->get('/insider/courses/2')->assertOk()->assertSee('id="course-leaderboard"', false);

        $this->actingAs($teacher)->post('/insider/courses/1/edit', $settings + ['hide_leaderboard' => '0'])
            ->assertRedirect('/insider/courses/1');
        $this->get('/insider/courses/1')->assertOk()
            ->assertSee('id="course-leaderboard"', false)->assertDontSee('Скрыт от учеников');
        $this->actingAs($student)->get('/insider/courses/1')->assertOk()->assertSee('id="course-leaderboard"', false);
    }
}
