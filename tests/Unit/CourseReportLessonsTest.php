<?php

namespace Tests\Unit;

use App\Course;
use App\Lesson;
use App\LessonInfo;
use App\Program;
use App\ProgramChapter;
use App\ProgramStep;
use App\Support\CourseReportLessons;
use App\Task;
use Tests\TestCase;

class CourseReportLessonsTest extends TestCase
{
    private function lesson(int $id, ?int $chapter, bool $tasks, bool $started = true): Lesson
    {
        $lesson = (new Lesson())->forceFill(['id' => $id, 'program_id' => 1, 'chapter_id' => $chapter, 'sort_index' => $id]);
        $step = new ProgramStep();
        $step->setRelation('tasks', collect($tasks ? [(new Task())->forceFill(['max_mark' => 0])] : []));
        $lesson->setRelation('steps', collect([$step]));
        $lesson->setRelation('info', collect([(new LessonInfo())->forceFill([
            'course_id' => 1, 'start_date' => $started ? now()->subDay() : now()->addMonth(),
        ])]));
        return $lesson;
    }

    private function course($lessons): Course
    {
        $program = new Program();
        $program->setRelation('lessons', collect($lessons));
        $program->setRelation('chapters', collect([
            (new ProgramChapter())->forceFill(['id' => 1]), (new ProgramChapter())->forceFill(['id' => 2]),
        ]));
        $course = (new Course())->forceFill(['id' => 1]);
        $course->setRelation('program', $program);
        return $course;
    }

    public function test_report_excludes_theory_only_lessons_and_empty_chapters_but_keeps_unscored_tasks(): void
    {
        $course = $this->course([
            $this->lesson(1, 1, false), $this->lesson(3, 1, true), $this->lesson(2, 1, true),
            $this->lesson(4, 2, false), $this->lesson(5, 2, true, false),
        ]);
        [$lessons, $groups] = CourseReportLessons::groups($course);
        $this->assertSame([2, 3], $lessons->pluck('id')->all());
        $this->assertSame([1], $groups->pluck('chapter.id')->all());
        $this->assertCount(5, $course->program->lessons, 'Theory materials remain available to the program and activity report.');
    }

    public function test_uncategorized_lessons_follow_the_same_task_and_opening_rules(): void
    {
        $course = $this->course([
            $this->lesson(1, null, false), $this->lesson(2, 99, false), $this->lesson(3, null, true),
            $this->lesson(4, 99, true), $this->lesson(5, null, true, false),
        ]);
        [$lessons, $groups] = CourseReportLessons::groups($course);
        $this->assertSame([3, 4], $lessons->pluck('id')->all());
        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['chapter']);
    }

    public function test_course_with_only_theory_has_no_empty_report_spoilers(): void
    {
        [$lessons, $groups] = CourseReportLessons::groups($this->course([
            $this->lesson(1, 1, false), $this->lesson(2, 2, false), $this->lesson(3, null, false),
        ]));
        $this->assertTrue($lessons->isEmpty());
        $this->assertTrue($groups->isEmpty());
    }
}
