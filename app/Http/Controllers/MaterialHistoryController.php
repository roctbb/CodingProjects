<?php
namespace App\Http\Controllers;

use App\MaterialReview\Documents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialHistoryController extends Controller
{
    public function resource(Request $r,string $id)
    {
        $resource=DB::table('material_review_resources')->find($id); abort_unless($resource,404);
        $allowed=$r->user()->role==='admin';
        if (!$allowed) {
            foreach (\App\Course::where('program_id',$resource->program_id)->with('teachers','students','program.lessons.info')->get() as $course) {
                if (!$course->teachers->contains('id',$r->user()->id)&&!$course->students->contains('id',$r->user()->id)) continue;
                $manager=app(\App\Services\CourseAccess::class)->manages($r->user(),$course);
                foreach ($course->program->lessons as $lesson) if ($lesson->isAvailableForUser($course,$r->user())) {
                    $document=app(Documents::class)->lesson($lesson->id);
                    foreach ($document['resources'] as $use) {
                        if ($use['resource_id']!==$id) continue;
                        if (!$manager&&!in_array($use['field'],['theory','text','description','image'],true)) continue;
                        if (!$manager&&$use['entity']==='task'&&!\App\Task::find($use['id'])->isVisible($r->user(),$course)) continue;
                        $allowed=true;
                    }
                }
            }
            if (!$allowed&&\Illuminate\Support\Facades\Schema::hasColumn('solutions','material_snapshot')) {
                foreach (DB::table('solutions')->where('user_id',$r->user()->id)->where('material_snapshot','like','%'.$id.'%')->pluck('material_snapshot') as $raw) {
                    foreach (Documents::decode($raw)['resources']??[] as $use) if ($use['resource_id']===$id&&$use['field']==='text') $allowed=true;
                }
            }
        }
        abort_unless($allowed,403);
        return response(base64_decode($resource->data),200,['Content-Type'=>$resource->mime,'X-Content-Type-Options'=>'nosniff',
            'Content-Security-Policy'=>"default-src 'none'; style-src 'unsafe-inline'; sandbox",'Cache-Control'=>'private, max-age=3600']);
    }
    public function attempt(Request $r,string $id)
    {
        $work=\App\Solution::findOrFail($id); $course=\App\Course::findOrFail($work->course_id);
        abort_unless($work->user_id===$r->user()->id||app(\App\Services\CourseAccess::class)->manages($r->user(),$course),403);
        $snapshot=Documents::decode($work->material_snapshot); $manager=app(\App\Services\CourseAccess::class)->manages($r->user(),$course);
        return view('material-review.attempt',compact('work','snapshot','manager'));
    }
    public function codeAttempt(Request $r,string $id)
    {
        $work=\App\CodeSubmission::with('currentRun')->findOrFail($id);
        app(\App\Services\CodeChecking\Access::class)->authorOrTeacher($r->user(),$work);
        $snapshot=Documents::decode($work->material_snapshot);
        if ((!$snapshot||!empty($snapshot['unknown']))&&isset($work->currentRun->snapshot['public_text'])) {
            $s=$work->currentRun->snapshot; $snapshot=['fields'=>['text'=>$s['public_text'],'name'=>$s['name']??'Условие задачи']];
        }
        $manager=app(\App\Services\CodeChecking\Access::class)->teacher($r->user(),$work->course_id);
        return view('material-review.code-condition',compact('work','snapshot','manager'));
    }
}
