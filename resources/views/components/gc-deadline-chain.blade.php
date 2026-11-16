@props(['rule', 'showTime' => true, 'compact' => false, 'note' => null])
@if($rule instanceof \App\Support\DeadlineRule && count($rule->series))
    @php
        $deadlineNow = now();
        $deadlineNowMs = $deadlineNow->getTimestampMs();
        $deadlineStages = collect($rule->series)->map(function ($stage) use ($rule, $showTime) {
            $date = $rule->deadlineAt($stage);
            return ['cutoff' => \Carbon\Carbon::parse($stage['at'], 'UTC')->getTimestampMs(),
                'percent' => $stage['percent'], 'date' => $date->format($showTime ? 'd.m H:i' : 'd.m'),
                'datetime' => $date->toIso8601String()];
        });
        $deadlinePenalty = (float) round(100 - $rule->at($deadlineNow)->penalty * 100, 2);
        $nextDeadline = $deadlineStages->first(fn ($stage) => $rule->legacyBoundary ? $stage['cutoff'] >= $deadlineNowMs : $stage['cutoff'] > $deadlineNowMs);
        $calendarDeadline = $nextDeadline ?? $deadlineStages->last();
    @endphp
    <x-gc-deadline-popover class="gc-deadline-summary {{ $compact ? 'gc-deadline-summary--compact' : '' }}" data-deadline-summary data-server-now="{{ $deadlineNowMs }}" data-legacy-boundary="{{ $rule->legacyBoundary ? 'true' : 'false' }}">
        <x-slot:summary>
            @if($compact)
                <span class="visually-hidden">Сроки сдачи: </span>
                <i class="fas fa-calendar-days" aria-hidden="true"></i>
                <time data-deadline-calendar data-deadline-next-date datetime="{{ $calendarDeadline['datetime'] }}">{{ $calendarDeadline['date'] }}</time>
            @else
            <span data-deadline-current>{{ $deadlinePenalty ? 'Сейчас −'.$deadlinePenalty.'%' : 'Без штрафа' }}</span>
            <span data-deadline-next @if(!$nextDeadline) hidden @endif>· После <time data-deadline-next-date datetime="{{ $nextDeadline['datetime'] ?? '' }}">{{ $nextDeadline['date'] ?? '' }}</time> → <span data-deadline-next-penalty>−{{ (float) round(100 - ($nextDeadline['percent'] ?? 100), 2) }}%</span></span>
            @endif
        </x-slot:summary>
        @if($compact)<p class="gc-help-text mb-2" data-deadline-current>{{ $deadlinePenalty ? 'Сейчас −'.$deadlinePenalty.'%' : 'Без штрафа' }}</p>@endif
        @if($note)<p class="gc-help-text mb-2">{{ $note }}</p>@endif
        <ul aria-label="Все сроки сдачи">
            @foreach($deadlineStages as $stage)
                <li data-deadline-stage data-cutoff="{{ $stage['cutoff'] }}" data-percent="{{ $stage['percent'] }}" data-date="{{ $stage['date'] }}" data-datetime="{{ $stage['datetime'] }}">После <time datetime="{{ $stage['datetime'] }}">{{ $stage['date'] }}</time> — штраф {{ (float) round(100 - $stage['percent'], 2) }}%</li>
            @endforeach
        </ul>
    </x-gc-deadline-popover>
@endif
