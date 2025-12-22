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
        $lesson = Lesson::with('steps', 'steps.tasks')->findOrFail($lessonId);
        $student = User::with('submissions')->findOrFail($studentId);

        $points = 0;
        $max_points = 0;

        // Calculate points for this lesson
        foreach ($lesson->steps as $step) {
            foreach ($step->tasks as $task) {
                if (!$task->is_star) {
                    $max_points += $task->max_mark;
                }
                $points += $student->submissions->where('task_id', $task->id)->max('mark');
            }
        }

        $percent = $max_points > 0 ? ($points * 100 / $max_points) : 100;

        // Update or create the record
        return self::updateOrCreate(
            ['course_id' => $courseId, 'lesson_id' => $lessonId, 'student_id' => $studentId],
            ['points' => $points, 'max_points' => $max_points, 'percent' => $percent]
        );
    }

    /**
     * Recalculate stats for all lessons in a course for a specific student
     *
     * @param int $courseId
     * @param int $studentId
     */
    public static function recalculateForStudent($courseId, $studentId)
    {
        $course = Course::with('program.lessons')->findOrFail($courseId);

        foreach ($course->program->lessons as $lesson) {
            if ($lesson->isStarted($course)) {
                self::recalculate($courseId, $lesson->id, $studentId);
            }
        }
    }
}
