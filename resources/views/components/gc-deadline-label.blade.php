@props(['expiration', 'settled' => false, 'rule' => null, 'personal' => true, 'showTime' => true])

@php
    $precise = $rule instanceof \App\Support\DeadlineRule;
    $overdue = $personal && !$settled && ($precise ? $rule->cutoffAt() : $expiration->copy()->startOfDay()->addDay())->lte(now());
    $relative = $expiration->isToday() ? 'Сегодня' : ($expiration->isTomorrow() ? 'Завтра' : null);
@endphp
<time {{ $attributes->class(['gc-deadline-label', 'is-overdue' => $overdue, 'is-soon' => $personal && !$settled && !$overdue && $relative]) }} datetime="{{ $precise ? $expiration->toIso8601String() : $expiration->format('Y-m-d') }}" title="Срок сдачи: {{ $expiration->format($precise ? 'd.m.Y H:i' : 'd.m.Y') }}{{ $precise ? ' ('.$rule->ruleTimezone.')' : '' }}">
    @if($overdue)<i class="fas fa-clock" aria-hidden="true"></i>Просрочено · @elseif($personal && $relative && !$settled){{ $relative }} · @endif{{ $expiration->format($precise && $showTime ? 'd.m H:i' : 'd.m') }}
</time>
