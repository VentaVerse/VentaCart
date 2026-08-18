{{--
    The sales plot: one period of revenue, with the same ground a year
    earlier beneath it (dashed) on every range except All Time, whose trace
    is the whole history and has no earlier self.

    ONE MEASURE, ONE SCALE. Both traces are revenue in the store's own
    currency, so they share a vertical scale honestly and a crossing means
    what it looks like it means. A chart that draws two different measures
    against two different maxima decides where its own lines cross, which is
    the one thing a chart must never do.

    LIKE FOR LIKE AT THE END. An open window's final point is incomplete, so
    the earlier trace's final point is cut to the same span rather than
    being the whole of that day or month. Without that, every plot would end
    in a cliff at the boundary and recover as the span filled.

    DRAWN AS INLINE SVG on the design tokens: no canvas, no charting
    library, no network request, and both themes work for free because
    every colour is a var(). The frame stretches horizontally only
    (preserveAspectRatio="none"), which is what lets a fixed 200 unit
    viewBox hold its height from 1440 down to 390; the strokes keep their
    weight under that stretch through vector-effect="non-scaling-stroke",
    and no circle marks the endpoint because under a non-uniform stretch a
    circle renders as a lopsided ellipse.

    EVERYTHING DRAWN HERE IS THE PAYLOAD, and the payload is SalesPlot's:
    the picker's client-side swap fetches the same structure from the
    sales-series endpoint and rebuilds these exact nodes, so the no-script
    render and the swapped render cannot disagree. WITHOUT JAVASCRIPT the
    plot still draws, the axis still names its points, every point still
    carries a <title>, and the key prints the newest reading so the plot
    answers without being pointed at.
--}}
@props(['plot', 'currency'])

@php
    $p = \App\Support\SalesPlot::payload($plot, $currency);
    $count = count($p['x']);
    $hitW = $count < 2 ? $p['width'] : $p['width'] / ($count - 1);
@endphp

<div class="v3-plotcard" data-plot="{{ json_encode($p) }}">
    <div class="v3-plot__key">
        <span class="v3-plot__keyitem">
            <svg class="v3-plot__sw" width="18" height="4" viewBox="0 0 18 4" aria-hidden="true" focusable="false">
                <rect class="v3-plot__sw-now" width="18" height="4" rx="2"></rect>
            </svg>
            <span data-plot-nowname>{{ $p['nowName'] }}</span>
            <b class="v3-plot__peak" data-plot-nowpeak>peak {{ $p['peakNow'] }}</b>
        </span>
        <span class="v3-plot__keyitem" data-plot-thenkey @if (! $p['hasEarlier']) hidden @endif>
            <svg class="v3-plot__sw" width="18" height="4" viewBox="0 0 18 4" aria-hidden="true" focusable="false">
                <rect class="v3-plot__sw-then" x="0" width="7" height="4" rx="2"></rect>
                <rect class="v3-plot__sw-then" x="11" width="7" height="4" rx="2"></rect>
            </svg>
            <span data-plot-thenname>{{ $p['thenName'] }}</span>
            <b class="v3-plot__peak" data-plot-thenpeak>@if ($p['hasEarlier'])peak {{ $p['peakThen'] }}@endif</b>
        </span>
        {{-- The trace's newest reading, printed: the plot answers with
             nothing pointed at anything. --}}
        <span class="v3-plot__keyitem v3-plot__nowread">
            <span data-plot-nowread-label>{{ $p['nowRead']['label'] }}</span>
            <b class="v3-plot__peak" data-plot-nowread-value>{{ $p['nowRead']['value'] }}</b>
        </span>
    </div>

    <div class="v3-plot__frame">
        <svg class="v3-plot" viewBox="0 0 {{ $p['width'] }} {{ $p['height'] }}" preserveAspectRatio="none" role="img"
             aria-label="{{ $p['aria'] }}">
            <defs>
                <linearGradient id="v3-plot-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop class="v3-plot__from" offset="0%"></stop>
                    <stop class="v3-plot__to" offset="100%"></stop>
                </linearGradient>
            </defs>

            @foreach ([0.25, 0.5, 0.75] as $g)
                <line class="v3-plot__grid" x1="0" x2="{{ $p['width'] }}" y1="{{ $p['height'] * $g }}" y2="{{ $p['height'] * $g }}"></line>
            @endforeach

            <path class="v3-plot__area" d="{{ $p['dArea'] }}" fill="url(#v3-plot-fill)"></path>
            <path class="v3-plot__then" d="{{ $p['dThen'] }}"></path>
            <path class="v3-plot__now" d="{{ $p['dNow'] }}"></path>

            {{-- The hit rects exist to carry a <title> per point for
                 assistive technology; the guide below is the visible
                 answer. Grouped so a swap can rebuild them in one place. --}}
            <g data-plot-hits>
                @for ($i = 0; $i < $count; $i++)
                    <rect class="v3-plot__hit" x="{{ max(0, $p['x'][$i] - $hitW / 2) }}" y="0" width="{{ $hitW }}" height="{{ $p['height'] }}">
                        <title>{{ $p['titles'][$i] }}</title>
                    </rect>
                @endfor
            </g>
        </svg>

        <span class="v3-plot__guide" data-plot-guide aria-hidden="true"></span>
        <span class="v3-plot__dot v3-plot__dot--now" data-plot-dot="y" aria-hidden="true"></span>
        <span class="v3-plot__dot v3-plot__dot--then" data-plot-dot="yEarlier" aria-hidden="true" @if (! $p['hasEarlier']) hidden @endif></span>

        <div class="v3-plot__readout" data-plot-readout aria-hidden="true">
            <span class="v3-plot__rd" data-plot-date></span>
            <span class="v3-plot__rr">
                <i class="v3-plot__rk v3-plot__rk--now"></i><span data-plot-current></span>
            </span>
            <span class="v3-plot__rr" data-plot-earlier-row @if (! $p['hasEarlier']) hidden @endif>
                <i class="v3-plot__rk v3-plot__rk--then"></i><span data-plot-earlier></span>
            </span>
        </div>
    </div>

    <div class="v3-plot__axis" data-plot-axis aria-hidden="true">
        @foreach ($p['axis'] as $label)
            <span>{{ $label }}</span>
        @endforeach
    </div>

</div>
