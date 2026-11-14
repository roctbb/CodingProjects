@props(['rule'])
@if($rule instanceof \App\Support\DeadlineRule)
    <span class="d-inline-flex flex-wrap align-items-center gap-2 small" aria-label="Изменение баллов по срокам">
        @foreach($rule->series as $stage)
            @php($date = \Carbon\Carbon::parse($stage['at'], 'UTC')->setTimezone($rule->ruleTimezone))
            <span class="gc-meta-label"><time datetime="{{ $date->toIso8601String() }}">{{ $date->format('d.m H:i') }}</time> → {{ $stage['percent'] }}%</span>
        @endforeach
    </span>
@endif
