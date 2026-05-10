@php
    $isInsider = \Request::is('insider/*');
    $isManager = $isInsider && isset($course) && ($course->teachers->contains($user) || $user->role == 'admin');
    $stepBaseUrl = $isInsider && $course ? '/insider/courses/' . $course->id . '/steps/' : '/open/steps/';
    $courseBackUrl = $isInsider && $course ? url('/insider/courses/' . $course->id . '?chapter=' . $step->lesson->chapter->id) : null;
@endphp

<nav class="step-sidebar" id="stepsSidebar">
    <ul class="nav nav-pills flex-column step-sidebar__brand-list">
        <li class="nav-item">
            <a class="nav-link step-sidebar__brand" href="{{ $courseBackUrl ?: url('/') }}">
                @if ($isInsider)
                    <i class="icon ion-chevron-left step-sidebar__brand-back"></i>
                @endif
                <img src="{{ url('images/icons/icons8-idea-64.png') }}" height="32" alt=""/>
                <span>GeekClass</span>
            </a>
        </li>
    </ul>

    <ul class="nav nav-pills flex-column step-sidebar__list">
        @foreach($step->lesson->steps as $lesson_step)
            @php
                $visibleSidebarTasks = $isManager || !$isInsider
                    ? $lesson_step->tasks
                    : $lesson_step->tasks->filter(fn ($task) => $task->isVisible($user, $course));
            @endphp
            <li class="nav-item">
                <a class="nav-link @if ($lesson_step->id == $step->id) active @endif"
                   href="{{ url($stepBaseUrl . $lesson_step->id) }}">
                    <span class="step-sidebar__link-label">{{ $lesson_step->name }}</span>
                    @if ($isInsider && $visibleSidebarTasks->count() != 0)
                        <i class="ion ion-trophy"></i>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if ($isManager)
        <p class="mt-3 mb-0">
            <a href="{{ url('/insider/courses/' . $course->id . '/lessons/' . $step->lesson->id . '/create') }}"
               class="btn btn-success btn-sm rounded-3 fw-semibold w-100 step-sidebar__add">Новый этап</a>
        </p>
    @endif
</nav>
