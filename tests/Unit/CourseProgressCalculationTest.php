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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $futureBonusTask = $this->task(4, 30, true);

        $course = $this->courseWithLessons([
            $this->lesson(1, '2026-08-18', [$openRequiredTask, $openBonusTask]),
            $this->lesson(2, '2026-08-19', [$futureRequiredTask, $futureBonusTask]),
        ]);
        $student = $this->studentWithMarks([
            1 => 5,
            2 => 20,
            3 => 100,
            4 => 30,
        ]);

        $stats = $this->invokeStats(CourseStudentPoints::class, 'calculateStats', [$course, $student]);

        $this->assertSame(155, $stats['points']);
        $this->assertSame(160, $stats['max_points']);
        $this->assertEquals(250, $stats['percent']);
        $this->assertSame(25, $course->points($student));
        $this->assertSame(10, $course->max_points($student));
    }

    public function testLessonPercentIncludesBonusPointsWithoutIncreasingRequiredMaximum(): void
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
        $this->assertSame(10, $stats['max_points']);
        $this->assertEquals(250, $stats['percent']);
    }

    #[DataProvider('bonusProgressCases')]
    public function testBonusTasksIncreaseCourseAndLessonProgress(array $marks, int $expectedPercent): void
    {
        Carbon::setTestNow('2026-08-18 12:00:00');

        $hiddenTask = $this->task(3, 50, true);
        $hiddenTask->is_hidden = true;
        $lesson = $this->lesson(1, '2026-08-18', [
            $this->task(1, 100),
            $this->task(2, 20, true),
            $hiddenTask,
        ]);
        $course = $this->courseWithLessons([$lesson]);
        $student = $this->studentWithMarks($marks);

        $courseStats = $this->invokeStats(CourseStudentPoints::class, 'calculateStats', [$course, $student]);
        $lessonStats = $this->invokeStats(LessonStudentStats::class, 'calculateLessonStats', [$course, $lesson, $student]);

        $this->assertEquals($expectedPercent, $courseStats['percent']);
        $this->assertEquals($expectedPercent, $lessonStats['percent']);
        $this->assertSame(100, $lessonStats['max_points']);
        $this->assertEquals($expectedPercent, $course->points($student));
        $this->assertEquals(100, $course->max_points($student));
        $this->assertEquals(min(100, $expectedPercent), $course->getPercent($student));
    }

    public static function bonusProgressCases(): array
    {
        return [
            'unsolved bonus does not lower progress' => [[1 => 50], 50],
            'all required tasks earn maximum without bonus' => [[1 => 100], 100],
            'full bonus increases progress' => [[1 => 50, 2 => 20], 70],
            'partial bonus increases progress' => [[1 => 50, 2 => 10], 60],
            'bonus alone earns progress' => [[2 => 20], 20],
            'bonus can exceed required maximum' => [[1 => 100, 2 => 20], 120],
            'hidden bonus stays excluded' => [[1 => 50, 2 => 20, 3 => 50], 70],
        ];
    }

    private function courseWithLessons(array $lessons): Course
    {
        $program = new Program();
        $program->setRelation('lessons', new Collection($lessons));

        $course = new Course();
        $course->id = 1;
        $course->setRelation('program', $program);
        $course->setRelation('lessons', new Collection($lessons));
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
