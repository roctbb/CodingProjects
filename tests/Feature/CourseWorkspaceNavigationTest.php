<?php

namespace Tests\Feature;

use App\Course;
use App\Program;
use App\ProgramChapter;
use App\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CourseWorkspaceNavigationTest extends TestCase
{
    private function navigation(string $role, bool $assigned, string $active = 'program', bool $planner = true, mixed $chapter = null): \DOMXPath
    {
        config(['course_planner.enabled' => $planner]);
        $viewer = (new User())->forceFill(['id' => 7, 'name' => 'Viewer', 'role' => $role]);
        $this->actingAs($viewer);
        $course = (new Course())->forceFill(['id' => 133, 'name' => 'Python <script>test</script>', 'description' => 'Описание курса']);
        $course->setRelation('teachers', collect($assigned ? [$viewer] : []));
        $this->mock(\App\Services\CourseSubgroups::class)->shouldReceive('available')->andReturn(false);
        $this->mock(\App\Services\CourseAccess::class)->shouldReceive('manages')
            ->andReturn($role === 'admin' || ($role === 'teacher' && $assigned));
        $program = new Program();
        $program->setRelation('chapters', collect([(new ProgramChapter())->forceFill(['id' => 1]), (new ProgramChapter())->forceFill(['id' => 50])]));
        $course->setRelation('program', $program);
        $html = Blade::render('<x-gc-course-header :course="$course" :active="$active" :chapter="$chapter" />', compact('course', 'active', 'chapter'));
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($document);
    }

    public function test_every_course_destination_keeps_the_same_navigation_and_context(): void
    {
        $expected = ['/insider/courses/133', '/insider/courses/133/planner', '/insider/courses/133/assessments', '/insider/courses/133/report', '/insider/courses/133/deadlines'];
        foreach (['program', 'plan', 'journal', 'report', 'deadlines'] as $index => $active) {
            $xpath = $this->navigation('teacher', true, $active);
            $links = $xpath->query('//nav[@data-course-navigation]/a');
            $this->assertSame($expected, array_map(fn ($link) => parse_url($link->getAttribute('href'), PHP_URL_PATH), iterator_to_array($links)));
            $this->assertSame(1, $xpath->query('//nav/a[@aria-current="page"]')->length);
            $this->assertSame($expected[$index], parse_url($xpath->query('//nav/a[@aria-current="page"]')->item(0)->getAttribute('href'), PHP_URL_PATH));
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(0, $xpath->query('//script')->length);
        }
    }

    public function test_manager_destinations_follow_role_and_course_membership(): void
    {
        foreach ([['student', false, 3], ['student', true, 3], ['teacher', false, 2], ['teacher', true, 5], ['admin', false, 5]] as [$role, $assigned, $count]) {
            $this->assertSame($count, $this->navigation($role, $assigned)->query('//nav/a')->length);
        }
        $xpath = $this->navigation('teacher', true, 'journal', false);
        $this->assertSame(4, $xpath->query('//nav/a')->length);
        $this->assertSame(0, $xpath->query('//nav/a[contains(@href,"planner")]')->length);
    }

    public function test_course_actions_stay_in_the_header_without_duplicating_section_links(): void
    {
        foreach (['program', 'plan', 'journal', 'report', 'deadlines'] as $active) {
            $xpath = $this->navigation('teacher', true, $active);
            $this->assertSame(1, $xpath->query('//header//div[@data-course-actions]')->length);
            $paths = array_map(fn ($link) => parse_url($link->getAttribute('href'), PHP_URL_PATH), iterator_to_array($xpath->query('//*[@data-course-actions]//a')));
            $this->assertSame(['/insider/courses/133/subgroups', '/insider/courses/133/edit', '/insider/courses/133/notifications', '/insider/courses/133/export-md', '/insider/courses/133/blocked'], $paths);
        }
        foreach ([['student', false], ['student', true], ['teacher', false]] as [$role, $assigned]) {
            $this->assertSame(0, $this->navigation($role, $assigned)->query('//*[@data-course-actions]')->length);
        }
    }

    public function test_student_task_tab_preserves_chapter_context_and_is_not_a_teacher_tool(): void
    {
        $xpath = $this->navigation('student', false, 'tasks', chapter: 50);
        $link = $xpath->query('//nav[@data-course-navigation]/a[@aria-current="page"]')->item(0);
        $this->assertStringEndsWith('/insider/courses/133/chapter-tasks?chapter=50', $link->getAttribute('href'));
        $this->assertSame(0, $this->navigation('teacher', true)->query('//nav/a[contains(@href,"chapter-tasks")]')->length);
    }

    public function test_selected_chapter_is_preserved_in_every_section_but_foreign_ids_are_not(): void
    {
        foreach (['program', 'plan', 'journal', 'report', 'deadlines'] as $active) {
            $links = $this->navigation('teacher', true, $active, chapter: '50')->query('//nav[@data-course-navigation]/a');
            $this->assertSame(5, $links->length);
            foreach ($links as $link) $this->assertSame('chapter=50', parse_url($link->getAttribute('href'), PHP_URL_QUERY));
        }
        foreach (['all', '999', '0', '<script>'] as $invalid) {
            foreach ($this->navigation('teacher', true, chapter: $invalid)->query('//nav[@data-course-navigation]/a') as $link) {
                $this->assertNull(parse_url($link->getAttribute('href'), PHP_URL_QUERY));
            }
        }
    }
}
