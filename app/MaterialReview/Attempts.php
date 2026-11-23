<?php
namespace App\MaterialReview;

use App\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Attempts
{
    public static function snapshot(int $id): array
    {
        $task=DB::table('tasks')->find($id); $step=DB::table('program_steps')->find($task->step_id);
        $resources=[];
        if (app(Documents::class)->installed()) $resources=array_values(array_filter(app(Documents::class)->lesson($step->lesson_id)['resources'],fn ($r)=>$r['entity']==='task'&&(int)$r['id']===$id));
        return ['task_id'=>$id,'lesson_id'=>$step->lesson_id,'program_id'=>$step->program_id,'fields'=>array_diff_key((array)$task,array_flip(['created_at','updated_at'])),'resources'=>$resources,
            'captured_at'=>now()->toIso8601String()];
    }
    public static function token(Task $task): string
    {
        $checking=null;
        if ($task->relationLoaded('workbookChecker')) {
            $checking = $task->workbookChecker?->getRawOriginal();
        } elseif (\Illuminate\Support\Facades\Schema::hasTable('code_task_checkers')) {
            $column=\Illuminate\Support\Facades\Schema::hasColumn('code_task_checkers','task_id')?'task_id':'id';
            $checking=DB::table('code_task_checkers')->where($column,$task->id)->first();
        }
        return Documents::hash(['id'=>$task->id,'step_id'=>$task->step_id,'name'=>$task->name,'text'=>$task->text,'max_mark'=>$task->max_mark,'answer'=>$task->answer,'is_code'=>$task->is_code,'is_quiz'=>$task->is_quiz,'checking'=>$checking]);
    }
    public static function guard(Request $request,Task $task): void
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('program_steps','review_archived_at') && DB::table('program_steps')->where('id',$task->step_id)->whereNotNull('review_archived_at')->exists()) abort(409,'Ступень исключена из маршрута. Прежние работы доступны в истории.');
        $expected=$request->input('material_revision')??$request->header('X-Material-Revision');
        if ($expected!==null && !hash_equals(self::token($task),(string)$expected)) {
            abort(response()->json(['error'=>'material_changed','message'=>'Условие задачи изменилось. Ваш ответ остаётся в редакторе. Обновите условие перед отправкой.'],409));
        }
    }
}
