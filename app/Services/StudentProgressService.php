<?php

namespace App\Services;

use App\CourseStudentPoints;
use App\Jobs\RecalculateCourseStudentPoints;
use App\LessonStudentStats;

class StudentProgressService
{
    public function dispatchCourseStudentsRecalculation($courseId, $students): void
    {
        foreach ($students as $student) {
            RecalculateCourseStudentPoints::dispatch($courseId, $student->id);
        }
    }

    public function recalculateStudentProgress($courseId, $studentId): void
    {
        CourseStudentPoints::recalculate($courseId, $studentId);
        LessonStudentStats::recalculateForStudent($courseId, $studentId);
    }

    public function recalculateCoursePoints($courseId, $studentId): void
    {
        CourseStudentPoints::recalculate($courseId, $studentId);
    }
}
