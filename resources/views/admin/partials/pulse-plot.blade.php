{{--
    Twelve months of revenue, with the same twelve a year earlier beneath
    them.

    ONE MEASURE, ONE SCALE. Both traces are revenue in the store's own
    currency, so they share a vertical scale honestly and a crossing means
    what it looks like it means. A chart that draws two different measures
    against two different maxima decides where its own lines cross, which is
    the one thing a chart must never do.

    LIKE FOR LIKE AT THE END. The current month is incomplete, so the
    earlier trace's final point is cut to the same span rather than being
    the whole of that month. Without that, every plot would end in a cliff
    on the first of the month and recover by the thirty-first.

    DRAWN AS INLINE SVG on the design tokens: no canvas, no charting
    library, no network request, and both themes work for free because
    every colour is a var(). The frame stretches horizontally only
    (preserveAspectRatio="none"), which is what lets a fixed 200 unit
    viewBox hold its height from 1440 down to 390; the strokes keep their
    weight under that stretch through vector-effect="non-scaling-stroke",
    and no circle marks the endpoint because under a non-uniform stretch a
    circle renders as a lopsided ellipse.

    WITHOUT COLOUR the two traces are still told apart: the current year is
    solid and filled, the earlier year is dashed, and each names itself in
    the key beside its own peak. WITHOUT JAVASCRIPT the plot still draws,
    the axis still names its months, every point still carries a <title>,
    and the figures are all listed in the table below.
--}}
@props(['plot', 'currency'])

@php
    $labels = $plot['labels'];
    $count = count($labels);
    $peak = (float) $plot['peak'];

    $pw = 1000;
    $ph = 200;
    $pad = 14;

    $x = fn (int $i) => $count < 2 ? 0 : round($i / ($count - 1) * $pw, 2);
    $y = fn (float $v) => $peak <= 0
        ? $ph - $pad
        : round($ph - $pad - ($v / $peak) * ($ph - $pad * 2), 2);

    $trace = function (array $values) use ($count, $x, $y) {
        $d = '';
        for ($i = 0; $i < $count; $i++) {
            $d .= ($i === 0 ? 'M' : 'L') . $x($i) . ',' . $y((float) $values[$i]) . ' ';
        }

        return rtrim($d);
    };

    $currentLine = $trace($plot['current']);
    $earlierLine = $trace($plot['earlier']);
    $currentArea = $currentLine . ' L' . $x($count - 1) . ',' . $ph . ' L0,' . $ph . ' Z';

    $money = fn (float $v) => $currency . number_format($v, 2);

    $readings = [
        'labels' => $plot['full'],
        'earlierLabels' => $plot['earlierLabels'],
        'current' => array_map(fn ($v) => $money((float) $v), $plot['current']),
        'earlier' => array_map(fn ($v) => $money((float) $v), $plot['earlier']),
        'y' => array_map(fn ($v) => $y((float) $v), $plot['current']),
        'yEarlier' => array_map(fn ($v) => $y((float) $v), $plot['earlier']),
        'height' => $ph,
    ];
@endphp

