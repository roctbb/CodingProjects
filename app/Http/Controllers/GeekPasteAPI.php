<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\LessonStudentStats;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Firebase\JWT\Key;

class GeekPasteAPI extends Controller
{
    public function submitSolution(Request $request)
    {
        try {
            $requestData = $request->json()->all();

            $points = $requestData['points'];
            $comments = $requestData['comments'];
            $text = $requestData['solution'];
            $course_id = $requestData['course_id'];
            $tokenPayload = \Firebase\JWT\JWT::decode($requestData['token'], new Key(config('auth.jwt_secret'), 'HS256'));
            $user_id = $tokenPayload->user_id;
            $task_id = $tokenPayload->task_id;

            $task = Task::findOrFail($task_id);
            $user = User::findOrFail($user_id);
            $course = Course::findOrFail($course_id);
            $old_rank = $user->rank();

            // Reject if task is blocked for this user in this course
            if ($task->isBlocked($user->id, $course->id)) {
                return response()->json([
                    'state' => 'blocked',
                    'message' => 'Задача заблокирована для этого пользователя в данном курсе.'
                ], 403);
            }

            // Check if solution with the same link already exists
            $solution = Solution::where('task_id', $task->id)
                ->where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->where('text', $text)
                ->first();

            // If solution exists, update it; otherwise create new one
            $isNewSolution = !$solution;

            if (!$solution) {
                $solution = new Solution();
                $solution->task_id = $task->id;
                $solution->course_id = $course->id;
                $solution->user_id = $user->id;
                $solution->submitted = Carbon::now();
                $solution->text = $text;
                $solution->teacher_id = $course->teachers->first()->id;
            }

            $solution->applyDeadlinePenalty(min($points, $task->max_mark), $task->getDeadline($course->id));
            $solution->comment = $solution->hasActiveDeadlinePenalty()
                ? trim("Сдано с опозданием. Штраф: -{$solution->deadline_penalty_amount} XP.\n\n" . $comments)
                : $comments;
            $solution->checked = Carbon::now();


            if ($solution->task->price > 0 && $solution->qualifiesForTaskPriceReward() && !$solution->task->hasRewardableFullSolution($solution->user_id)) {
                CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
            }
            $solution->save();
            if ($isNewSolution) {
                CourseActivity::recordSolutionSubmitted($solution);
            }
            CourseActivity::recordSolutionChecked($solution);

            // Recalculate cached points after auto-grading code
            CourseStudentPoints::recalculate($course->id, $solution->user_id);
            LessonStudentStats::recalculateForStudent($course->id, $solution->user_id);

            $user->rescore();
            $user->awardRankPromotionIfNeeded($old_rank);

            return response()->json(['state' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['state' => 'error', 'message' => $e->getMessage()]);
        }

    }

}
