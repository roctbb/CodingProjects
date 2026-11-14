@if($workspaceNavigation['courses']->isNotEmpty())
    <li class="gc-sidebar__section-label">Текущие курсы</li>
    @foreach($workspaceNavigation['courses'] as $sidebarCourse)
        @php $courseActive = $workspaceNavigation['inCourses'] && $sidebarCourse->id === $workspaceNavigation['currentCourseId']; @endphp
        <li>
            <a class="gc-sidebar__link gc-sidebar__course-link {{ $courseActive ? 'active' : '' }}" href="{{ url('/insider/courses/'.$sidebarCourse->id) }}" @if($courseActive) aria-current="page" @endif title="{{ $sidebarCourse->name }}">
                <x-gc-course-icon :course="$sidebarCourse" />
                <span>{{ $sidebarCourse->name }}</span>
            </a>
        </li>
    @endforeach
    @if($workspaceNavigation['hasMoreCourses'])
        <li><a class="gc-sidebar__link gc-sidebar__all-courses" href="{{ url('/insider/courses') }}">Все мои курсы <i class="fas fa-chevron-right" aria-hidden="true"></i></a></li>
    @endif
@endif
