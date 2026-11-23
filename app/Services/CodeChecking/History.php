<?php

namespace App\Services\CodeChecking;

use App\CodeSubmission;
use App\Task;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Attempt events and source snapshots are distinct, especially after the GP import. */
class History
{
    public function __construct(private Access $access) {}

    /** New native attempts exist before a graded Solution row; load the whole step once. */
    public function ungradedProgress(int $course,array $tasks,?int $author=null)
    {
        if(!$tasks || !Schema::hasTable('code_submissions') || !Schema::hasTable('code_execution_runs'))return collect();
        $rows=DB::table('code_submissions as s')->leftJoin('code_execution_runs as r','r.id','=','s.current_run_id')
            ->where('s.course_id',$course)->whereIn('s.task_id',$tasks)->whereNull('s.legacy_id')->whereNull('s.solution_id')
            ->when($author!==null,fn($query)=>$query->where('s.user_id',$author))
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('course_students')
                ->whereColumn('course_students.user_id','s.user_id')->where('course_students.course_id',$course))
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('course_teachers')
                ->whereColumn('course_teachers.user_id','s.user_id')->where('course_teachers.course_id',$course))
            ->orderByDesc('s.created_at')->get(['s.task_id','s.user_id','s.created_at','r.state','r.points']);
        return $rows->groupBy('task_id')->map(fn($taskRows)=>$taskRows->groupBy('user_id')->map(function($attempts){
            $latest=$attempts->first();
            return ['best_evaluation'=>$attempts->where('state', 'completed')->max('points'), 'attempts'=>$attempts->count(),'latest_at'=>Carbon::parse($latest->created_at,config('app.timezone')),
                'state'=>$latest->state??'queued','pending'=>$attempts->contains(fn($r)=>!in_array($r->state,['completed','error','imported_pending','awaiting_review'],true)),
                'error'=>$latest->state==='error'];
        }));
    }

    public function page(User $viewer,int $course,Task $task,?int $author,?bool $viewed,int $page):array
    {
        $teacher=$this->access->teacher($viewer,$course);
        if(!$teacher){
            abort_if($author!==null && $author!==(int)$viewer->id,403);
            $author=(int)$viewer->id;
        }
        $selected = (new AttemptHistoryPage)->select($course, $task->id, $author, $viewed, $page);
        $summary = $selected[0];
        $keys = collect($selected)->whereNotNull('entry_id');
        $sourceIds = $keys->pluck('source_id')->filter()->unique()->values();
        $eventIds = $keys->pluck('event_id')->filter()->unique()->values();
        $sources = $sourceIds->isEmpty() ? collect() : $this->sources($course, $task->id, $author)
            ->whereIn('s.id', $sourceIds)->get()->keyBy('id');
        $events = $eventIds->isEmpty() ? collect() : $this->solutions($course, $task->id, $author)
            ->whereIntegerInRaw('solutions.id', $eventIds)->get()->keyBy('id');
        $items = [];
        $bestAttempt = null;
        foreach ($keys as $key) {
            $source = $sources->get($key->source_id);
            $event = $events->get($key->event_id);
            if (($key->event_id !== null && !$event) || ($key->entry_id === $key->source_id && !$source)) continue;
            $native = $source && $key->entry_id === $source->id;
            $row = $native ? $this->submission($source) : $this->legacy($event, $source, (int) $task->max_mark);
            if ($event) {
                $row['needs_review'] = (!$event->review_skipped && $event->mark === null) || (bool)$event->recheck_requested;
                if ($native) {
                    $row['awarded_xp'] = $event->mark === null ? null : (int)$event->mark;
                    $row['evaluated_points'] = $event->evaluated_mark ?? $event->raw_mark ?? $event->mark;
                    $row['solution_id'] = (int)$event->id;
                }
            } elseif (!$row['counts_as_attempt']) {
                $row['origin'] = 'geekpaste_archive';
            }
            $row['is_best'] = (int)$key->grade_rank === 1 && $key->xp !== null;
            if ($key->on_page) $items[] = $row;
            else $bestAttempt = $row;
        }
        $total = (int)$summary->total;
        return ['items'=>$items, 'has_more'=>$page*20 < $total, 'total'=>$total,
            'total_attempts'=>(int)$summary->attempts, 'best_attempt'=>$bestAttempt,
            'task_outcome'=>$author === null ? null : \App\Support\TaskOutcome::summary(
                $summary->best_evaluation, (int)$task->max_mark, $summary->latest_state
            )];
    }

    public function event(User $viewer,int $course,Task $task,int $id):array
    {
        abort_unless(Schema::hasTable('solutions'),404);
        $event=$this->solutions($course,$task->id,null)->where('solutions.id',$id)->first();
        abort_unless($event,404);
        $teacher=$this->access->teacher($viewer,$course);
        abort_unless($teacher || (int)$event->user_id===(int)$viewer->id,403);
        $solution=\App\Solution::findOrFail($id);
        $sources=$this->sources($course,$task->id,(int)$event->user_id)->where(function ($query) use ($event) {
            $query->where('s.solution_id', $event->id);
            if (preg_match('~/code-submissions/([a-f0-9-]{36})(?:[/?#]|$)~i',$event->source_reference ?? '',$match)
                && \Illuminate\Support\Str::isUuid($match[1])) $query->orWhere('s.id',$match[1]);
            if (!empty($event->geekpaste_code_id)) $query->orWhere('s.legacy_id',$event->geekpaste_code_id);
            if (preg_match('/(?:\?|&|&amp;)id=([A-Za-z0-9_-]+)/',$event->source_reference ?? '',$match)) $query->orWhere('s.legacy_id',$match[1]);
        })->get();
        $source=$this->sourceFor($event,$sources->keyBy('id'),$sources->filter(fn($r)=>$r->legacy_id!==null)->keyBy('legacy_id'),$sources->filter(fn($r)=>$r->solution_id!==null)->keyBy('solution_id'));
        $row=$this->legacy($event,$source,(int)$task->max_mark);
        $saved=DB::table('solutions')->where('id',$id)->first();
        $answer=(string)($saved->text??'');
        $isLink=$source || preg_match('~(?:paste\.geekclass\.ru|/code-submissions/|^https?://)~i',$answer);
        $row['source_available']=$source!==null || (!$isLink && trim($answer)!=='');
        $penalty=(empty($saved->deadline_penalty_paid_at) && empty($saved->deadline_penalty_waived_at))?(int)($saved->deadline_penalty_amount??0):0;
        $comment=(string)($saved->comment??'');
        $editableComment=$this->teacherComment($saved,$comment);
        $teacherFeedback=$editableComment!==null;
        $checkType=$source?->check_type;
        if(!$checkType && Schema::hasTable('code_task_checkers'))$checkType=DB::table('code_task_checkers')->where('task_id',$task->id)->value('config->check_type');
        // Some old exports contain a test report despite an unrelated current
        // checker. Its raw IO must not escape through the generic comment field.
        if(!$teacherFeedback && app(HistoricalReport::class)->parse($comment)!==null)$checkType='tests';
        $checkType??='manual';
        $visibleComment=$checkType==='tests' && !$teacherFeedback?'':$comment;
        return $row+['checked_at'=>$this->date($saved->checked??null),'check_type'=>$checkType,'comment_available'=>trim($comment)!=='','comment'=>$visibleComment,
            'review'=>$teacher ? app(ReviewActions::class)->forSolution($solution) : null,
            'review_request'=>app(\App\Services\SolutionRecheck::class)->status($solution),
            'review_request_url'=>url('/insider/courses/'.$course.'/tasks/'.$task->id.'/solution/'.$id.'/recheck'),
            'teacher_feedback'=>$teacherFeedback,
            'text'=>$source?null:$answer,'original_reference'=>$isLink?$answer:null,'files'=>[],'report'=>null,
            'award_breakdown'=>app(SolutionAward::class)->view($solution, $viewer),
            'source_url'=>$source?url('/insider/code-submissions/'.$source->id):null,
            'source_notice'=>match (true) {
                $source !== null && $source->legacy_id === null => 'Исходный код сохранён отдельно. Отчёт текущей проверки не подставляется в историческую запись сдачи.',
                $source === null && $isLink => 'Исходная ссылка сохранена, но отдельная версия кода и отчёт этой сдачи недоступны.',
                default => null,
            },
            'review_url'=>$teacher?app(\App\Services\TaskLocation::class)->task($course,$task,(int)$event->user_id,'solution-'.$id):null,
            'can_grade'=>$teacher,'grade_url'=>$teacher?url('/insider/courses/'.$course.'/solution/'.$id):null,
            'review_mark'=>$saved->evaluated_mark??$saved->raw_mark??$saved->mark,
            'review_comment'=>$teacherFeedback?$editableComment:$visibleComment];
    }

    /** Detail-only lookup; history summaries never load feedback or audit payloads. */
    private function teacherComment(object $solution,string $comment):?string
    {
        if(!Schema::hasTable('course_activities'))return null;
        $activity=\App\CourseActivity::where('solution_id',$solution->id)->where('course_id',$solution->course_id)
            ->where('task_id',$solution->task_id)->where('user_id',$solution->user_id)
            ->where('type',\App\CourseActivity::TYPE_SOLUTION_CHECKED)->orderByDesc('id')->first(['payload']);
        return self::verifiedTeacherComment($activity?->payload ?? [], $comment);
    }

    public static function verifiedTeacherComment(array $payload, string $comment): ?string
    {
        if(($payload['feedback_source']??null)!=='teacher' || !is_string($payload['feedback_sha256']??null)
            || !hash_equals(hash('sha256',$comment),$payload['feedback_sha256'])
            || !is_string($payload['editable_comment']??null) || mb_strlen($payload['editable_comment'])>20000)return null;
        return $payload['editable_comment'];
    }

    private function sources(int $course,int $task,?int $author)
    {
        return DB::table('code_submissions as s')->leftJoin('code_execution_runs as r','r.id','=','s.current_run_id')
            ->leftJoin('users as u','u.id','=','s.user_id')
            ->where('s.course_id',$course)->where('s.task_id',$task)
            ->when($author!==null,fn($q)=>$q->where('s.user_id',$author))
            ->select(['s.id','s.user_id','s.legacy_id','s.solution_id','s.created_at','s.teacher_viewed','s.preparation_state',
                'u.name as user_name','r.id as execution_id','r.state','r.progress','r.points','r.snapshot->points as maximum_points','r.snapshot->check_type as check_type']);
    }

    private function solutions(int $course,int $task,?int $author)
    {
        $columns=['solutions.id','solutions.user_id','solutions.submitted','solutions.mark','u.name as user_name',DB::raw('substr(solutions.text,1,2048) as source_reference')];
        foreach(['raw_mark','evaluated_mark','geekpaste_code_id','checked','recheck_requested','review_skipped'] as $field)$columns[]=Schema::hasColumn('solutions',$field)?'solutions.'.$field:DB::raw('NULL as '.$field);
        return DB::table('solutions')->leftJoin('users as u','u.id','=','solutions.user_id')
            ->where('solutions.course_id',$course)->where('solutions.task_id',$task)->whereNotNull('solutions.submitted')
            ->when($author!==null,fn($q)=>$q->where('solutions.user_id',$author))->select($columns);
    }

    private function sourceFor(object $event,$byId,$byLegacy,$bySolution):?object
    {
        $source=null;
        if(preg_match('~/code-submissions/([a-f0-9-]{36})(?:[/?#]|$)~i',$event->source_reference??'',$m))$source=$byId->get($m[1]);
        if(!$source && !empty($event->geekpaste_code_id))$source=$byLegacy->get($event->geekpaste_code_id);
        if(!$source && preg_match('/(?:\?|&|&amp;)id=([A-Za-z0-9_-]+)/',$event->source_reference??'',$m))$source=$byLegacy->get($m[1]);
        $source??=$bySolution->get($event->id);
        return $source && (int)$source->user_id===(int)$event->user_id?$source:null;
    }

    private function submission(object $source):array
    {
        return ['kind'=>'submission','id'=>$source->id,'submission_id'=>$source->id,'solution_id'=>$source->solution_id===null?null:(int)$source->solution_id,
            'condition_url'=>url('/insider/material-code-history/'.$source->id),
            'user_id'=>(int)$source->user_id,'user_name'=>$source->user_name,'created_at'=>$this->date($source->created_at),
            'state'=>$source->state??'preparing','progress'=>$source->progress?json_decode($source->progress,true):null,
            'points'=>$source->points===null?null:(int)$source->points,'maximum_points'=>$source->maximum_points===null?null:(int)$source->maximum_points,
            'awarded_xp'=>null,'teacher_viewed'=>(bool)$source->teacher_viewed,'review_state_known'=>true,
            'source_version_unknown'=>$source->legacy_id!==null,'source_available'=>true,'source_submission_id'=>$source->id,
            'counts_as_attempt'=>$source->legacy_id===null];
    }

    private function legacy(object $event,?object $source,int $maximum):array
    {
        return ['kind'=>'legacy_attempt','id'=>'solution-'.$event->id,'submission_id'=>null,'solution_id'=>(int)$event->id,
            'condition_url'=>url('/insider/material-history/'.$event->id),
            'source_submission_id'=>$source?->id,'source_version_unknown'=>$source===null || $source->legacy_id!==null,
            'source_available'=>$source!==null,'user_id'=>(int)$event->user_id,'user_name'=>$event->user_name,
            'created_at'=>$this->date($event->submitted),'state'=>$event->mark===null?'awaiting_review':'completed','progress'=>null,
            'points'=>null,'maximum_points'=>null,'grading_maximum_points'=>$maximum,'awarded_xp'=>$event->mark===null?null:(int)$event->mark,
            'evaluated_points'=>isset($event->evaluated_mark)?(int)$event->evaluated_mark:(isset($event->raw_mark)?(int)$event->raw_mark:null),
            'teacher_viewed'=>$source!==null?(bool)$source->teacher_viewed:null,'review_state_known'=>$source!==null,
            'counts_as_attempt'=>true];
    }

    private function date(?string $value):?string
    {
        return $value?Carbon::parse($value,config('app.timezone'))->toIso8601String():null;
    }
}
