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

    private function course(Request $request, int $id): Course
    {
        abort_unless(config('course_planner.enabled'), 404);
        $course = Course::findOrFail($id);
        abort_unless(app(CoursePlannerPolicy::class)->manage($request->user(), $course), 403);
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
        $course = $this->course($request, $course_id);
        return view('course-planner.index', ['course' => $course, 'ready' => $this->schemaReady()]);
    }

    public function state(Request $request, int $course_id, PlannerService $service, PlannerReader $reader)
    {
        $course = $this->course($request, $course_id);
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
        return response()->json($reader->state($course, $planner, $from, $to, $input['layer'] ?? 'plan'));
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
            'parent_type' => 'nullable|string|max:80', 'parent_external_id' => 'nullable|string|max:190']);
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
        return response()->json($reader->details($service->planner($course), $data['layer'], (int) $data['lesson_id'], $data['provider'], (int) ($data['page'] ?? 1)));
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
