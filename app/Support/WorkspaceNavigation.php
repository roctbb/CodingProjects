<?php

namespace App\Support;

use App\Course;
use App\User;
use Illuminate\Http\Request;

class WorkspaceNavigation
{
    public function forUser(?User $user, Request $request): array
    {
        if (!$user) {
            return ['items' => [], 'courses' => collect(), 'hasMoreCourses' => false, 'currentCourseId' => null];
        }

        $teacher = in_array($user->role, ['teacher', 'admin'], true);
        preg_match('~^insider/courses/([1-9][0-9]*)(?:/|$)~', $request->path(), $matches);
        $currentCourseId = isset($matches[1]) ? (int) $matches[1] : null;
        $coursesQuery = Course::query()->where('state', 'started')
            ->when($user->role !== 'admin', fn ($query) => $query->where(function ($query) use ($user) {
                $query->whereHas('students', fn ($members) => $members->where('users.id', $user->id))
                    ->orWhereHas('teachers', fn ($members) => $members->where('users.id', $user->id));
            }));
        $shortlist = (clone $coursesQuery)->orderBy('name')->orderBy('id')->limit(9)->get();
        $hasMoreCourses = $shortlist->count() > 8;
        $courses = $shortlist->take(8);
        // Keep the open course reachable even when it is beyond the shortcuts.
        if ($currentCourseId && !$courses->contains('id', $currentCourseId)) {
            $current = (clone $coursesQuery)->whereKey($currentCourseId)->first();
            if ($current) {
                $courses = $courses->take(7)->push($current)->sortBy('name')->values();
            }
        }

        $review = $teacher && ($request->is('insider/reviews*') || preg_match('~^insider/courses/[0-9]+/tasks/[0-9]+/student/[0-9]+/?$~', $request->path()));
        $profileId = $request->segment(3);
        $ownProfile = $request->is('insider/profile') || ($request->is('insider/profile/*') && (string) $profileId === (string) $user->id);
        $inCourses = $request->is('insider/courses', 'insider/courses/*') && !$review;
        $items = [
            ['key' => 'courses', 'label' => 'Мои курсы', 'shortLabel' => 'Курсы', 'icon' => 'graduation-cap', 'path' => '/insider/courses', 'active' => $inCourses, 'shortcutActive' => $inCourses && !$courses->contains('id', $currentCourseId)],
            ['key' => 'pulse', 'label' => 'Пульс', 'shortLabel' => 'Пульс', 'icon' => 'wave-square', 'path' => '/insider/pulse', 'active' => $request->is('insider/pulse*')],
        ];
        if ($teacher) {
            $items[] = ['key' => 'reviews', 'label' => 'Проверка', 'shortLabel' => 'Проверка', 'icon' => 'code-branch', 'path' => '/insider/reviews', 'active' => (bool) $review];
        }
        $items[] = ['key' => 'market', 'label' => 'Магазин', 'shortLabel' => 'Магазин', 'icon' => 'store', 'path' => '/insider/market', 'active' => $request->is('insider/market*')];
        $items[] = ['key' => 'community', 'label' => 'Сообщество', 'shortLabel' => 'Люди', 'icon' => 'users', 'path' => '/insider/community', 'active' => $request->is('insider/community*') || ($request->is('insider/profile/*') && !$ownProfile)];
        $items[] = ['key' => 'profile', 'label' => 'Профиль', 'shortLabel' => 'Профиль', 'icon' => 'user', 'path' => '/insider/profile', 'active' => $ownProfile];

        return compact('items', 'courses', 'hasMoreCourses', 'currentCourseId') + ['inCourses' => $inCourses];
    }
}
