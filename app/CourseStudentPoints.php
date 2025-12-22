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
        $course = Course::with('program.lessons', 'program.lessons.steps', 'program.lessons.steps.tasks')->findOrFail($courseId);
        $student = User::with('submissions')->findOrFail($studentId);

        $max_points = 0;
        $points = 0;

        // Get all steps for started lessons
        $all_steps = collect([]);
        foreach ($course->program->lessons as $lesson) {
            if ($lesson->isStarted($course)) {
                $all_steps = $all_steps->merge($lesson->steps);
            }
        }

        // Calculate points
        foreach ($all_steps as $step) {
            foreach ($step->tasks as $task) {
                if (!$task->is_star) {
                    $max_points += $task->max_mark;
                }
                $points += $student->submissions->where('task_id', $task->id)->max('mark');
            }
        }

        $percent = $max_points > 0 ? min(100, $points * 100 / $max_points) : 0;

        // Update or create the record
        return self::updateOrCreate(
            ['course_id' => $courseId, 'student_id' => $studentId],
            ['points' => $points, 'max_points' => $max_points, 'percent' => $percent]
        );
    }
}
