@props(['rule', 'showTime' => true])
@if($rule instanceof \App\Support\DeadlineRule)
    <span class="d-inline-flex flex-wrap align-items-center gap-2 small" aria-label="Изменение баллов по срокам">
        @foreach($rule->series as $stage)
            @php($date = $rule->deadlineAt($stage))
            <span class="gc-meta-label">После <time datetime="{{ $date->toIso8601String() }}">{{ $date->format($showTime ? 'd.m H:i' : 'd.m') }}</time> → {{ $stage['percent'] }}%</span>
        @endforeach
    </span>
@endif
