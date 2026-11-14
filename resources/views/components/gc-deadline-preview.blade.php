@props(['items', 'allUrl' => null, 'showCourse' => false, 'embedded' => false, 'limit' => 3])

@if($items->isNotEmpty())
    @php
        $overdueItems = $items->where('is_overdue', true);
        $futureItems = $items->where('is_overdue', false);
        $previewItems = $futureItems->isEmpty()
            ? $overdueItems->sortByDesc('expiration')->take($limit)
            : $overdueItems->sortByDesc('expiration')->take(1)->concat($futureItems->take($overdueItems->isEmpty() ? $limit : $limit - 1));
    @endphp
    <x-gc-panel title="Сроки сдачи" icon="fas fa-flag" :embedded="$embedded" {{ $attributes }}>
        <x-slot:actions>
            @if($overdueItems->count())<span class="gc-help-text text-danger">Просрочено: {{ $overdueItems->count() }}</span>@endif
            @if($allUrl)<a class="gc-quiet-link small" href="{{ $allUrl }}">Все сроки · {{ $items->count() }}</a>@endif
        </x-slot:actions>
        <ul class="list-unstyled gc-rows mb-0">
            @foreach($previewItems as $deadline)
                <li>
                    <a class="gc-deadline-row" href="{{ url('/insider/courses/'.$deadline->course_id.'/steps/'.$deadline->task->step_id.'#task'.$deadline->task_id) }}">
                        <time class="gc-deadline-row__date @if($deadline->is_overdue) text-danger @endif" datetime="{{ $deadline->expiration->format('Y-m-d') }}" title="{{ $deadline->expiration->format('d.m.Y') }}">{{ $deadline->expiration->format('d.m') }}</time>
                        <span class="min-width-0"><strong>{{ $deadline->task->name }}</strong><small>{{ $showCourse ? $deadline->course->name : $deadline->task->step->lesson->name }}</small></span>
                        @if($deadline->is_overdue || $deadline->is_today || $deadline->expiration->isTomorrow() || $deadline->is_soon)
                        <span class="gc-deadline-row__state @if($deadline->is_overdue) text-danger @elseif($deadline->is_today) text-primary @endif">
                            @if($deadline->is_overdue)Просрочено
                            @elseif($deadline->is_today)Сегодня
                            @elseif($deadline->expiration->isTomorrow())Завтра
                            @elseif($deadline->is_soon)Скоро
                            @endif
                        </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        @if(!$allUrl && $showCourse)
            <div class="gc-actions mt-2">
                @foreach($items->pluck('course')->unique('id') as $deadlineCourse)
                    <a class="gc-quiet-link small" href="{{ route('courses.deadlines', $deadlineCourse->id) }}">Все сроки · {{ $deadlineCourse->name }}</a>
                @endforeach
            </div>
        @endif
    </x-gc-panel>
@endif
