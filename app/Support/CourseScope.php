<?php

namespace App\Support;

use App\Course;
use App\CourseSubgroup;
use App\Services\CourseAccess;
use App\Services\CourseSubgroups;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** One validated participant selection, shared by HTML, JSON and exports. */
class CourseScope
{
    public function __construct(public Course $course, public string $key = 'all', public ?CourseSubgroup $group = null, public bool $manager = true, public ?int $viewerId = null) {}

    public static function for(Course $course, ?User $viewer = null, ?Request $request = null): self
    {
        $viewer ??= auth()->user();
        $request ??= request();
        $service = app(CourseSubgroups::class);
        $manager = $viewer && app(CourseAccess::class)->manages($viewer, $course);
        if (!$service->available()) return new self($course, 'all', null, (bool) $manager, $viewer?->id);
        if (!$manager) {
            $id = $viewer ? $service->groupFor($course->id, $viewer->id) : null;
            $group = $id ? CourseSubgroup::where('course_id', $course->id)->find($id) : null;
            return new self($course, $group ? (string) $group->id : ($course->subgroups()->exists() ? 'self' : 'all'), $group, false, $viewer?->id);
        }
        $value = $request->input('subgroup', 'all');
        abort_unless(is_scalar($value) && ($value === 'all' || $value === 'none' || preg_match('/^[1-9]\d*$/D', (string) $value)), 422);
        $group = ctype_digit((string) $value) ? $course->subgroups()->findOrFail((int) $value) : null;
        return new self($course, (string) $value, $group, true, $viewer?->id);
    }

    public function audienceId(): int { return (int) ($this->group?->id ?? 0); }
    public function label(): string { return $this->group?->name ?? match ($this->key) { 'none' => 'Без подгруппы', 'self' => 'Подгруппа ещё не назначена', default => 'Весь класс' }; }

    public function query($query, string $column = 'users.id')
    {
        if ($this->key === 'all') return $query;
        if ($this->key === 'self') return $query->where($column, $this->viewerId);
        $members = DB::table('course_subgroup_memberships')->where('course_id', $this->course->id)->whereNotNull('active_user_id')->select('user_id');
        if ($this->group) return $query->whereIn($column, $members->where('subgroup_id', $this->group->id));
        return $query->whereNotIn($column, $members);
    }

    public function students() { return $this->query($this->course->statsStudents())->get(); }
    public function filter($students) { if ($this->key === 'all') return $students; $ids = $this->query($this->course->students())->pluck('users.id'); return $students->whereIn('id', $ids)->values(); }
    public function params(): array { return $this->manager && $this->key !== 'all' ? ['subgroup' => $this->key] : []; }
    public function url(string $url): string
    {
        if (!$this->params()) return $url;
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query($this->params()).($fragment !== null ? '#'.$fragment : '');
    }
}
