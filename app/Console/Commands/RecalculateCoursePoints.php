<?php

namespace App\Console\Commands;

use App\Course;
use App\CourseStudentPoints;
use App\LessonStudentStats;
use Illuminate\Console\Command;

class RecalculateCoursePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course:recalculate-points {course_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate and cache course student points';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $courseId = $this->argument('course_id');

        if ($courseId) {
            // Recalculate for specific course
            $course = Course::with('students')->findOrFail($courseId);
            $this->info("Recalculating points for course: {$course->name}");

            foreach ($course->students as $student) {
                CourseStudentPoints::recalculate($course->id, $student->id);
                LessonStudentStats::recalculateForStudent($course->id, $student->id);
                $this->info("  - Student {$student->id}: {$student->name}");
            }

            $this->info("Done!");
        } else {
            // Recalculate for all courses
            $courses = Course::with('students')->get();
            $this->info("Recalculating points for all courses...");

            $bar = $this->output->createProgressBar($courses->count());

            foreach ($courses as $course) {
                foreach ($course->students as $student) {
                    CourseStudentPoints::recalculate($course->id, $student->id);
                    LessonStudentStats::recalculateForStudent($course->id, $student->id);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->info("\nDone!");
        }
    }
}
