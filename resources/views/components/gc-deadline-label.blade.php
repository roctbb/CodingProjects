@props(['expiration', 'settled' => false, 'rule' => null])

@php
    $precise = $rule instanceof \App\Support\DeadlineRule;
    $overdue = !$settled && ($precise ? $rule->cutoffAt() : $expiration->copy()->startOfDay()->addDay())->lte(now());
    $relative = $expiration->isToday() ? 'Сегодня' : ($expiration->isTomorrow() ? 'Завтра' : null);
@endphp
<time {{ $attributes->class(['gc-deadline-label', 'is-overdue' => $overdue, 'is-soon' => !$settled && !$overdue && $relative]) }} datetime="{{ $precise ? $expiration->toIso8601String() : $expiration->format('Y-m-d') }}" title="Срок сдачи: {{ $expiration->format($precise ? 'd.m.Y H:i' : 'd.m.Y') }}{{ $precise ? ' ('.$rule->ruleTimezone.')' : '' }}">
    @if($overdue)<i class="fas fa-clock" aria-hidden="true"></i>Просрочено · @elseif($relative && !$settled){{ $relative }} · @endif{{ $expiration->format($precise ? 'd.m H:i' : 'd.m') }}
</time>
