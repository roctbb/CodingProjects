<?php

namespace Tests\Unit;

use App\Course;
use App\CourseStudentPoints;
use App\Lesson;
use App\LessonInfo;
use App\LessonStudentStats;
use App\Program;
use App\ProgramStep;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use ReflectionMethod;
use Tests\TestCase;

class CourseProgressCalculationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testCoursePercentUsesOnlyPointsAndMaximumFromOpenedLessons(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');

        $openRequiredTask = $this->task(1, 10);
        $openBonusTask = $this->task(2, 20, true);
        $futureRequiredTask = $this->task(3, 100);

        $course = $this->courseWithLessons([
            $this->lesson(1, '2026-08-18', [$openRequiredTask, $openBonusTask]),
            $this->lesson(2, '2026-08-19', [$futureRequiredTask]),
        ]);
        $student = $this->studentWithMarks([
            1 => 5,
            2 => 20,
            3 => 100,
        ]);

        $stats = $this->invokeStats(CourseStudentPoints::class, 'calculateStats', [$course, $student]);

        $this->assertSame(125, $stats['points']);
        $this->assertSame(130, $stats['max_points']);
        $this->assertEquals(50, $stats['percent']);
    }

    public function testLessonPercentExcludesBonusPointsFromProgress(): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');

        $requiredTask = $this->task(1, 10);
        $bonusTask = $this->task(2, 20, true);
        $lesson = $this->lesson(1, '2026-08-18', [$requiredTask, $bonusTask]);
        $course = $this->courseWithLessons([$lesson]);
        $student = $this->studentWithMarks([
            1 => 5,
            2 => 20,
        ]);

        $stats = $this->invokeStats(LessonStudentStats::class, 'calculateLessonStats', [$course, $lesson, $student]);

        $this->assertSame(25, $stats['points']);
        $this->assertSame(30, $stats['max_points']);
        $this->assertEquals(50, $stats['percent']);
    }

    private function courseWithLessons(array $lessons): Course
    {
        $program = new Program();
        $program->setRelation('lessons', new Collection($lessons));

        $course = new Course();
        $course->id = 1;
        $course->setRelation('program', $program);
        $course->setRelation('students', new Collection());
        $course->setRelation('teachers', new Collection());

        return $course;
    }

    private function lesson(int $id, string $startDate, array $tasks): Lesson
    {
        $step = new ProgramStep();
        $step->setRelation('tasks', new Collection($tasks));

        $info = new LessonInfo();
        $info->forceFill([
            'course_id' => 1,
            'lesson_id' => $id,
            'start_date' => $startDate,
        ]);

        $lesson = new Lesson();
        $lesson->id = $id;
        $lesson->setRelation('info', new Collection([$info]));
        $lesson->setRelation('steps', new Collection([$step]));

        return $lesson;
    }

    private function task(int $id, int $maxMark, bool $isStar = false): Task
    {
        $task = new Task();
        $task->id = $id;
        $task->max_mark = $maxMark;
        $task->is_star = $isStar;
        $task->is_hidden = false;

        return $task;
    }

    private function studentWithMarks(array $marksByTask): User
    {
        $submissions = collect($marksByTask)->map(function ($mark, $taskId) {
            $solution = new Solution();
            $solution->task_id = $taskId;
            $solution->course_id = 1;
            $solution->user_id = 1;
            $solution->mark = $mark;

            return $solution;
        })->values();

        $student = new User();
        $student->id = 1;
        $student->role = 'student';
        $student->setRelation('submissions', new Collection($submissions->all()));

        return $student;
    }

    private function invokeStats(string $class, string $method, array $arguments): array
    {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $arguments);
    }
}
