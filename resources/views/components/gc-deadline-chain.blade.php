@props(['rule', 'showTime' => true])
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
    @endphp
    <details class="gc-deadline-summary" data-deadline-summary data-server-now="{{ $deadlineNowMs }}" data-legacy-boundary="{{ $rule->legacyBoundary ? 'true' : 'false' }}">
        <summary aria-label="Текущий штраф и следующий срок. Показать все сроки">
            <span data-deadline-current>{{ $deadlinePenalty ? 'Сейчас −'.$deadlinePenalty.'%' : 'Без штрафа' }}</span>
            <span data-deadline-next @if(!$nextDeadline) hidden @endif>· После <time data-deadline-next-date datetime="{{ $nextDeadline['datetime'] ?? '' }}">{{ $nextDeadline['date'] ?? '' }}</time> → <span data-deadline-next-penalty>−{{ (float) round(100 - ($nextDeadline['percent'] ?? 100), 2) }}%</span></span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </summary>
        <ul aria-label="Все сроки сдачи">
            @foreach($deadlineStages as $stage)
                <li data-deadline-stage data-cutoff="{{ $stage['cutoff'] }}" data-percent="{{ $stage['percent'] }}" data-date="{{ $stage['date'] }}" data-datetime="{{ $stage['datetime'] }}">После <time datetime="{{ $stage['datetime'] }}">{{ $stage['date'] }}</time> — штраф {{ (float) round(100 - $stage['percent'], 2) }}%</li>
            @endforeach
        </ul>
    </details>
@endif
