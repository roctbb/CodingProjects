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

        $max_points = 0;
        $points = 0;

        // Get all steps for lessons available to this student
        $all_steps = collect([]);
        foreach ($course->program->lessons as $lesson) {
            if ($lesson->isAvailableForUser($course, $student)) {
                $all_steps = $all_steps->merge($lesson->steps);
            }
        }

        // Calculate points
        foreach ($all_steps as $step) {
            foreach ($step->tasks as $task) {
                if (!$task->isVisible($student, $course)) continue;
                if (!$task->is_star) {
                    $max_points += $task->max_mark;
                }
                $points += (int) $student->submissions->where('task_id', $task->id)->max('mark');
            }
        }

        $percent = $max_points > 0 ? min(100, $points * 100 / $max_points) : 0;

        // Update or create the record
        return self::updateOrCreate(
            ['course_id' => $courseId, 'student_id' => $studentId],
            ['points' => $points, 'max_points' => $max_points, 'percent' => $percent]
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
            $max_points = 0;
            $points = 0;

            foreach ($course->program->lessons as $lesson) {
                if (!$lesson->isAvailableForUser($course, $student)) {
                    continue;
                }

                foreach ($lesson->steps as $step) {
                    foreach ($step->tasks as $task) {
                        if (!$task->isVisible($student, $course)) continue;
                        if (!$task->is_star) {
                            $max_points += $task->max_mark;
                        }
                        $points += (int) $student->submissions->where('task_id', $task->id)->max('mark');
                    }
                }
            }

            $rows[] = [
                'course_id' => $course->id,
                'student_id' => $student->id,
                'points' => $points,
                'max_points' => $max_points,
                'percent' => $max_points > 0 ? min(100, $points * 100 / $max_points) : 0,
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
