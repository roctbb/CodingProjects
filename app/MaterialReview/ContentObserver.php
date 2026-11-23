<?php
namespace App\MaterialReview;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Also records ordinary authoring, which remains an immediate published edit. */
class ContentObserver
{
    public function saving(Model $model): void { $this->capture($model,'editor_before'); }
    public function saved(Model $model): void { $this->capture($model,'editor'); }
    public function deleting(Model $model): ?bool
    {
        if (!app(Documents::class)->installed()) return null;
        $this->capture($model,'editor_before_delete');
        if ($model instanceof \App\Task) { $model->is_hidden=true; $model->save(); return false; }
        if ($model instanceof \App\ProgramStep) {
            DB::transaction(function () use ($model) {
                DB::table('tasks')->where('step_id',$model->id)->update(['is_hidden'=>true]);
                $model->review_archived_at=now(); $model->save();
            });
            return false;
        }
        return null;
    }
    private function capture(Model $m,string $source): void
    {
        $docs=app(Documents::class);
        if (!$docs->installed()) return;
        $lesson=$m instanceof \App\Lesson ? $m->id : ($m instanceof \App\ProgramStep ? $m->lesson_id : DB::table('program_steps')->where('id',$m->step_id)->value('lesson_id'));
        if ($lesson&&DB::table('lessons')->where('id',$lesson)->exists()) $docs->revision((int)$lesson,$source,auth()->id());
    }
    public static function freeze(Model $work): void
    {
        if (!Schema::hasColumn($work->getTable(),'material_snapshot') || $work->material_snapshot!==null) return;
        if ($work instanceof \App\Solution && $work->submitted===null) return;
        // Historical work cannot be falsely attributed to today's condition.
        if ($work->exists && ($work instanceof \App\CodeSubmission || $work->getRawOriginal('submitted'))) {
            $work->material_snapshot=Documents::encode(['unknown'=>true]); return;
        }
        $work->material_snapshot=Documents::encode(Attempts::snapshot((int)$work->task_id));
    }
}
