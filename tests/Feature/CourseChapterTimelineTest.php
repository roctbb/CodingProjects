<?php

namespace Tests\Feature;

use App\Course;
use App\Program;
use App\ProgramChapter;
use App\Support\CourseChapterNavigation;
use App\User;
use Illuminate\Support\Facades\Blade;
use Mockery;
use Tests\TestCase;

class CourseChapterTimelineTest extends TestCase
{
    private function fixture(array $access = [true, true, false], string $role = 'student'): array
    {
        $viewer = (new User())->forceFill(['id' => 10, 'role' => $role]);
        $course = (new Course())->forceFill(['id' => 133, 'name' => 'Python']);
        $course->setRelation('teachers', collect($role === 'teacher' ? [$viewer] : []));
        $chapters = collect();
        foreach ($access as $index => $available) {
            $chapter = Mockery::mock(ProgramChapter::class)->makePartial();
            $chapter->id = 100 - $index * 7;
            $chapter->name = 'Глава '.$index;
            if (in_array($role, ['teacher', 'admin'])) {
                $chapter->shouldNotReceive('isAvailableForUser');
            } else {
                $chapter->shouldReceive('isAvailableForUser')->with($course, $viewer)->andReturn($available);
            }
            $chapter->shouldNotReceive('isStarted');
            $chapters->push($chapter);
        }
        $program = new Program();
        $program->setRelation('chapters', $chapters);
        $course->setRelation('program', $program);

        return [$course, $viewer, $chapters];
    }

    private function render(Course $course, User $viewer, ProgramChapter $chapter): string
    {
        return Blade::render('<x-gc-chapter-timeline :course="$course" :chapter="$chapter" :viewer="$viewer" />', compact('course', 'viewer', 'chapter'));
    }

    public function test_positions_follow_program_order_and_urls_use_chapter_ids(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([true, true, true]);
        $navigation = CourseChapterNavigation::forCourse($course, $chapters[1], $viewer);
        $this->assertSame(1, $navigation['selected']);
        $this->assertSame([100, 93, 86], array_column($navigation['items'], 'id'));
        $this->assertStringEndsWith('?chapter=100', $navigation['previous']['url']);
        $this->assertStringEndsWith('?chapter=86', $navigation['next']['url']);
    }

    public function test_closed_chapters_have_no_target_and_neighbor_links_skip_gaps(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([true, false, true, false]);
        $navigation = CourseChapterNavigation::forCourse($course, $chapters[2], $viewer);
        $this->assertNull($navigation['items'][1]['url']);
        $this->assertNull($navigation['items'][3]['url']);
        $this->assertSame(100, $navigation['previous']['id']);
        $this->assertNull($navigation['next']);
        $this->assertSame(2, $navigation['availableCount']);
        $document = new \DOMDocument();
        @$document->loadHTML($this->render($course, $viewer, $chapters[2]));
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//nav[@data-chapter-list]//a')->length);
        $this->assertSame(2, $xpath->query('//nav[@data-chapter-list]//*[@aria-disabled="true"]')->length);
        $this->assertSame(0, $xpath->query('//nav[@data-chapter-list]//a[contains(@href,"chapter=93")]')->length);

    }

    public function test_managers_can_select_unopened_chapters(): void
    {
        foreach (['teacher', 'admin'] as $role) {
            [$course, $viewer, $chapters] = $this->fixture([false, false, false], $role);
            $navigation = CourseChapterNavigation::forCourse($course, $chapters[0], $viewer);
            $this->assertSame(3, $navigation['availableCount']);
            $this->assertSame(93, $navigation['next']['id']);
        }
    }

    public function test_timeline_uses_actual_availability_including_personal_early_access(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([false, true, false]);
        $navigation = CourseChapterNavigation::forCourse($course, $chapters[0], $viewer);
        $this->assertNotNull($navigation['items'][1]['url']);
        $this->assertSame(93, $navigation['next']['id']);
        $this->assertNull($navigation['items'][2]['url']);
        $this->assertTrue($navigation['hasAlternatives']);
        $document = new \DOMDocument();
        @$document->loadHTML($this->render($course, $viewer, $chapters[0]));
        $this->assertFalse($document->getElementsByTagName('input')->item(0)->hasAttribute('disabled'));
    }

    public function test_teacher_studying_in_someone_elses_course_does_not_get_manager_navigation(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([true, false]);
        $viewer->role = 'teacher';
        $navigation = CourseChapterNavigation::forCourse($course, $chapters[0], $viewer);
        $this->assertSame(1, $navigation['availableCount']);
        $this->assertNull($navigation['items'][1]['url']);
        $this->assertNull($navigation['next']);
    }

    public function test_only_current_name_is_visible_and_serialized_titles_are_safe(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([true, true, true]);
        $chapters[2]->name = '</script><img src=x onerror=alert(1)>';
        $html = $this->render($course, $viewer, $chapters[1]);
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame('Глава 1', $xpath->query('//*[@data-chapter-title]')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//*[@data-chapter-title]')->length);
        $this->assertSame(0, $xpath->query('//img')->length);
        $this->assertSame(1, $xpath->query('//script')->length);
        $payload = json_decode($xpath->query('//script')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($chapters[2]->name, $payload['items'][2]['name']);
        $this->assertStringContainsString('name="chapter"', $html);
        $this->assertStringNotContainsString('course-chapter-switcher', $html);
    }

    public function test_single_last_and_closed_only_states_are_explicit(): void
    {
        [$course, $viewer, $chapters] = $this->fixture([true]);
        $single = $this->render($course, $viewer, $chapters[0]);
        $this->assertStringContainsString('Глава 1 из 1', $single);
        $this->assertStringNotContainsString('type="range"', $single);
        [$course, $viewer, $chapters] = $this->fixture([true, true]);
        $this->assertStringContainsString('Глава 2 из 2', $this->render($course, $viewer, $chapters[1]));
        [$course, $viewer, $chapters] = $this->fixture([false, false]);
        $closed = $this->render($course, $viewer, $chapters[0]);
        $this->assertStringContainsString('Главы пока не открыты.', $closed);
        $this->assertStringContainsString('disabled', $closed);
    }
}
