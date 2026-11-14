@props(['expiration', 'settled' => false])

@php
    $overdue = !$settled && $expiration->copy()->addDay()->lte(now());
    $relative = $expiration->isToday() ? 'Сегодня' : ($expiration->isTomorrow() ? 'Завтра' : null);
@endphp
<time {{ $attributes->class(['gc-deadline-label', 'is-overdue' => $overdue, 'is-soon' => !$settled && !$overdue && $relative]) }} datetime="{{ $expiration->format('Y-m-d') }}" title="Срок сдачи: {{ $expiration->format('d.m.Y') }}">
    @if($overdue)<i class="fas fa-clock" aria-hidden="true"></i>Просрочено · @elseif($relative && !$settled){{ $relative }} · @endif{{ $expiration->format('d.m') }}
</time>
