<?php
namespace App\Services\CodeChecking;

use App\CodeSubmission;
use App\Solution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubmissionReview
{
    public function status(CodeSubmission $submission): array
    {
        $solution=$submission->solution_id ? Solution::find($submission->solution_id) : null;
        $requested=(bool)$solution?->recheck_requested;
        $run=$submission->currentRun; $task=\App\Task::findOrFail($submission->task_id);
        $reason=null;
        if (!$run->finished_at && $run->state!=='imported_pending') $reason='Дождитесь результата проверки.';
        elseif ($task->isBlocked($submission->user_id,$submission->course_id)) $reason='Задача заблокирована.';
        elseif (($run->points !== null && (int)($run->snapshot['points']??0)>0 && $run->points >= $run->snapshot['points'])
            || (\Illuminate\Support\Facades\Schema::hasTable('solutions') && Solution::where('task_id',$task->id)->where('course_id',$submission->course_id)
                ->where('user_id',$submission->user_id)->fullEvaluation((int)$task->max_mark)->exists())) $reason='Задача уже зачтена на полный балл.';
        return ['available'=>!$requested && !$reason,'requested'=>$requested,'comment'=>$requested?$solution->recheck_comment:null,
            'message'=>$requested?'Запрос отправлен преподавателю.':$reason];
    }

    public function request(CodeSubmission $submission,string $comment): array
    {
        return DB::transaction(function () use ($submission,$comment) {
            $locked=CodeSubmission::lockForUpdate()->findOrFail($submission->id);
            \App\User::whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
            $status=$this->status($locked);
            if (!$status['available']) return $status;
            if ($locked->solution_id) $solution=Solution::lockForUpdate()->findOrFail($locked->solution_id);
            else {
                // An archived orphan enters the review queue ungraded, without historical rewards.
                $solution=new Solution();
                $solution->forceFill(['user_id'=>$locked->user_id,'course_id'=>$locked->course_id,'task_id'=>$locked->task_id,
                    'submitted'=>$locked->created_at,'text'=>url('/insider/code-submissions/'.$locked->id),
                    'teacher_id'=>\App\Course::findOrFail($locked->course_id)->teachers->first()?->id]);
            }
            if (!$solution->recheck_requested) {
                $solution->recheck_requested=true;$solution->recheck_comment=trim($comment);$solution->review_skipped=false;$solution->save();
                $locked->update(['solution_id'=>$solution->id]);
                app(\App\Services\TaskChat::class)->recordRecheck($solution, $locked->id);
                Notification::send($solution->course->teachers,(new \App\Notifications\NewSolution($solution))->delay(now()->addSecond())->afterCommit());
            }
            return $this->status($locked);
        });
    }
}
