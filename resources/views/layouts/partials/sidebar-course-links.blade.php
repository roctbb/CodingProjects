@if (Auth::check())
    @php
        $sidebarUser = Auth::user();
        $sidebarIsLearner = !in_array($sidebarUser->role, ['teacher', 'admin']);

        $sidebarUser->loadMissing([
            'courses' => function ($query) {
                $query->select('courses.id', 'courses.name', 'courses.state');
            },
            'managed_courses' => function ($query) {
                $query->select('courses.id', 'courses.name', 'courses.state');
            },
            'completedCourses' => function ($query) {
                $query->select('id', 'name', 'mark', 'course_id', 'user_id')->orderByDesc('id');
            },
        ]);

        if ($sidebarUser->role == 'admin') {
            $sidebarCurrentCourses = \App\Course::where('state', 'started')
                ->orderBy('name')
                ->get(['id', 'name', 'state']);
        } else {
            $sidebarCurrentCourses = $sidebarUser->courses
                ->merge($sidebarUser->managed_courses)
                ->where('state', 'started')
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        $sidebarAttachedCourseIds = $sidebarUser->courses
            ->merge($sidebarUser->managed_courses)
            ->pluck('id')
            ->unique();

        $sidebarCompletedCourses = $sidebarIsLearner
            ? $sidebarUser->completedCourses->take(5)
            : collect();
    @endphp

    @if ($sidebarCurrentCourses->count() > 0 && $sidebarCurrentCourses->count() <= 5)
        <li class="gc-sidebar__section-label gc-sidebar__section-label--compact">Текущие</li>
        @foreach ($sidebarCurrentCourses as $sidebarCourse)
            <li>
                <a class="gc-sidebar__link gc-sidebar__course-link {{ Request::is('insider/courses/'.$sidebarCourse->id.'*') ? 'active' : '' }}" href="{{ url('/insider/courses/'.$sidebarCourse->id) }}">
                    <i class="fas fa-book-open"></i>
                    <span>{{ $sidebarCourse->name }}</span>
                </a>
            </li>
        @endforeach
    @endif

    @if ($sidebarCompletedCourses->count())
        <li class="gc-sidebar__section-label gc-sidebar__section-label--compact">Пройденные</li>
        @foreach ($sidebarCompletedCourses as $sidebarCompletedCourse)
            @php
                $sidebarCompletedCourseUrl = $sidebarCompletedCourse->course_id && $sidebarAttachedCourseIds->contains($sidebarCompletedCourse->course_id)
                    ? url('/insider/courses/'.$sidebarCompletedCourse->course_id)
                    : null;
            @endphp
            <li>
                @if ($sidebarCompletedCourseUrl)
                    <a class="gc-sidebar__link gc-sidebar__course-link" href="{{ $sidebarCompletedCourseUrl }}">
                        <i class="fas fa-check"></i>
                        <span>{{ $sidebarCompletedCourse->name }}</span>
                        @if ($sidebarCompletedCourse->mark)
                            <small>{{ $sidebarCompletedCourse->mark }}</small>
                        @endif
                    </a>
                @else
                    <span class="gc-sidebar__link gc-sidebar__course-link gc-sidebar__course-link--static">
                        <i class="fas fa-check"></i>
                        <span>{{ $sidebarCompletedCourse->name }}</span>
                        @if ($sidebarCompletedCourse->mark)
                            <small>{{ $sidebarCompletedCourse->mark }}</small>
                        @endif
                    </span>
                @endif
            </li>
        @endforeach
        @if ($sidebarUser->completedCourses->count() > $sidebarCompletedCourses->count())
            <li>
                <a class="gc-sidebar__link gc-sidebar__course-link gc-sidebar__course-link--muted" href="{{ url('/insider/profile') }}">
                    <i class="fas fa-ellipsis-h"></i>
                    <span>Все пройденные</span>
                </a>
            </li>
        @endif
    @endif
@endif
