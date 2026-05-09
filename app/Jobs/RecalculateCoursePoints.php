<?php

namespace App\Jobs;

use App\CourseStudentPoints;
use App\LessonStudentStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateCoursePoints implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $courseId;
    protected $studentIds;

    public function __construct($courseId, $studentIds = null)
    {
        $this->courseId = $courseId;
        $this->studentIds = $studentIds;
    }

    public function handle()
    {
        CourseStudentPoints::recalculateForStudents($this->courseId, $this->studentIds);
        LessonStudentStats::recalculateForStudents($this->courseId, $this->studentIds);
    }
}
