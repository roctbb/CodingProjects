@if($course->state === 'started' && $courseDeadlines->isNotEmpty())
    @php
        $dueToday = $courseDeadlines->where('is_today', true)->count();
        $overdue = $courseDeadlines->where('is_overdue', true)->count();
        $nearest = !$isManager && $overdue ? $courseDeadlines->firstWhere('is_overdue', true)
            : ($courseDeadlines->first(fn ($item) => !$item->is_overdue) ?? $courseDeadlines->first());
        $dueLabel = !$isManager && $overdue ? 'Просрочено: '.$overdue
            : ($dueToday ? 'Срок сегодня: '.$dueToday : ($nearest->is_overdue ? 'Прошедшие сроки: '.$overdue : 'Ближайший срок: '.$nearest->expiration->format('d.m')));
    @endphp
    <a class="gc-course-due-summary @if(!$isManager && $overdue) is-overdue @endif" href="{{ route('courses.deadlines', ['id' => $course->id, 'chapter' => $chapter->id]) }}" aria-label="{{ $dueLabel }}. Все сроки: {{ $courseDeadlines->count() }}">
        <i class="fas fa-calendar-days" aria-hidden="true"></i>
        <span><strong>{{ $dueLabel }}</strong><small>{{ $nearest->task->name }}</small></span>
        <span class="gc-course-due-summary__count">Все сроки · {{ $courseDeadlines->count() }}</span>
    </a>
@endif
