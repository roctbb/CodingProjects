@php
    $homePulse = $pulse ?? [];
    $pulseSeries = collect($homePulse['series'] ?? []);
    $maxPulseValue = max(0, (int) $pulseSeries->max('value'));
@endphp

@if($compact ?? false)
    <a class="gc-header-pulse {{ $maxPulseValue === 0 ? 'gc-header-pulse--empty' : '' }}" href="{{ url('/insider/pulse') }}" aria-label="Пульс курсов: {{ $homePulse['label'] ?? 'нет сигнала' }}" title="Пульс курсов · {{ $homePulse['label'] ?? 'нет сигнала' }}">
        @if($maxPulseValue > 0)
            <span class="gc-header-pulse__label">Пульс<span>{{ $homePulse['current'] ?? 0 }}</span></span>
            <x-gc-pulse-chart :pulse="$homePulse" :chart-width="220" :chart-height="58" :chart-padding="4" :filled="true" />
        @else
            <span class="gc-header-pulse__empty">
                <span class="gc-header-pulse__label">Пульс<span>{{ $homePulse['current'] ?? 0 }}</span></span>
                <span class="gc-help-text">Пока нет активности</span>
            </span>
        @endif
    </a>
@elseif($maxPulseValue === 0)
    <a class="gc-card gc-summary-link mb-4" href="{{ url('/insider/pulse') }}">
        <span><span class="fw-medium">Пульс курсов</span><span class="gc-help-text d-block">Пока нет активности</span></span>
        <i class="fas fa-arrow-right text-muted" aria-hidden="true"></i>
    </a>
@else
<a class="gc-card home-activity home-pulse-card home-pulse-card--{{ $homePulse['level'] ?? 'empty' }} mb-4"
   href="{{ url('/insider/pulse') }}"
   aria-label="Открыть пульс">
    <span class="home-pulse-card__title">
        <span class="home-activity__head-icon flex-shrink-0"><i class="fas fa-wave-square"></i></span>
        <span class="min-width-0">
            <span class="home-pulse-card__eyebrow">Пульс</span>
            <span class="home-pulse-card__label">{{ $homePulse['label'] ?? 'нет сигнала' }}</span>
        </span>
    </span>

    <span class="home-pulse-card__value">{{ $homePulse['current'] ?? 0 }}</span>

    <span class="home-pulse-card__chart" aria-hidden="true">
        <x-gc-pulse-chart :pulse="$homePulse" :chart-width="220" :chart-height="58" :chart-padding="4" :filled="true" line-class="home-pulse-card__line" area-class="home-pulse-card__area" />
    </span>

    <span class="home-card-arrow home-pulse-card__arrow"><i class="fas fa-arrow-right"></i></span>
</a>

@endif
