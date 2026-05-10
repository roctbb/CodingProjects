@extends('layouts.left-menu')

@section('title', 'Пульс')

@section('content')
    <div class="gc-title-row gc-title-row--center">
        <div class="min-width-0">
            <span class="text-muted text-uppercase fw-bold font-monospace small d-block mb-1">workspace</span>
            <h2 class="mb-1">Пульс</h2>
        </div>

        <a class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2" href="{{ url('/insider/courses') }}">
            <i class="fas fa-graduation-cap me-1"></i>Мои курсы
        </a>
    </div>

    <section class="gc-card pulse-feed">
        <div class="pulse-feed__head">
            <div class="d-flex align-items-center gap-2 min-width-0">
                <span class="pulse-feed__head-icon"><i class="fas fa-wave-square"></i></span>
                <h5 class="mb-0">Все события</h5>
            </div>
            <span class="badge rounded-pill bg-body-tertiary">{{ $activities->total() }}</span>
        </div>

        @php
            $pulseSeries = collect($pulse['series'] ?? []);
            $chartWidth = 320;
            $chartHeight = 92;
            $chartPadding = 7;
            $chartInnerWidth = $chartWidth - ($chartPadding * 2);
            $chartInnerHeight = $chartHeight - ($chartPadding * 2);
            $seriesCount = max(1, $pulseSeries->count());
            $formatChartNumber = function ($value) {
                return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
            };
            $formatChartPoint = function ($point) use ($formatChartNumber) {
                return $formatChartNumber($point['x']).','.$formatChartNumber($point['y']);
            };
            $buildPulseLine = function ($points) use ($formatChartNumber, $formatChartPoint) {
                $points = array_values($points);
                $count = count($points);

                if ($count === 0) {
                    return '';
                }

                if ($count === 1) {
                    return 'M '.$formatChartPoint($points[0]);
                }

                $path = 'M '.$formatChartPoint($points[0]);

                for ($i = 0; $i < $count - 1; $i++) {
                    $p0 = $points[max(0, $i - 1)];
                    $p1 = $points[$i];
                    $p2 = $points[$i + 1];
                    $p3 = $points[min($count - 1, $i + 2)];
                    $control1 = [
                        'x' => $p1['x'] + (($p2['x'] - $p0['x']) / 6),
                        'y' => $p1['y'] + (($p2['y'] - $p0['y']) / 6),
                    ];
                    $control2 = [
                        'x' => $p2['x'] - (($p3['x'] - $p1['x']) / 6),
                        'y' => $p2['y'] - (($p3['y'] - $p1['y']) / 6),
                    ];

                    $path .= ' C '.$formatChartPoint($control1).' '.$formatChartPoint($control2).' '.$formatChartPoint($p2);
                }

                return $path;
            };
            $maxPulseValue = max(0, (int) $pulseSeries->max('value'));
            $visualPulseMax = $maxPulseValue > 0 ? min(100, max(8, $maxPulseValue * 1.18)) : 100;
            $pulsePoints = $pulseSeries->values()->map(function ($point, $index) use ($chartPadding, $chartInnerWidth, $chartInnerHeight, $chartHeight, $seriesCount, $visualPulseMax) {
                $x = $seriesCount === 1 ? $chartPadding : $chartPadding + ($chartInnerWidth * $index / ($seriesCount - 1));
                $visualValue = min(1, max(0, (int) $point['value'] / $visualPulseMax));
                $y = $chartHeight - $chartPadding - ($chartInnerHeight * $visualValue);

                return [
                    'x' => $x,
                    'y' => $y,
                    'value' => (int) $point['value'],
                ];
            })->values();
            $pulseLine = '';
            $pulseArea = '';

            if ($pulsePoints->isNotEmpty()) {
                $points = $pulsePoints->all();
                $firstPoint = $points[0];
                $lastPoint = $points[count($points) - 1];
                $baseline = $chartHeight - $chartPadding;

                $pulseLine = $buildPulseLine($points);
                $pulseArea = 'M '.$formatChartNumber($firstPoint['x']).','.$formatChartNumber($baseline)
                    .' L '.$formatChartPoint($firstPoint)
                    .substr($pulseLine, strlen('M '.$formatChartPoint($firstPoint)))
                    .' L '.$formatChartNumber($lastPoint['x']).','.$formatChartNumber($baseline).' Z';
            }
            $pulseChange = (int) ($pulse['change'] ?? 0);
        @endphp

        <div class="pulse-overview pulse-overview--{{ $pulse['level'] ?? 'empty' }}">
            <div class="pulse-overview__summary">
                <span class="pulse-overview__eyebrow">Пульс сейчас</span>
                <div class="pulse-overview__value-row">
                    <strong>{{ $pulse['current'] ?? 0 }}</strong>
                    <span>{{ $pulse['label'] ?? 'нет сигнала' }}</span>
                </div>
                <small>
                    {{ $pulse['trend'] ?? 'ровно' }}
                    @if($pulseChange !== 0)
                        {{ $pulseChange > 0 ? '+' : '' }}{{ $pulseChange }}
                    @endif
                    за 6 часов
                </small>
            </div>

            <div class="pulse-overview__chart" aria-label="График пульса за 24 часа">
                <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" focusable="false" preserveAspectRatio="none">
                    <path class="pulse-overview__grid-line" d="M {{ $chartPadding }} {{ $chartPadding + ($chartInnerHeight * 0.34) }} H {{ $chartWidth - $chartPadding }}" />
                    <path class="pulse-overview__grid-line" d="M {{ $chartPadding }} {{ $chartPadding + ($chartInnerHeight * 0.68) }} H {{ $chartWidth - $chartPadding }}" />
                    @if($pulseArea)
                        <path class="pulse-overview__area" d="{{ $pulseArea }}" />
                        <path class="pulse-overview__line" d="{{ $pulseLine }}" />
                    @endif
                </svg>
                <div class="pulse-overview__axis">
                    <span>{{ $pulseSeries->first()['label'] ?? '' }}</span>
                    <span>24 часа</span>
                    <span>{{ $pulseSeries->last()['label'] ?? '' }}</span>
                </div>
            </div>
        </div>

        <div class="pulse-feed__list">
            @forelse($activities as $activity)
                @php
                    $activityFrame = $activity->user && method_exists($activity->user, 'activeAvatarFrame')
                        ? $activity->user->activeAvatarFrame()
                        : null;
                    $subtitle = $activity->subtitle();
                    $summary = $activity->payload['summary'] ?? ($activity->payload['achievement_description'] ?? null);
                    $activitySvgIcon = $activity->svgIcon();
                @endphp
                <a class="pulse-feed__item {{ $activity->toneClass() }} @if($activityFrame) pulse-feed__item--framed pulse-feed__item--frame-{{ $activityFrame }} @endif"
                   href="{{ $activity->url() }}"
                   title="{{ trim($activity->title().' '.$subtitle) }}">
                    <span class="pulse-feed__icon">
                        @if($activitySvgIcon)
                            {!! $activitySvgIcon !!}
                        @else
                            <i class="{{ $activity->iconClass() }}"></i>
                        @endif
                    </span>
                    <span class="pulse-feed__body min-width-0">
                        <span class="pulse-feed__title">
                            @if($activity->hasActor())
                                <span class="pulse-feed__actor">{{ $activity->actorName() }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $activity->user, 'compact' => true])
                                <span class="pulse-feed__action">{{ $activity->actionText() }}</span>
                            @else
                                {{ $activity->title() }}
                            @endif
                        </span>
                        @if($subtitle)
                            <span class="pulse-feed__meta">{{ $subtitle }}</span>
                        @endif
                        @if($summary)
                            <span class="pulse-feed__news">{{ $summary }}</span>
                        @endif
                    </span>
                    <span class="pulse-feed__time">
                        <span>{{ $activity->timeAgo() }}</span>
                        @if($activity->created_at)
                            <span>{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="pulse-feed__empty">
                    <span class="pulse-feed__icon is-muted"><i class="fas fa-seedling"></i></span>
                    <span>Лента оживёт, когда появятся сдачи, проверки и покупки бустеров.</span>
                </div>
            @endforelse
        </div>

        @if($activities->hasPages())
            <div class="pulse-feed__pagination">
                {{ $activities->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </section>
@endsection
