@php
    $isInsider = \Request::is('insider/*');
    $isManager = $isInsider && isset($course) && ($course->teachers->contains($user) || $user->role == 'admin');
    $stepBaseUrl = $isInsider && $course ? '/insider/courses/' . $course->id . '/steps/' : '/open/steps/';
    $courseBackUrl = $isInsider && $course ? url('/insider/courses/' . $course->id . '?chapter=' . $step->lesson->chapter->id) : null;
@endphp

<nav class="step-sidebar" id="stepsSidebar">
    <ul class="nav nav-pills flex-column step-sidebar__brand-list">
        <li class="nav-item">
            @if ($isInsider)
                <a class="nav-link step-sidebar__brand" href="{{ $courseBackUrl }}">
                    <i class="icon ion-chevron-left"></i>
                    <img src="{{ url('images/bhlogo.png') }}" height="35" alt=""/>
                </a>
            @else
                <span class="nav-link step-sidebar__brand">
                    <img src="{{ url('images/bhlogo.png') }}" height="35" alt=""/>
                </span>
            @endif
        </li>
    </ul>

    <ul class="nav nav-pills flex-column step-sidebar__list">
        @foreach($step->lesson->steps as $lesson_step)
            <li class="nav-item">
                <a class="nav-link @if ($lesson_step->id == $step->id) active @endif"
                   href="{{ url($stepBaseUrl . $lesson_step->id) }}">{{ $lesson_step->name }}
                    @if ($isInsider && $lesson_step->tasks->count() != 0)
                        <i class="ion ion-trophy"></i>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    @if ($isManager)
        <p class="mt-3 text-center">
            <a href="{{ url('/insider/courses/' . $course->id . '/lessons/' . $step->lesson->id . '/create') }}"
               class="btn btn-success btn-sm">Новый этап</a>
        </p>
    @endif
</nav>
