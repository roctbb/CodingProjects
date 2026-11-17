<?php

namespace App\Http\Controllers;

use App\Course;
use App\CourseSubgroup;
use App\Services\CourseAccess;
use App\Services\CourseSubgroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CourseSubgroupsController extends Controller
{
    private function course(Request $request, int $id): Course
    {
        $course = Course::with('teachers')->findOrFail($id);
        app(CourseAccess::class)->manage($request->user(), $course);
        abort_unless(app(CourseSubgroups::class)->available(), 503, 'Примените миграции подгрупп.');
        return $course;
    }

    private function version(Course $course): string
    {
        return hash('sha256', json_encode([$course->students()->pluck('users.id')->all(), DB::table('course_subgroup_memberships')
            ->where('course_id', $course->id)->whereNotNull('active_user_id')->orderBy('id')->pluck('id')->all()]));
    }

    public function index(Request $request, int $course_id)
    {
        $course = $this->course($request, $course_id);
        $groups = $course->subgroups;
        $students = $course->students;
        $memberships = DB::table('course_subgroup_memberships')->where('course_id', $course->id)->whereNotNull('active_user_id')->pluck('subgroup_id', 'user_id');
        $membershipVersion = $this->version($course);
        return view('courses.subgroups', compact('course', 'groups', 'students', 'memberships', 'membershipVersion'));
    }

    public function store(Request $request, int $course_id)
    {
        $course = $this->course($request, $course_id);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('course_subgroups')->where('course_id', $course->id)]]);
        $group = $course->subgroups()->create(['name' => trim($data['name'])]);
        return redirect('/insider/courses/'.$course->id.'/subgroups')->with('status', 'Подгруппа «'.$group->name.'» создана.')->with('created_subgroup_id', $group->id);
    }

    public function update(Request $request, int $course_id, int $subgroup_id)
    {
        $course = $this->course($request, $course_id);
        $group = $course->subgroups()->findOrFail($subgroup_id);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('course_subgroups')->where('course_id', $course->id)->ignore($group->id)], 'revision' => 'required|integer|min:0']);
        if (!CourseSubgroup::whereKey($group->id)->where('revision', $data['revision'])->update(['name' => trim($data['name']), 'revision' => DB::raw('revision + 1'), 'updated_at' => now()])) {
            throw new ConflictHttpException('Подгруппа изменена в другом окне. Обновите страницу.');
        }
        return back()->with('status', 'Название подгруппы сохранено.');
    }

    public function assign(Request $request, int $course_id, CourseSubgroups $service)
    {
        $course = $this->course($request, $course_id);
        $data = $request->validate(['students' => 'required|array|min:1', 'students.*' => 'required|integer|distinct|min:1',
            'subgroup_id' => 'nullable|integer|min:1', 'membership_version' => 'required|string|size:64']);
        DB::transaction(function () use ($course, $data, $request, $service) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if (!hash_equals($this->version($course), $data['membership_version'])) throw new ConflictHttpException('Состав курса изменился. Обновите страницу и повторите перевод.');
            $service->assign($course, $data['students'], isset($data['subgroup_id']) ? (int) $data['subgroup_id'] : null, $request->user()->id);
        });
        return back()->with('status', 'Состав подгрупп сохранён. Решения и баллы учеников сохранены.');
    }
}