<div class="v-plotcard" data-plot="{{ json_encode($readings) }}">
    <div class="v-plot__key">
        <span class="v-plot__keyitem">
            <svg class="v-plot__sw" width="18" height="4" viewBox="0 0 18 4" aria-hidden="true" focusable="false">
                <rect class="v-plot__sw-now" width="18" height="4" rx="2"></rect>
            </svg>
            This year
            <b class="v-plot__peak">peak {{ $money((float) $plot['currentPeak']) }}</b>
        </span>
        <span class="v-plot__keyitem">
            <svg class="v-plot__sw" width="18" height="4" viewBox="0 0 18 4" aria-hidden="true" focusable="false">
                <rect class="v-plot__sw-then" x="0" width="7" height="4" rx="2"></rect>
                <rect class="v-plot__sw-then" x="11" width="7" height="4" rx="2"></rect>
            </svg>
            A year earlier
            <b class="v-plot__peak">peak {{ $money((float) $plot['earlierPeak']) }}</b>
        </span>
    </div>

    <div class="v-plot__frame">
        <svg class="v-plot" viewBox="0 0 {{ $pw }} {{ $ph }}" preserveAspectRatio="none" role="img"
             aria-label="Revenue by month, {{ $labels[0] }} to {{ $labels[$count - 1] }}, against the same months a year earlier. Peak {{ $money((float) $plot['currentPeak']) }} this year and {{ $money((float) $plot['earlierPeak']) }} a year earlier. Every figure is listed in the table below this plot.">
            <defs>
                <linearGradient id="v-plot-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop class="v-plot__from" offset="0%"></stop>
                    <stop class="v-plot__to" offset="100%"></stop>
                </linearGradient>
            </defs>

            @foreach ([0.25, 0.5, 0.75] as $g)
                <line class="v-plot__grid" x1="0" x2="{{ $pw }}" y1="{{ $ph * $g }}" y2="{{ $ph * $g }}"></line>
            @endforeach

            <path class="v-plot__area" d="{{ $currentArea }}" fill="url(#v-plot-fill)"></path>
            <path class="v-plot__then" d="{{ $earlierLine }}"></path>
            <path class="v-plot__now" d="{{ $currentLine }}"></path>

            @for ($i = 0; $i < $count; $i++)
                @php
                    $w = $count < 2 ? $pw : $pw / ($count - 1);
                @endphp
                <rect class="v-plot__hit" x="{{ max(0, $x($i) - $w / 2) }}" y="0" width="{{ $w }}" height="{{ $ph }}">
                    <title>{{ $plot['full'][$i] }}: {{ $money((float) $plot['current'][$i]) }}. {{ $plot['earlierLabels'][$i] }}: {{ $money((float) $plot['earlier'][$i]) }}.</title>
                </rect>
            @endfor
        </svg>

        <span class="v-plot__guide" data-plot-guide aria-hidden="true"></span>
        <span class="v-plot__dot v-plot__dot--now" data-plot-dot="y" aria-hidden="true"></span>
        <span class="v-plot__dot v-plot__dot--then" data-plot-dot="yEarlier" aria-hidden="true"></span>

        <div class="v-plot__readout" data-plot-readout aria-hidden="true">
            <span class="v-plot__rd" data-plot-date></span>
            <span class="v-plot__rr">
                <i class="v-plot__rk v-plot__rk--now"></i><span data-plot-current></span>
            </span>
            <span class="v-plot__rr">
                <i class="v-plot__rk v-plot__rk--then"></i><span data-plot-earlier></span>
            </span>
        </div>
    </div>

    <div class="v-plot__axis" aria-hidden="true">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
    </div>

    <details class="v-plot__twin">
        <summary class="v-plot__twinsum">Show the numbers</summary>
        <div class="v-sheet__scroll">
            <table class="v-table w-full text-[13px]">
                <thead>
                    <tr>
                        <x-admin.table.th>Month</x-admin.table.th>
                        <x-admin.table.th align="right">Revenue taken</x-admin.table.th>
                        <x-admin.table.th>A year earlier</x-admin.table.th>
                        <x-admin.table.th align="right">Revenue taken</x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-v-border">
                    @foreach ($labels as $i => $label)
                        <tr>
                            <x-admin.table.cell class="text-v-text">{{ $plot['full'][$i] }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <span class="v-money"><span class="v-money__mark">{{ $currency }}</span><span class="v-money__amt">{{ number_format((float) $plot['current'][$i], 2) }}</span></span>
                            </x-admin.table.cell>
                            <x-admin.table.cell class="text-v-secondary">{{ $plot['earlierLabels'][$i] }}</x-admin.table.cell>
                            <x-admin.table.cell>
                                <span class="v-money"><span class="v-money__mark">{{ $currency }}</span><span class="v-money__amt">{{ number_format((float) $plot['earlier'][$i], 2) }}</span></span>
                            </x-admin.table.cell>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
