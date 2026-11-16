<?php

namespace App\Services;

use App\CodeSubmission;
use App\Solution;
use App\Task;
use App\Course;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** The task page owns both student attempts and teacher review context. */
class TaskLocation
{
    public function task(int $course,Task $task,int $author,?string $attempt=null):string
    {
        $parameters=['student'=>$author,'task'=>(int)$task->id];
        if($attempt!==null)$parameters['attempt']=$attempt;
        return url('/insider/courses/'.$course.'/steps/'.$task->step_id).'?'.http_build_query($parameters).'#task'.$task->id;
    }

    public function submission(CodeSubmission $submission):string
    {
        return $this->task((int)$submission->course_id,Task::findOrFail($submission->task_id),(int)$submission->user_id,$submission->id);
    }

    public function solution(Solution $solution, array $reviewContext = []):string
    {
        $native=array_key_exists('native_submission_id',$solution->getAttributes())?$solution->getAttribute('native_submission_id')
            :(Schema::hasTable('code_submissions')?CodeSubmission::where('solution_id',$solution->id)->whereNull('legacy_id')
                ->where('course_id',$solution->course_id)->where('task_id',$solution->task_id)->where('user_id',$solution->user_id)->value('id'):null);
        $url = $this->task((int)$solution->course_id,$solution->task,(int)$solution->user_id,$native?:'solution-'.$solution->id);
        $reviewContext = array_intersect_key($reviewContext, array_flip(['lesson', 'q', 'review_status', 'queue', 'page']));
        if (!$reviewContext) return $url;
        [$path, $anchor] = explode('#', $url, 2);
        return $path.'&'.http_build_query($reviewContext).'#'.$anchor;
    }

    /** Resolve a deep link before rendering: an attempt never changes its task or author. */
    public function context(Request $request,User $viewer,Course $course,Collection $tasks):?array
    {
        if(!$request->hasAny(['student','task','attempt']))return null;
        $input=$request->validate(['student'=>'sometimes|integer|min:1','task'=>'sometimes|integer|min:1',
            'attempt'=>['sometimes','string','regex:/^(?:solution-[1-9][0-9]*|[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})$/D']]);
        $author=(int)($input['student']??$viewer->id);
        abort_unless($author===(int)$viewer->id || app(CourseAccess::class)->manages($viewer,$course),403);
        $taskId=isset($input['task'])?(int)$input['task']:null;$attempt=$input['attempt']??null;
        if($attempt!==null){
            $query=str_starts_with($attempt,'solution-')?Solution::query():CodeSubmission::query();
            $record=$query->where('course_id',$course->id)->where('user_id',$author)->whereIn('task_id',$tasks->pluck('id'))
                ->findOrFail(str_starts_with($attempt,'solution-')?(int)substr($attempt,9):$attempt);
            abort_if($taskId!==null && $taskId!==(int)$record->task_id,404);
            $taskId=(int)$record->task_id;
        }
        abort_if($taskId!==null && !$tasks->contains('id',$taskId),404);
        return ['student_id'=>$author,'task_id'=>$taskId,'attempt'=>$attempt];
    }
}
