@if($course->state === 'started' && $courseDeadlines->isNotEmpty())
    @php
        $dueToday = $courseDeadlines->where('is_today', true)->count();
        $nearest = $courseDeadlines->first();
        $dueLabel = $dueToday ? 'Срок сегодня: '.$dueToday : 'Ближайший срок: '.$nearest->expiration->format('d.m');
    @endphp
    <a class="gc-course-due-summary" href="{{ route('courses.deadlines', ['id' => $course->id, 'chapter' => $chapter->id]) }}" aria-label="{{ $dueLabel }}. Все сроки">
        <i class="fas fa-calendar-days" aria-hidden="true"></i>
        <span><strong>{{ $dueLabel }}</strong><small>{{ $nearest->task->name }}</small></span>
        <span class="gc-course-due-summary__count">Все сроки</span>
    </a>
@endif
