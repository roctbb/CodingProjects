<?php

namespace Tests\Feature;

use App\CompletedCourse;
use App\Course;
use App\User;
use Tests\TestCase;

class ProfileCourseGalleryTest extends TestCase
{
    public function test_all_courses_and_grades_are_visible_without_disclosures_and_links_keep_membership_rules(): void
    {
        $user = (new User())->forceFill(['id' => 1, 'role' => 'student']);
        $guest = (new User())->forceFill(['id' => 2, 'role' => 'student']);
        $courses = collect(range(1, 8))->map(function ($id) use ($guest) {
            return (new Course())->forceFill(['id' => $id, 'name' => 'Курс '.$id])
                ->setRelation('students', collect($id === 1 ? [$guest] : []))->setRelation('teachers', collect());
        });
        $completedCourses = collect([
            (new CompletedCourse())->forceFill(['id' => 1, 'user_id' => 1, 'course_id' => 1, 'name' => 'Завершённый курс', 'mark' => '9'])->setRelation('course', $courses[0]),
            (new CompletedCourse())->forceFill(['id' => 2, 'user_id' => 1, 'name' => 'Внешний курс <script>alert(1)</script>', 'mark' => null])->setRelation('course', null),
        ]);
        $html = view('profile.partials.courses', ['user'=>$user, 'guest'=>$guest, 'managedCourses'=>collect(), 'startedCourses'=>$courses, 'completedCourses'=>$completedCourses])->render();
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//details')->length);
        $this->assertSame(0, $xpath->query('//script')->length);
        foreach (range(1, 8) as $id) $this->assertStringContainsString('Курс '.$id, $html);
        $this->assertStringContainsString('Отметка: 9', $html);
        $this->assertSame(2, $xpath->query('//a[contains(@href,"/courses/1")]')->length);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"/courses/2")]')->length);
    }
}
