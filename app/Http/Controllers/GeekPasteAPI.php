<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\Services\StudentProgressService;
use App\Solution;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Notification;
use Firebase\JWT\Key;

class GeekPasteAPI extends Controller
{
    private StudentProgressService $progressService;

    public function __construct(StudentProgressService $progressService)
    {
        $this->progressService = $progressService;
    }

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
            if (!$solution) {
                $solution = new Solution();
                $solution->task_id = $task->id;
                $solution->course_id = $course->id;
                $solution->user_id = $user->id;
                $solution->submitted = Carbon::now();
                $solution->text = $text;
                $solution->teacher_id = $course->teachers->first()->id;
            }

            $solution->mark = min($points, $task->max_mark);
            $solution->comment = $comments;
            $solution->checked = Carbon::now();
            DB::transaction(function () use ($solution, $course) {
                if ($solution->task->price > 0 && $solution->mark == $solution->task->max_mark && !$solution->task->isFullDone($solution->user_id)) {
                    CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
                }
                $solution->save();
                $this->progressService->recalculateStudentProgress($course->id, $solution->user_id);
            });

            $old_rank = $solution->user->rank();

            $solution->user->rescore();
            $new_rank = $solution->user->rank();

            $when = \Carbon\Carbon::now()->addSeconds(1);
            if ($new_rank != $old_rank) {
                $when = \Carbon\Carbon::now()->addSeconds(1);
                Notification::send($solution->user, (new \App\Notifications\NewRank())->delay($when));
            }

            return response()->json(['state' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['state' => 'error', 'message' => $e->getMessage()]);
        }

    }

}
