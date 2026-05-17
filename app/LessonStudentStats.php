<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LessonStudentStats extends Model
{
    protected $table = 'lesson_student_stats';

    protected $fillable = [
        'course_id', 'lesson_id', 'student_id', 'points', 'max_points', 'percent'
    ];

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
    }

    public function lesson()
    {
        return $this->belongsTo('App\Lesson', 'lesson_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo('App\User', 'student_id', 'id');
    }

    private static function isCommonProgressLesson(Lesson $lesson, Course $course)
    {
        return $lesson->isStarted($course);
    }

    private static function isCommonProgressTask(Task $task)
    {
        return !$task->is_star && !$task->is_hidden;
    }

    /**
     * Recalculate stats for a student in a specific lesson within a course
     *
     * @param int $courseId
     * @param int $lessonId
     * @param int $studentId
     * @return LessonStudentStats
     */
    public static function recalculate($courseId, $lessonId, $studentId)
    {
        $lesson = Lesson::with('steps.tasks')->findOrFail($lessonId);
        $student = User::with([
            'submissions' => function ($query) use ($courseId) {
                $query->where('course_id', $courseId)
                    ->select('id', 'task_id', 'course_id', 'user_id', 'mark');
            },
        ])->findOrFail($studentId);
        $course = Course::with('students:id', 'teachers:id')->findOrFail($courseId);

        return self::updateLessonStats($course, $lesson, $student);
    }

    protected static function updateLessonStats(Course $course, Lesson $lesson, User $student)
    {
        $stats = self::calculateLessonStats($course, $lesson, $student);

        // Update or create the record
        return self::updateOrCreate(
            ['course_id' => $course->id, 'lesson_id' => $lesson->id, 'student_id' => $student->id],
            ['points' => $stats['points'], 'max_points' => $stats['max_points'], 'percent' => $stats['percent']]
        );
    }

    protected static function calculateLessonStats(Course $course, Lesson $lesson, User $student)
    {
        // XP includes all visible lesson tasks; percent compares earned XP with the common required path.
        $points = 0;
        $max_points = 0;
        $progressPoints = 0;
        $progressMaxPoints = 0;
        $bestMarksByTask = $student->submissions
            ->groupBy('task_id')
            ->map(function ($submissions) {
                return (int) $submissions->max('mark');
            });

        if (!self::isCommonProgressLesson($lesson, $course)) {
            return [
                'points' => 0,
                'max_points' => 0,
                'percent' => 0,
            ];
        }

        foreach ($lesson->steps as $step) {
            foreach ($step->tasks as $task) {
                $bestMark = $bestMarksByTask->get($task->id, 0);

                if ($task->isVisible($student, $course)) {
                    $max_points += $task->max_mark;
                    $points += $bestMark;
                }

                if (self::isCommonProgressTask($task)) {
                    $progressMaxPoints += $task->max_mark;
                    $progressPoints += $bestMark;
                }
            }
        }

        return [
            'points' => $points,
            'max_points' => $max_points,
            'percent' => $progressMaxPoints > 0 ? ($points * 100 / $progressMaxPoints) : 100,
        ];
    }

    /**
     * Recalculate stats for all lessons in a course for a specific student
     *
     * @param int $courseId
     * @param int $studentId
     */
    public static function recalculateForStudent($courseId, $studentId)
    {
        $course = Course::with([
            'teachers:id',
            'students:id',
            'program.lessons.info',
            'program.lessons.steps.tasks',
            'program.lessons.earlyAccesses' => function ($query) use ($courseId, $studentId) {
                $query->where('course_id', $courseId)->where('user_id', $studentId);
            },
        ])->findOrFail($courseId);
        $student = User::with([
            'submissions' => function ($query) use ($courseId) {
                $query->where('course_id', $courseId)
                    ->select('id', 'task_id', 'course_id', 'user_id', 'mark');
            },
        ])->findOrFail($studentId);

        $rows = [];
        $now = now();

        foreach ($course->program->lessons as $lesson) {
            $stats = self::calculateLessonStats($course, $lesson, $student);
            $rows[] = [
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
                'student_id' => $student->id,
                'points' => $stats['points'],
                'max_points' => $stats['max_points'],
                'percent' => $stats['percent'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            self::upsert(
                $rows,
                ['course_id', 'lesson_id', 'student_id'],
                ['points', 'max_points', 'percent', 'updated_at']
            );
        }
    }

    public static function recalculateForStudents($courseId, $studentIds = null)
    {
        $studentIds = collect($studentIds === null ? Course::findOrFail($courseId)->students()->pluck('users.id') : $studentIds)
            ->filter()
            ->unique()
            ->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        $course = Course::with([
            'teachers:id',
            'students:id',
            'program.lessons.info',
            'program.lessons.steps.tasks',
            'program.lessons.earlyAccesses' => function ($query) use ($courseId, $studentIds) {
                $query->where('course_id', $courseId)->whereIn('user_id', $studentIds);
            },
        ])->findOrFail($courseId);

        $students = User::with([
            'submissions' => function ($query) use ($courseId) {
                $query->where('course_id', $courseId)
                    ->select('id', 'task_id', 'course_id', 'user_id', 'mark');
            },
        ])->whereIn('id', $studentIds)->get();

        $rows = [];
        $now = now();

        foreach ($students as $student) {
            foreach ($course->program->lessons as $lesson) {
                $stats = self::calculateLessonStats($course, $lesson, $student);
                $rows[] = [
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'student_id' => $student->id,
                    'points' => $stats['points'],
                    'max_points' => $stats['max_points'],
                    'percent' => $stats['percent'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rows)) {
            self::upsert(
                $rows,
                ['course_id', 'lesson_id', 'student_id'],
                ['points', 'max_points', 'percent', 'updated_at']
            );
        }

        return collect($rows);
    }
}
