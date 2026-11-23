<?php
namespace App\Http\Controllers;

use App\MaterialReview\{Documents,Review,Publishing,Problem};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaterialReviewController extends Controller
{
    public function index(Request $r,Review $review)
    {
        abort_unless($review->documents->installed(),503,'Сначала примените миграцию модуля ревью.');
        $catalog=$review->catalog($r->user()); $draft=null; $validation=null; $documents=[]; $alternatives=[];
        $program=(int)$r->query('program',0); $lesson=(int)$r->query('lesson',0);
        $draftId=$r->query('draft');
        if (!$draftId&&$lesson) {
            $live=$review->documents->lesson($lesson); if ($program&&$program!==$live['program_id']) abort(404);
            $candidate=DB::table('material_review_drafts')->where('program_id',$live['program_id'])->whereIn('state',['selected','draft'])->orderByRaw("CASE WHEN state = 'selected' THEN 0 ELSE 1 END")->orderByDesc('updated_at')->get()
                ->first(fn ($d)=>array_key_exists($lesson,Documents::decode($d->bases)));
            $draftId=$candidate?->id;
        }
        if ($draftId) {
            $draft=$review->get($r->user(),$draftId); $validation=$review->check($r->user(),$draft['id']);
            $program=(int)$draft['program_id']; $lesson=$lesson?:$draft['targets'][0]['id']; $documents=$draft['targets'];
            $alternatives=DB::table('material_review_drafts')->where('program_id',$program)->where('id','!=',$draft['id'])->get(['id','title','bases','targets','state'])->filter(fn ($d)=>array_keys(Documents::decode($d->bases))===array_keys($draft['bases']))->all();
        } elseif ($lesson) {
            $doc=$review->documents->lesson($lesson); if ($program && $doc['program_id']!==$program) abort(404);
            $program=$doc['program_id']; $documents=[$doc];
        }
        if ($program&&!collect($catalog)->firstWhere('id',$program)) abort(404);
        $compare=null;
        if ($draft&&$r->query('compare')) { $compare=$review->get($r->user(),$r->query('compare')); abort_unless($compare['program_id']===$draft['program_id'],404); $compare['transfer_groups']=$review->transferGroups($compare); }
        return view('material-review.index',compact('catalog','draft','validation','program','lesson','documents','alternatives','compare'));
    }
    public function create(Request $r,Review $review)
    {
        $r->validate(['lesson_id'=>'required|integer','title'=>'nullable|string|max:255']);
        $base=$review->documents->revision((int)$r->lesson_id,'snapshot',$r->user()->id);
        $d=$review->create($r->user(),['format_version'=>1,'installation_id'=>$review->documents->installation(),'program_id'=>(int)$base['program_id'],
            'base_revision_ids'=>[$base['id']],'title'=>$r->title??'Точечные исправления','reason'=>'Черновик для разбора и ручного редактирования урока.']);
        return redirect('/insider/material-review?draft='.$d['id']);
    }
    public function read(Request $r,Review $review,string $id)
    { return response()->json(['draft'=>$review->get($r->user(),$id),'validation'=>$review->check($r->user(),$id)]); }
    public function action(Request $r,Review $review,Publishing $publishing,string $id,string $action)
    {
        $r->validate(['version'=>'required|integer|min:1']); $v=(int)$r->version; $u=$r->user();
        $out=match($action) {
            'proposal'=>$review->propose($u,$id,$r->input('proposal',[]),$v),
            'decision'=>$review->decide($u,$id,(string)$r->proposal_id,(string)$r->decision,$v,$r->comment),
            'update'=>$review->update($u,$id,$r->input('change',[]),$v),
            'transfer'=>$review->transfer($u,$id,(string)$r->source_draft_id,$r->input('paths',[]),$v,$r->has('source_version')?(int)$r->source_version:null),
            'select'=>$review->select($u,$id,$v), 'reject'=>$review->reject($u,$id,$v),
            'complete'=>$review->complete($u,$id,$v), 'validate'=>$review->check($u,$id),
            'publish'=>$publishing->publish($u,$id,$v),
            'reverse'=>$publishing->reverse($u,(string)$r->publication_id),
            default=>throw new Problem('action','Неизвестное действие.',[],404),
        };
        return response()->json($out);
    }
    public function preview(Request $r)
    { $r->validate(['text'=>'nullable|string|max:2000000']); return response()->json(['html'=>parsedown_math($r->text??'')]); }
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
