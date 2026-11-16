<?php
// Opt-in PostgreSQL integration check. All writes are rolled back; no external notifications or files are touched.
if(getenv('ROLLBACK_SUBMISSION_AUDIT')!=='1')throw new RuntimeException('Explicit rollback audit opt-in required');
require 'vendor/autoload.php';
if(getenv('REMOVAL_FIX_PATH'))require getenv('REMOVAL_FIX_PATH');
$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,get_class($e).': '.substr(explode(' (Connection:',$e->getMessage())[0],0,300).PHP_EOL);exit(1);});
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
if(DB::connection()->getDriverName()!=='pgsql' || DB::transactionLevel()!==0)throw new RuntimeException('A fresh PostgreSQL connection is required');
config(['cache.default'=>'array','session.driver'=>'array']);
Illuminate\Support\Facades\Queue::fake();Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Http::preventStrayRequests();
$source=App\CodeSubmission::whereNull('legacy_id')->whereNotNull('solution_id')->where('preparation_state','ready')
 ->whereHas('currentRun',fn($q)=>$q->whereIn('state',['completed','error','awaiting_review']))
 ->whereHas('author',fn($q)=>$q->where('role','student'))->orderByDesc('created_at')->first();
if(!$source)throw new RuntimeException('No completed native student submission available for the rollback audit');
$course=App\Course::findOrFail($source->course_id);$teacher=$course->teachers()->whereIn('role',['teacher','admin'])->first();
if(!$teacher)throw new RuntimeException('The audit course has no teacher');
Illuminate\Support\Facades\Auth::setUser($teacher);
$snapshots=function()use($source){
 $queries=[
  'submission'=>DB::table('code_submissions')->where('id',$source->id),
  'runs'=>DB::table('code_execution_runs')->where('submission_id',$source->id),
  'solutions'=>DB::table('solutions')->where('course_id',$source->course_id)->where('task_id',$source->task_id)->where('user_id',$source->user_id),
  'coins'=>DB::table('coin_transactions')->where('user_id',$source->user_id),
  'achievements'=>DB::table('achievements')->where('user_id',$source->user_id),
  'course_points'=>DB::table('course_student_points')->where('course_id',$source->course_id)->where('student_id',$source->user_id),
  'lesson_points'=>DB::table('lesson_student_stats')->where('course_id',$source->course_id)->where('student_id',$source->user_id),
 ];$values=[];foreach($queries as $key=>$query)$values[$key]=hash('sha256',$query->orderBy('id')->get()->toJson());return $values;
};
DB::beginTransaction();$before=null;$failure=null;
try{
 App\CodeSubmission::whereKey($source->id)->lockForUpdate()->firstOrFail();
 App\User::whereKey($source->user_id)->lockForUpdate()->firstOrFail();
 $before=$snapshots();
 app(App\Services\SolutionRemoval::class)->remove($teacher,$source->course_id,$source->task_id,$source->id);
 if(DB::transactionLevel()!==1 || DB::table('code_submissions')->where('id',$source->id)->exists())throw new RuntimeException('Removal did not complete inside the outer transaction');
}catch(Throwable $e){$failure=$e;}
finally{while(DB::transactionLevel()>0)DB::rollBack();}
if($before!==null && $before!==$snapshots())throw new RuntimeException('Rollback did not restore the original records');
if($failure)throw $failure;
echo json_encode(['passed'=>true,'native_removal_completed'=>true,'all_records_restored'=>true,'notifications_faked'=>true,'persistent_deletion'=>false]).PHP_EOL;
