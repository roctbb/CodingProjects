<?php

namespace App\Console\Commands;

use App\Course;
use App\CourseActivity;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeneratePulseInsights extends Command
{
    protected $signature = 'pulse:insights {--daily-summary} {--difficult-spots} {--streaks}';

    protected $description = 'Generate aggregate and teacher-signal pulse events';

    public function handle()
    {
        $dailySummary = (bool) $this->option('daily-summary');
        $difficultSpots = (bool) $this->option('difficult-spots');
        $streaks = (bool) $this->option('streaks');

        if (!$dailySummary && !$difficultSpots && !$streaks) {
            $dailySummary = true;
            $difficultSpots = true;
            $streaks = true;
        }

        $created = 0;

        if ($dailySummary) {
            $created += $this->recordDailySummaries();
        }

        if ($difficultSpots) {
            $created += $this->recordDifficultSpots();
        }

        if ($streaks) {
            $created += $this->recordLearningStreaks();
        }

        $this->info('Created pulse insight events: ' . $created . '.');

        return 0;
    }

    private function recordDailySummaries(): int
    {
        $date = Carbon::yesterday();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $created = 0;

        Course::where('state', 'started')->chunkById(50, function ($courses) use ($date, $start, $end, &$created) {
            foreach ($courses as $course) {
                $solutionsCount = Solution::where('course_id', $course->id)
                    ->whereBetween('submitted', [$start, $end])
                    ->count();
                $checkedStats = Solution::where('course_id', $course->id)
                    ->whereBetween('checked', [$start, $end])
                    ->selectRaw('COUNT(*) as checked_count, COALESCE(SUM(mark), 0) as xp_earned')
                    ->first();

                $activity = CourseActivity::recordCourseDailySummary(
                    $course,
                    $date,
                    (int) $solutionsCount,
                    (int) ($checkedStats->checked_count ?? 0),
                    (int) ($checkedStats->xp_earned ?? 0)
                );

                if ($activity) {
                    $created++;
                }
            }
        });

        return $created;
    }

    private function recordDifficultSpots(): int
    {
        $since = Carbon::now()->subDay();
        $created = 0;

        $rows = Solution::query()
            ->join('tasks', 'tasks.id', '=', 'solutions.task_id')
            ->where('tasks.is_hidden', false)
            ->where('tasks.max_mark', '>', 0)
            ->whereNotNull('solutions.checked')
            ->where('solutions.checked', '>=', $since)
            ->whereColumn('solutions.mark', '<', 'tasks.max_mark')
            ->groupBy('solutions.course_id', 'solutions.task_id')
            ->havingRaw('COUNT(DISTINCT solutions.user_id) >= 3')
            ->orderByDesc(DB::raw('COUNT(DISTINCT solutions.user_id)'))
            ->limit(20)
            ->get([
                'solutions.course_id',
                'solutions.task_id',
                DB::raw('COUNT(DISTINCT solutions.user_id) as students_count'),
            ]);

        $courses = Course::whereIn('id', $rows->pluck('course_id')->filter()->unique())->get()->keyBy('id');
        $tasks = Task::with('step.lesson')->whereIn('id', $rows->pluck('task_id')->filter()->unique())->get()->keyBy('id');

        foreach ($rows as $row) {
            $course = $courses->get($row->course_id);
            $task = $tasks->get($row->task_id);

            if (!$course || !$task) {
                continue;
            }

            $activity = CourseActivity::recordTaskStruggle($course, $task, (int) $row->students_count);
            if ($activity) {
                $created++;
            }
        }

        return $created;
    }

    private function recordLearningStreaks(): int
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays(14)->startOfDay();
        $created = 0;

        $userIds = Solution::whereNotNull('submitted')
            ->where('submitted', '>=', $windowStart)
            ->distinct()
            ->pluck('user_id')
            ->filter();

        User::whereIn('id', $userIds)->chunkById(100, function ($users) use ($today, $windowStart, &$created) {
            foreach ($users as $user) {
                $dates = Solution::where('user_id', $user->id)
                    ->whereNotNull('submitted')
                    ->where('submitted', '>=', $windowStart)
                    ->selectRaw('DATE(submitted) as activity_date')
                    ->distinct()
                    ->pluck('activity_date')
                    ->flip();

                $days = 0;
                $cursor = $today->copy();

                while ($dates->has($cursor->toDateString())) {
                    $days++;
                    $cursor->subDay();
                }

                if ($days < 3) {
                    continue;
                }

                $activity = CourseActivity::recordLearningStreak($user, $days, $today);
                if ($activity) {
                    $created++;
                }
            }
        });

        return $created;
    }
}
