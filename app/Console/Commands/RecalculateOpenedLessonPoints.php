<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateCoursePoints;
use App\LessonInfo;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateOpenedLessonPoints extends Command
{
    protected $signature = 'course:recalculate-opened-lessons {--date= : Opening date in YYYY-MM-DD format}';

    protected $description = 'Recalculate student progress for courses whose lessons open on the selected date';

    public function handle()
    {
        try {
            $date = $this->option('date')
                ? Carbon::parse($this->option('date'))->startOfDay()
                : Carbon::today();
        } catch (\Throwable $exception) {
            $this->error('Invalid opening date. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $courseIds = LessonInfo::query()
            ->whereDate('start_date', $date->toDateString())
            ->whereNotNull('course_id')
            ->distinct()
            ->pluck('course_id');

        foreach ($courseIds as $courseId) {
            RecalculateCoursePoints::dispatch((int) $courseId);
        }

        $this->info("Queued progress recalculation for {$courseIds->count()} course(s).");

        return self::SUCCESS;
    }
}
