@if (Auth::check() && in_array(Auth::user()->role, ['teacher', 'admin']))
    @php
        $sidebarUser = Auth::user();

        $sidebarUser->loadMissing([
            'courses' => function ($query) {
                $query->select('courses.id', 'courses.name', 'courses.state');
            },
            'managed_courses' => function ($query) {
                $query->select('courses.id', 'courses.name', 'courses.state');
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

@endif
