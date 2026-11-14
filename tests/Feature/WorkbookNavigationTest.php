<?php

namespace Tests\Feature;

use App\Course;
use App\Lesson;
use App\ProgramStep;
use App\Solution;
use App\Task;
use App\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkbookNavigationTest extends TestCase
{
    #[DataProvider('viewers')]
    public function test_trophies_identify_only_steps_with_tasks_visible_to_the_viewer(string $role, ?int $submissionCourse, bool $public, array $expected): void
    {
        RequestFacade::swap(Request::create($public ? '/open/steps/1' : '/insider/courses/133/steps/1'));
        $user = (new User())->forceFill(['id' => 1, 'role' => $role]);
        $user->setRelation('submissions', new Collection($submissionCourse ? [
            (new Solution())->forceFill(['task_id' => 30, 'course_id' => $submissionCourse, 'user_id' => 1]),
        ] : []));
        $course = (new Course())->forceFill(['id' => 133]);
        $course->setRelation('teachers', new Collection($role === 'teacher' ? [$user] : []));
        $lesson = (new Lesson())->forceFill(['id' => 10, 'chapter_id' => 20]);
        $steps = new Collection();
        foreach ([1 => [], 2 => [false], 3 => [true]] as $id => $hiddenFlags) {
            $step = (new ProgramStep())->forceFill(['id' => $id, 'name' => 'Ступень '.$id]);
            $step->setRelation('lesson', $lesson);
            $step->setRelation('tasks', new Collection(array_map(fn ($hidden) => (new Task())->forceFill([
                'id' => $id * 10, 'step_id' => $id, 'is_hidden' => $hidden,
            ]), $hiddenFlags)));
            $steps->push($step);
        }
        $lesson->setRelation('steps', $steps);
        $html = view('steps.partials.nav', ['step' => $steps->first(), 'user' => $user, 'course' => $public ? null : $course])->render();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[@class="gc-workbook-steps__link"]');
        $this->assertCount(3, $links);
        foreach ($links as $index => $link) {
            $markers = $xpath->query('.//span[@class="gc-workbook-steps__tasks"]', $link);
            $this->assertCount(in_array($index + 1, $expected, true) ? 1 : 0, $markers);
            if ($markers->length) {
                $this->assertStringContainsString('Есть задания', $markers->item(0)->textContent);
                $this->assertCount(1, $xpath->query('.//i[contains(@class,"fa-trophy")]', $markers->item(0)));
            }
        }
    }

    public static function viewers(): array
    {
        return [
            'student' => ['student', null, false, [2]],
            'student with an answered hidden task' => ['student', 133, false, [2, 3]],
            'a submission in another course does not reveal a task' => ['student', 134, false, [2]],
            'teacher' => ['teacher', null, false, [2, 3]],
            'admin' => ['admin', null, false, [2, 3]],
            'public lesson' => ['student', null, true, [2]],
            'public lesson opened by a teacher' => ['teacher', null, true, [2]],
        ];
    }
}
