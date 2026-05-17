<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseStudentPoints extends Model
{
    protected $table = 'course_student_points';

    protected $fillable = [
        'course_id', 'student_id', 'points', 'max_points', 'percent'
    ];

    public function course()
    {
        return $this->belongsTo('App\Course', 'course_id', 'id');
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

    private static function calculateStats(Course $course, User $student)
    {
        // XP includes all visible course tasks; percent tracks only the common required path.
        $points = 0;
        $max_points = 0;
        $progressPoints = 0;
        $progressMaxPoints = 0;
        $bestMarksByTask = $student->submissions
            ->groupBy('task_id')
            ->map(function ($submissions) {
                return (int) $submissions->max('mark');
            });

        foreach ($course->program->lessons as $lesson) {
            $isProgressLesson = self::isCommonProgressLesson($lesson, $course);

            foreach ($lesson->steps as $step) {
                foreach ($step->tasks as $task) {
                    $bestMark = $bestMarksByTask->get($task->id, 0);

                    if ($task->isVisible($student, $course)) {
                        $max_points += $task->max_mark;
                        $points += $bestMark;
                    }

                    if ($isProgressLesson && self::isCommonProgressTask($task)) {
                        $progressMaxPoints += $task->max_mark;
                        $progressPoints += $bestMark;
                    }
                }
            }
        }

        return [
            'points' => $points,
            'max_points' => $max_points,
            'percent' => $progressMaxPoints > 0 ? ($progressPoints * 100 / $progressMaxPoints) : 0,
        ];
    }

    /**
     * Recalculate points for a student in a course
     *
     * @param int $courseId
     * @param int $studentId
     * @return CourseStudentPoints
     */
    public static function recalculate($courseId, $studentId)
    {
        $course = Course::with([
            'program.lessons.info',
            'program.lessons.steps.tasks',
            'program.lessons.earlyAccesses' => function ($query) use ($courseId, $studentId) {
                $query->where('course_id', $courseId)->where('user_id', $studentId);
            },
            'students:id',
            'teachers:id',
        ])->findOrFail($courseId);
        $student = User::with([
            'submissions' => function ($query) use ($courseId) {
                $query->where('course_id', $courseId)
                    ->select('id', 'task_id', 'course_id', 'user_id', 'mark');
            },
        ])->findOrFail($studentId);

        $stats = self::calculateStats($course, $student);

        // Update or create the record
        return self::updateOrCreate(
            ['course_id' => $courseId, 'student_id' => $studentId],
            ['points' => $stats['points'], 'max_points' => $stats['max_points'], 'percent' => $stats['percent']]
        );
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
            'program.lessons.info',
            'program.lessons.steps.tasks',
            'program.lessons.earlyAccesses' => function ($query) use ($courseId, $studentIds) {
                $query->where('course_id', $courseId)->whereIn('user_id', $studentIds);
            },
            'students:id',
            'teachers:id',
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
            $stats = self::calculateStats($course, $student);

            $rows[] = [
                'course_id' => $course->id,
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
                ['course_id', 'student_id'],
                ['points', 'max_points', 'percent', 'updated_at']
            );
        }

        return collect($rows);
    }
}
