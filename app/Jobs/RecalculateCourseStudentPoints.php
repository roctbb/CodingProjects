<?php

namespace App\Jobs;

use App\CourseStudentPoints;
use App\LessonStudentStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateCourseStudentPoints implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $courseId;
    protected $studentId;

    /**
     * Create a new job instance.
     *
     * @param int $courseId
     * @param int $studentId
     */
    public function __construct($courseId, $studentId)
    {
        $this->courseId = $courseId;
        $this->studentId = $studentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CourseStudentPoints::recalculate($this->courseId, $this->studentId);
        LessonStudentStats::recalculateForStudent($this->courseId, $this->studentId);
    }
}
