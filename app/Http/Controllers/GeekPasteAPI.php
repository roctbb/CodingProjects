<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\Course;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\Jobs\GenerateSolutionAchievement;
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
            $this->applyGeekPasteIntegrity($solution, $requestData, $text);


            if ($solution->task->price > 0 && $solution->qualifiesForTaskPriceReward() && !$solution->task->hasRewardableFullSolution($solution->user_id)) {
                CoinTransaction::register($solution->user_id, $solution->task->price, "Task #" . $solution->task->id);
            }
            $solution->save();
            if ($isNewSolution) {
                CourseActivity::recordSolutionSubmitted($solution);
            }
            CourseActivity::recordSolutionChecked($solution);
            $this->dispatchAiAchievementGeneration($solution);

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

    public function syncIntegrity(Request $request)
    {
        try {
            $requestData = $request->json()->all();

            $text = $requestData['solution'];
            $course_id = $requestData['course_id'];
            $tokenPayload = \Firebase\JWT\JWT::decode($requestData['token'], new Key(config('auth.jwt_secret'), 'HS256'));
            $user_id = $tokenPayload->user_id;
            $task_id = $tokenPayload->task_id;

            $solution = Solution::where('task_id', $task_id)
                ->where('course_id', $course_id)
                ->where('user_id', $user_id)
                ->where('text', $text)
                ->first();

            if (!$solution) {
                return response()->json(['state' => 'not_found'], 404);
            }

            $this->applyGeekPasteIntegrity($solution, $requestData, $text);
            $solution->save();

            return response()->json(['state' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['state' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function dispatchAiAchievementGeneration(Solution $solution)
    {
        if (!$solution->isEligibleForAiAchievement()) {
            return;
        }

        try {
            GenerateSolutionAchievement::dispatch($solution->id)->afterResponse();
        } catch (\Throwable $e) {
            \Log::warning('AI achievement dispatch failed', [
                'solution_id' => $solution->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function applyGeekPasteIntegrity(Solution $solution, array $requestData, string $solutionText)
    {
        if (trim($solutionText) === '') {
            return;
        }

        $codeId = $this->extractGeekPasteCodeId($solutionText);
        if ($codeId) {
            $solution->geekpaste_code_id = $codeId;
        }

        $integrity = $requestData['academic_integrity'] ?? null;
        $ai = is_array($integrity) && isset($integrity['ai']) && is_array($integrity['ai'])
            ? $integrity['ai']
            : [];
        $similarity = is_array($integrity) && isset($integrity['similarity']) && is_array($integrity['similarity'])
            ? $integrity['similarity']
            : [];

        if (array_key_exists('has_ai_warning', $requestData)) {
            $ai['warning'] = $requestData['has_ai_warning'];
        }
        if (array_key_exists('ai_warning_reasons', $requestData)) {
            $ai['reasons'] = $requestData['ai_warning_reasons'];
        }
        if (array_key_exists('ai_confidence', $requestData)) {
            $ai['confidence'] = $requestData['ai_confidence'];
        }
        if (array_key_exists('gpt_llm_probability', $requestData)) {
            $ai['llm_probability'] = $requestData['gpt_llm_probability'];
        }

        if (array_key_exists('similarity_checked', $requestData)) {
            $similarity['checked'] = $requestData['similarity_checked'];
        }
        if (array_key_exists('has_similarity_warning', $requestData)) {
            $similarity['warning'] = $requestData['has_similarity_warning'];
        }
        if (array_key_exists('has_critical_similarity_warning', $requestData)) {
            $similarity['critical'] = $requestData['has_critical_similarity_warning'];
        }
        if (array_key_exists('similarity_matches_count', $requestData)) {
            $similarity['matches_count'] = $requestData['similarity_matches_count'];
        }
        if (array_key_exists('similarity_max_percent', $requestData)) {
            $similarity['max_percent'] = $requestData['similarity_max_percent'];
        }

        if (empty($ai) && empty($similarity)) {
            return;
        }

        $solution->geekpaste_ai_warning = (bool) ($ai['warning'] ?? false);
        $solution->geekpaste_ai_confidence = $ai['confidence'] ?? null;
        $solution->geekpaste_ai_reasons = $ai['reasons'] ?? null;
        $solution->geekpaste_llm_probability = $this->normalizePercent($ai['llm_probability'] ?? null);
        $solution->geekpaste_similarity_checked = (bool) ($similarity['checked'] ?? false);
        $solution->geekpaste_similarity_warning = (bool) ($similarity['warning'] ?? false);
        $solution->geekpaste_similarity_critical = (bool) ($similarity['critical'] ?? false);
        $solution->geekpaste_similarity_max_percent = $this->normalizePercent($similarity['max_percent'] ?? null);
        $solution->geekpaste_similarity_matches_count = max(0, (int) ($similarity['matches_count'] ?? 0));
        $solution->geekpaste_integrity_synced_at = Carbon::now();
    }

    private function normalizePercent($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(100, (int) $value));
    }

    private function extractGeekPasteCodeId($text): ?string
    {
        $decoded = html_entity_decode((string) $text);

        if (preg_match('/(?:\\?|&|&amp;)id=([A-Za-z0-9_-]+)/', $decoded, $matches)) {
            return $matches[1];
        }

        if (preg_match('#paste\.geekclass\.ru/(?:raw/|view/)?([A-Za-z0-9_-]+)#', $decoded, $matches)) {
            return $matches[1];
        }

        return null;
    }

}
