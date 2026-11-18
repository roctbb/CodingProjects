<?php

namespace App\Http\Controllers\CoursePlanner;

use App\Course;
use App\CoursePlanner\Models\PlannerSession;
use App\CoursePlanner\Models\SessionResource;
use App\CoursePlanner\Providers\ProviderException;
use App\CoursePlanner\Providers\ProviderRegistry;
use App\CoursePlanner\Services\CalendarService;
use App\CoursePlanner\Services\PlannerReader;
use App\CoursePlanner\Services\PlannerService;
use App\Http\Controllers\Controller;
use App\Policies\CoursePlannerPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PlannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function course(Request $request, int $id, bool $write = true): Course
    {
        abort_unless(config('course_planner.enabled'), 404);
        $course = Course::findOrFail($id);
        abort_unless($write ? app(CoursePlannerPolicy::class)->manage($request->user(), $course) : app(CoursePlannerPolicy::class)->view($request->user(), $course), 403);
        return $course;
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('planner_courses') && Schema::hasColumn('planner_sessions', 'comment');
    }

    private function ready(): void
    {
        abort_unless($this->schemaReady(), 503, 'Планировщик ожидает применения миграций.');
    }

    public function page(Request $request, int $course_id)
    {
        $course = $this->course($request, $course_id, false);
        if (!app(CoursePlannerPolicy::class)->manage($request->user(), $course)) {
            return redirect()->route('courses.deadlines', $course->id);
        }
        return view('course-planner.index', ['course' => $course, 'ready' => $this->schemaReady()]);
    }

    public function state(Request $request, int $course_id, PlannerService $service, PlannerReader $reader)
    {
        $course = $this->course($request, $course_id, false);
        $this->ready();
        $input = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'layer' => 'nullable|in:plan,fact,workspace']);
        $planner = $service->planner($course);
        $anchor = CarbonImmutable::now($planner->timezone)->toDateString();
        if ($planner->starts_on && $planner->starts_on > $anchor) $anchor = $planner->starts_on;
        if ($planner->ends_on && $planner->ends_on < $anchor) $anchor = $planner->ends_on;
        $workspace = ($input['layer'] ?? 'plan') === 'workspace';
        $from = $input['from'] ?? ($workspace ? CarbonImmutable::parse($anchor)->startOfWeek()->toDateString() : ($planner->starts_on ?: CarbonImmutable::now($planner->timezone)->startOfWeek()->toDateString()));
        $to = $input['to'] ?? CarbonImmutable::parse($from)->addDays($workspace ? 6 : 41)->toDateString();
        if ($to < $from || CarbonImmutable::parse($from)->diffInDays($to) > 93) {
            throw ValidationException::withMessages(['to' => 'Загрузите календарь частями не более трёх месяцев.']);
        }
        $data = $reader->state($course, $planner, $from, $to, $input['layer'] ?? 'plan', $this->plannerIds($course, $planner));
        if (!app(CoursePlannerPolicy::class)->manage($request->user(), $course)) $data = $this->studentData($course, $data, $request);
        return response()->json($data);
    }

    private function plannerIds(Course $course, $planner): array
    {
        if (!Schema::hasColumn('planner_courses', 'audience_id')) return [$planner->id];
        $scope = \App\Support\CourseScope::for($course);
        $query = \App\CoursePlanner\Models\PlannerCourse::where('course_id', $course->id);
        if (!$scope->manager || $scope->key !== 'all') $query->whereIn('audience_id', array_unique([0, $scope->audienceId()]));
        return $query->pluck('id')->all();
    }

    private function studentData(Course $course, array $data, Request $request): array
    {
        $data['history'] = $data['exceptions'] = $data['providers'] = [];
        $data['summary'] = ['rows' => [], 'totals' => [], 'weeks' => []];
        $data['lessons'] = array_values(array_filter($data['lessons'], fn ($lesson) => \App\Lesson::find($lesson['id'])?->isAvailableForUser($course, $request->user())));
        $allowedLessons = array_column($data['lessons'], 'id');
        foreach ($data['sessions'] as &$session) {
            $session['editable'] = $session['can_comment'] = false;
            $session['comment'] = $session['record'] = null;
            $session['entries'] = array_values(array_filter($session['entries'], function ($entry) use ($course, $request, $allowedLessons) {
                if ($entry['layer'] !== 'plan') return false;
                if ($entry['kind'] !== 'resource') return true;
                $resource = $entry['resource'];
                if (($resource['provider_key'] ?? '') !== 'codingprojects') return true;
                if ($resource['type'] === 'task') {
                    $task = \App\Task::with('step.lesson')->find($resource['external_id']);
                    return $task && in_array($task->step?->lesson_id, $allowedLessons) && $task->isVisible($request->user(), $course);
                }
                $lessonId = $resource['type'] === 'lesson' ? (int) $resource['external_id'] : \App\ProgramStep::find($resource['external_id'])?->lesson_id;
                return in_array($lessonId, $allowedLessons);
            }));
            foreach ($session['entries'] as &$entry) {
                unset($entry['note'], $entry['fact_state']);
                if (isset($entry['resource'])) $entry['resource'] = array_intersect_key($entry['resource'], array_flip(['provider_key','type','type_label','external_id','title','open_url','child_count','points']));
            }
            unset($entry);
        }
        unset($session);
        $data['deadlines'] = array_values(array_filter($data['deadlines'], function ($deadline) use ($allowedLessons, $request, $course) {
            if ($deadline['lesson_id'] && !in_array($deadline['lesson_id'], $allowedLessons)) return false;
            return $deadline['provider_key'] !== 'codingprojects' || \App\Task::find($deadline['external_id'])?->isVisible($request->user(), $course);
        }));
        return $data;
    }

    public function copyTargets(Request $request, int $course_id)
    {
        $course = $this->course($request, $course_id);
        $input = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $rows = \Illuminate\Support\Facades\DB::table('planner_sessions as s')->join('planner_courses as p', 'p.id', '=', 's.planner_course_id')
            ->leftJoin('planner_calendar_slots as c', 'c.id', '=', 's.calendar_slot_id')->where('p.course_id', $course->id)
            ->where(fn ($q) => $q->whereNull('s.calendar_slot_id')->orWhereBetween('c.local_date', [$input['from'], $input['to']]))
            ->orderBy('c.local_date')->orderBy('c.start_time')->limit(200)
            ->get(['s.id', 's.title', 'c.local_date', 'c.start_time', 'p.revision', Schema::hasColumn('planner_courses', 'audience_id') ? 'p.audience_id' : \Illuminate\Support\Facades\DB::raw('0 as audience_id')]);
        $groups = app(\App\Services\CourseSubgroups::class)->available() ? $course->subgroups->keyBy('id') : collect();
        return response()->json(['items' => $rows->map(fn ($row) => (array) $row + ['audience_name' => $groups->get($row->audience_id)?->name ?? 'Весь класс'])]);
    }

    public function command(Request $request, int $course_id, string $operation, PlannerService $service)
    {
        $course = $this->course($request, $course_id);
        $this->ready();
        return $this->providerResponse(fn () => response()->json($service->command($course, $request->user(), $operation, $request->all())));
    }

    public function preview(Request $request, int $course_id, PlannerService $service, CalendarService $calendar)
    {
        $course = $this->course($request, $course_id);
        $this->ready();
        return response()->json($calendar->preview($service->planner($course), $request->all()));
    }

    public function catalog(Request $request, int $course_id, string $provider_key, PlannerService $service, ProviderRegistry $providers)
    {
        $course = $this->course($request, $course_id);
        $this->ready();
        $query = $request->validate(['query' => 'nullable|string|max:200', 'type' => 'nullable|string|max:80',
            'cursor' => 'nullable|string|max:4096', 'limit' => 'nullable|integer|min:1|max:50',
            'parent_type' => 'nullable|string|max:80', 'parent_external_id' => 'nullable|string|max:190',
            'chapter' => 'nullable|integer|min:0']);
        $query = array_filter($query, fn ($v) => $v !== null);
        return $this->providerResponse(function () use ($providers, $service, $course, $request, $provider_key, $query) {
            $driver = $providers->driver($provider_key, $service->planner($course));
            return response()->json(['manifest' => $driver->manifest($course, $request->user()), 'catalog' => $driver->catalog($course, $request->user(), $query + ['limit' => 25])]);
        });
    }

    public function entry(Request $request, int $course_id, int $link_id, PlannerService $service)
    {
        $course = $this->course($request, $course_id);
        $this->ready();
        $link = SessionResource::whereIn('session_id', PlannerSession::where('planner_course_id', $service->planner($course)->id)->select('id'))->whereKey($link_id)->firstOrFail();
        return response()->json(['resource' => $link->resource_snapshot]);
    }

    public function details(Request $request, int $course_id, PlannerService $service, PlannerReader $reader)
    {
        $course = $this->course($request, $course_id);
        $this->ready();
        $data = $request->validate(['layer' => 'required|in:plan,fact,workspace', 'lesson_id' => 'required|integer|min:0',
            'provider' => 'required|string|max:80', 'page' => 'nullable|integer|min:1|max:10000']);
        $planner = $service->planner($course);
        return response()->json($reader->details($planner, $data['layer'], (int) $data['lesson_id'], $data['provider'], (int) ($data['page'] ?? 1), $this->plannerIds($course, $planner)));
    }

    private function providerResponse(callable $callback)
    {
        try {
            return $callback();
        } catch (ProviderException $exception) {
            return response()->json(['code' => $exception->errorCode, 'message' => $exception->getMessage()], $exception->status);
        }
    }
}
