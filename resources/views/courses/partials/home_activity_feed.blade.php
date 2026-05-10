@php
    $homePulse = $pulse ?? [];
    $pulseSeries = collect($homePulse['series'] ?? []);
    $chartWidth = 220;
    $chartHeight = 58;
    $chartPadding = 4;
    $chartInnerWidth = $chartWidth - ($chartPadding * 2);
    $chartInnerHeight = $chartHeight - ($chartPadding * 2);
    $seriesCount = max(1, $pulseSeries->count());
    $formatChartNumber = function ($value) {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    };
    $formatChartPoint = function ($point) use ($formatChartNumber) {
        return $formatChartNumber($point['x']).','.$formatChartNumber($point['y']);
    };
    $clampChartValue = function ($value, $min, $max) {
        return max($min, min($max, $value));
    };
    $pulsePoints = $pulseSeries->values()->map(function ($point, $index) use ($chartPadding, $chartInnerWidth, $chartInnerHeight, $chartHeight, $seriesCount) {
        $x = $seriesCount === 1 ? $chartPadding : $chartPadding + ($chartInnerWidth * $index / ($seriesCount - 1));
        $y = $chartHeight - $chartPadding - ($chartInnerHeight * ((int) $point['value'] / 100));

        return [
            'x' => $x,
            'y' => $y,
        ];
    })->values();
    $pulseLine = '';
    $pulseArea = '';

    if ($pulsePoints->isNotEmpty()) {
        $points = $pulsePoints->all();
        $firstPoint = $points[0];
        $lastPoint = $points[count($points) - 1];
        $baseline = $chartHeight - $chartPadding;
        $curveCommands = '';

        for ($index = 0; $index < count($points) - 1; $index++) {
            $p0 = $points[max(0, $index - 1)];
            $p1 = $points[$index];
            $p2 = $points[$index + 1];
            $p3 = $points[min(count($points) - 1, $index + 2)];
            $smoothness = 0.18;
            $control1 = [
                'x' => $p1['x'] + (($p2['x'] - $p0['x']) * $smoothness),
                'y' => $clampChartValue($p1['y'] + (($p2['y'] - $p0['y']) * $smoothness), $chartPadding, $baseline),
            ];
            $control2 = [
                'x' => $p2['x'] - (($p3['x'] - $p1['x']) * $smoothness),
                'y' => $clampChartValue($p2['y'] - (($p3['y'] - $p1['y']) * $smoothness), $chartPadding, $baseline),
            ];
            $curveCommands .= ' C '.$formatChartPoint($control1).' '.$formatChartPoint($control2).' '.$formatChartPoint($p2);
        }

        $pulseLine = 'M '.$formatChartPoint($firstPoint).$curveCommands;
        $pulseArea = 'M '.$formatChartNumber($firstPoint['x']).','.$formatChartNumber($baseline)
            .' L '.$formatChartPoint($firstPoint)
            .$curveCommands
            .' L '.$formatChartNumber($lastPoint['x']).','.$formatChartNumber($baseline).' Z';
    }
@endphp

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
        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" focusable="false" preserveAspectRatio="none">
            @if($pulseArea)
                <path class="home-pulse-card__area" d="{{ $pulseArea }}" />
                <path class="home-pulse-card__line" d="{{ $pulseLine }}" />
            @endif
        </svg>
    </span>

    <span class="home-card-arrow home-pulse-card__arrow"><i class="fas fa-arrow-right"></i></span>
</a>
