<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProgramChapter extends Model
{
    public function program()
    {
        return $this->belongsTo('App\Program', 'program_id', 'id');
    }

    public function lessons()
    {
        return $this->hasMany('App\Lesson', 'chapter_id', 'id')->orderBy('sort_index')->orderBy('id');
    }

    public function isAvailable($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $this->isAvailableForUser($course, $user);
    }

    public function isAvailableForUser($course, $user)
    {
        foreach ($course->program->lessons->where('chapter_id', $this->id) as $lesson) {
            if ($lesson->isAvailableForUser($course, $user)) return true;
        }
        return false;
    }

    public function isDone($course)
    {
        $user = User::findOrFail(\Auth::User()->id);
        return $this->isDoneByUser($course, $user);
    }

    public function isDoneByUser($course, $user)
    {
        return $this->getStudentPercent($course, $user) >= 100;
    }

    public function isStarted($course)
    {
        if ($course->program->lessons->where('chapter_id', $this->id)->count() == 0) return false;
        foreach ($course->program->lessons->where('chapter_id', $this->id) as $lesson) {
            if ($lesson->isStarted($course)) return true;
        }
        return false;
    }

    public function getStudentsPercent($course)
    {
        $lessonIds = $this->startedLessonIdsForCourse($course);
        $studentIds = $course->relationLoaded('students')
            ? $course->students->pluck('id')
            : $course->students()->pluck('users.id');

        if ($lessonIds->isEmpty() || $studentIds->isEmpty()) {
            return 0;
        }

        $stats = LessonStudentStats::where('course_id', $course->id)
            ->whereIn('lesson_id', $lessonIds)
            ->whereIn('student_id', $studentIds)
            ->get(['student_id', 'percent'])
            ->groupBy('student_id');

        $done = 0;
        foreach ($studentIds as $studentId) {
            $studentStats = $stats->get($studentId, collect());
            if ($studentStats->count() > 0 && $studentStats->min('percent') >= 100) {
                $done++;
            }
        }

        return $done * 100 / max($studentIds->count(), 1);
    }

    public function getStudentPercent($course, $student)
    {
        $lessonIds = $this->startedLessonIdsForCourse($course);

        if ($lessonIds->isEmpty()) {
            return 0;
        }

        $stats = LessonStudentStats::where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get(['percent']);

        if ($stats->isNotEmpty()) {
            return min(100, $stats->avg('percent'));
        }

        return 0;
    }

    private function lessonIdsForCourse($course)
    {
        if ($course->relationLoaded('program') && $course->program && $course->program->relationLoaded('lessons')) {
            return $course->program->lessons
                ->where('chapter_id', $this->id)
                ->pluck('id')
                ->values();
        }

        return $this->lessons()->pluck('id');
    }

    private function startedLessonIdsForCourse($course)
    {
        if ($course->relationLoaded('program') && $course->program && $course->program->relationLoaded('lessons')) {
            return $course->program->lessons
                ->where('chapter_id', $this->id)
                ->filter(function ($lesson) use ($course) {
                    return $lesson->isStarted($course);
                })
                ->pluck('id')
                ->values();
        }

        return $this->lessons()
            ->with('info')
            ->get()
            ->filter(function ($lesson) use ($course) {
                return $lesson->isStarted($course);
            })
            ->pluck('id')
            ->values();
    }
}
