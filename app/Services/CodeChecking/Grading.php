<?php
namespace App\Services\CodeChecking;

use App\CodeSubmission;
use App\Course;
use App\CourseActivity;
use App\CourseStudentPoints;
use App\LessonStudentStats;
use App\CoinTransaction;
use App\Solution;
use App\Task;
use App\User;
use Illuminate\Support\Facades\DB;

class Grading
{
    public function apply(CodeSubmission $submission, array $data): void
    {
        DB::transaction(function () use ($submission,$data) {
            $task=Task::findOrFail($submission->task_id);
            $user=User::lockForUpdate()->findOrFail($submission->user_id);
            $course=Course::findOrFail($submission->course_id);
            abort_if($task->isBlocked($user->id,$course->id),403,'Task is blocked.');
            $beforeRank=$user->rank();
            $url=url('/insider/code-submissions/'.$submission->id);
            $solution=$submission->solution_id ? Solution::findOrFail($submission->solution_id) :
                Solution::where('course_id',$course->id)->where('task_id',$task->id)->where('user_id',$user->id)->where('text',$url)->first();
            $new=!$solution;
            if(!$solution) {
                $solution=new Solution();
                $solution->forceFill(['user_id'=>$user->id,'course_id'=>$course->id,'task_id'=>$task->id,
                    'submitted'=>$submission->created_at,'text'=>$url,'teacher_id'=>$course->teachers->first()?->id]);
            }
            abort_unless((int)$solution->user_id===(int)$user->id && (int)$solution->task_id===(int)$task->id && (int)$solution->course_id===(int)$course->id,409);
            // Solution applies the minimum point; retain the checker's original score for classroom eligibility.
            $points=min($data['points'],$task->max_mark);
            if ($new && $submission->deadline_rule_snapshot !== null) $solution->deadline_rule_snapshot=$submission->deadline_rule_snapshot;
            if ($new && $submission->class_bonus_snapshot !== null) $solution->class_bonus_snapshot=$submission->class_bonus_snapshot;
            $solution->applyDeadlinePenalty($points,$task->getDeadline($course->id));
            $comment=mb_strcut($data['comment'],0,60000,'UTF-8');
            $solution->comment=$solution->hasActiveDeadlinePenalty() ? trim("Сдано с опозданием. Штраф: -{$solution->deadline_penalty_amount} XP.\n\n".$comment) : $comment;
            $solution->checked=now();
            $solution->recheck_requested=false;$solution->recheck_comment=null;$solution->review_skipped=false;
            $integrity=app(Integrity::class)->legacyPayload($submission);
            $ai=$integrity['ai'];$similarity=$integrity['similarity'];
            $solution->geekpaste_ai_warning=(bool)($ai['warning']??false);
            $solution->geekpaste_ai_confidence=$ai['confidence']??null;
            $solution->geekpaste_ai_reasons=$ai['reasons']??null;
            $solution->geekpaste_llm_probability=$ai['llm_probability']??null;
            $solution->geekpaste_similarity_checked=$similarity['checked'];
            $solution->geekpaste_similarity_warning=$similarity['warning'];
            $solution->geekpaste_similarity_critical=$similarity['critical'];
            $solution->geekpaste_similarity_max_percent=$similarity['max_percent'];
            $solution->geekpaste_similarity_matches_count=$similarity['matches_count'];
            $solution->geekpaste_integrity_synced_at=now();
            if($task->price>0 && $solution->qualifiesForTaskPriceReward() && !$task->hasRewardableFullSolution($user->id))
                CoinTransaction::register($user->id,$user->taskCoinReward($task->price),'Task #'.$task->id);
            $solution->save();
            $submission->update(['solution_id'=>$solution->id]);
            if($new)CourseActivity::recordSolutionSubmitted($solution);
            CourseActivity::recordSolutionChecked($solution);
            if($solution->isEligibleForAiAchievement())\App\Jobs\GenerateSolutionAchievement::dispatch($solution->id)->afterCommit();
            CourseStudentPoints::recalculate($course->id,$user->id);
            LessonStudentStats::recalculateForStudent($course->id,$user->id);
            $solution->loadMissing('task.step.lesson','course','user');
            CourseActivity::recordProgressMilestones($course,$user,$solution->task?->step?->lesson);
            $user->rescore();CourseActivity::recordXpMilestones($user);$user->awardRankPromotionIfNeeded($beforeRank);
        });
    }
}
