@props(['pulse', 'chartWidth' => 320, 'chartHeight' => 92, 'chartPadding' => 7, 'filled' => false, 'lineClass' => 'pulse-overview__line', 'areaClass' => 'pulse-overview__area', 'label' => null])
        @php
            $pulseSeries = collect($pulse['series'] ?? []);
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
            $visualPulseMax = $maxPulseValue > 0 ? min(\App\CourseActivity::PULSE_MAX_VALUE, max(8, $maxPulseValue * 1.18)) : \App\CourseActivity::PULSE_MAX_VALUE;
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
        @endphp
<svg {{ $attributes->class(['gc-pulse-chart']) }} viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" focusable="false" preserveAspectRatio="none" @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif>
    @if($pulseLine)
        @if($filled)<path class="{{ $areaClass }}" d="{{ $pulseArea }}" />@endif
        <path class="{{ $lineClass }}" d="{{ $pulseLine }}" />
        <circle class="gc-pulse-chart__current" cx="{{ $formatChartNumber($lastPoint['x']) }}" cy="{{ $formatChartNumber($lastPoint['y']) }}" r="3" />
    @endif
</svg>
