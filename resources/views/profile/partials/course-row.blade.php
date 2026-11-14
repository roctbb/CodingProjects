@php
    $entryName = $completedEntry?->name ?? $course->name;
    $canOpen = $course && ($guest->role === 'admin' || $course->students->contains('id', $guest->id) || $course->teachers->contains('id', $guest->id));
    $icon = $course ? \App\Support\CourseIcons::forCourse($course) : \App\Support\CourseIcons::resolve(null, $entryName);
@endphp
<article class="gc-profile-course">
    @if($canOpen)<a class="gc-profile-course__body" href="{{ url('/insider/courses/'.$course->id) }}">
    @else<div class="gc-profile-course__body">@endif
        <x-gc-course-icon :name="$icon" />
        <span class="min-width-0"><span class="gc-profile-course__name">{{ $entryName }}</span>@if($completedEntry && filled($completedEntry->mark))<small class="gc-help-text">Отметка: {{ $completedEntry->mark }}</small>@endif</span>
    @if($canOpen)</a>@else</div>@endif
    @if($guest->role === 'admin')
        @if($completedEntry?->id)
            <a class="btn btn-sm gc-icon-button text-danger" href="{{ url('/insider/profile/delete-course/'.$completedEntry->id) }}" data-confirm="Удалить завершённый курс?" aria-label="Удалить курс {{ $entryName }}"><i class="fas fa-times" aria-hidden="true"></i></a>
        @elseif(!$completedEntry && $label === 'Текущие курсы')
            <div class="dropdown"><button class="btn btn-sm gc-icon-button" data-bs-toggle="dropdown" aria-label="Действия с курсом {{ $entryName }}" aria-expanded="false"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item text-danger" href="{{ url('/insider/profile/'.$user->id.'/delete-course/'.$course->id) }}" data-confirm="Отчислить ученика с курса?">Отчислить с курса</a></div></div>
        @endif
    @endif
</article>
