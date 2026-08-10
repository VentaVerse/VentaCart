<?php

namespace App\Support;

/**
 * One series from Standing, made drawable.
 *
 * The Blade partial and the sales-series endpoint MUST agree on every pixel
 * and every formatted figure, because with JavaScript the plot is rebuilt
 * from the endpoint's answer and without it the partial's render IS the
 * answer. So the geometry (viewBox positions, path strings), the money
 * strings, the axis thinning and the aria sentence are all computed here,
 * once, and both callers render the same payload. The client does no
 * arithmetic and no currency formatting: currency belongs to the store's
 * configuration, and a JS reimplementation of it would drift the first time
 * a second currency appeared.
 */
class SalesPlot
{
    /** The viewBox. Stretched horizontally only; see the partial. */
    public const WIDTH = 1000;

    public const HEIGHT = 200;

    private const PAD = 14;

    /**
     * The most labels the axis prints. Twelve monthly labels fit and always
     * have; ninety daily ones are a smear, so longer series print an evenly
     * spaced selection that always keeps both ends.
     */
    private const AXIS_MAX = 8;

    public static function payload(array $series, string $currency): array
    {
        $current = array_map('floatval', $series['current']);
        $earlier = $series['earlier'] === null ? null : array_map('floatval', $series['earlier']);
        $hasEarlier = $earlier !== null;
        $n = count($current);
        $peak = (float) $series['peak'];
        $money = fn (float $v) => $currency . number_format($v, 2);

        $x = [];
        $y = [];
        $yEarlier = $hasEarlier ? [] : null;

        for ($i = 0; $i < $n; $i++) {
            $x[] = $n < 2 ? 0.0 : round($i / ($n - 1) * self::WIDTH, 2);
            $y[] = self::y($current[$i], $peak);

            if ($hasEarlier) {
                $yEarlier[] = self::y($earlier[$i], $peak);
            }
        }

        $dNow = self::path($x, $y);
        $dThen = $hasEarlier ? self::path($x, $yEarlier) : '';
        $dArea = $n >= 2
            ? $dNow . ' L' . $x[$n - 1] . ',' . self::HEIGHT . ' L0,' . self::HEIGHT . ' Z'
            : '';

        $titles = [];
        for ($i = 0; $i < $n; $i++) {
            $titles[] = $series['full'][$i] . ': ' . $money($current[$i]) . '.'
                . ($hasEarlier ? ' ' . $series['earlierLabels'][$i] . ': ' . $money($earlier[$i]) . '.' : '');
        }

        $nowRead = [
            'label' => $series['now']['label'] . ($series['now']['sofar'] ? ' so far' : ''),
            'value' => $money((float) $series['now']['value']),
        ];

        return [
            'range' => $series['range'],
            'granularity' => $series['granularity'],
            'title' => $series['title'],
            'nowName' => $series['nowName'],
            'thenName' => $series['thenName'],
            'hasEarlier' => $hasEarlier,
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'x' => $x,
            'y' => $y,
            'yEarlier' => $yEarlier,
            'dNow' => $dNow,
            'dThen' => $dThen,
            'dArea' => $dArea,
            'full' => $series['full'],
            'earlierLabels' => $series['earlierLabels'],
            'currentFmt' => array_map($money, $current),
            'earlierFmt' => $hasEarlier ? array_map($money, $earlier) : null,
            'titles' => $titles,
            'axis' => self::axis($series['labels']),
            'peakNow' => $money((float) $series['currentPeak']),
            'peakThen' => $hasEarlier ? $money((float) $series['earlierPeak']) : null,
            'nowRead' => $nowRead,
            'aria' => self::aria($series, $money, $nowRead),
        ];
    }

    private static function y(float $value, float $peak): float
    {
        return $peak <= 0
            ? self::HEIGHT - self::PAD
            : round(self::HEIGHT - self::PAD - ($value / $peak) * (self::HEIGHT - self::PAD * 2), 2);
    }

    /**
     * @param  array<int, float>  $x
     * @param  array<int, float>  $y
     */
    private static function path(array $x, array $y): string
    {
        $d = '';
        foreach ($x as $i => $at) {
            $d .= ($i === 0 ? 'M' : 'L') . $at . ',' . $y[$i] . ' ';
        }

        return rtrim($d);
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    private static function axis(array $labels): array
    {
        $n = count($labels);

        if ($n <= 12) {
            return $labels;
        }

        $picked = [];
        for ($k = 0; $k < self::AXIS_MAX; $k++) {
            $picked[(int) round($k * ($n - 1) / (self::AXIS_MAX - 1))] = true;
        }

        return array_values(array_intersect_key($labels, $picked));
    }

    private static function aria(array $series, \Closure $money, array $nowRead): string
    {
        $word = $series['granularity'];
        $n = count($series['full']);
        $span = $n > 1 ? $series['full'][0] . ' to ' . $series['full'][$n - 1] : $series['full'][0];

        $sentence = 'Revenue by ' . $word . ', ' . $span;

        if ($series['earlier'] !== null) {
            $sentence .= ', against the same ' . $word . 's a year earlier'
                . '. Peak ' . $money((float) $series['currentPeak'])
                . ' and ' . $money((float) $series['earlierPeak']) . ' a year earlier';
        } else {
            $sentence .= '. Peak ' . $money((float) $series['currentPeak']);
        }

        return $sentence . '. ' . $nowRead['label'] . ' ' . $nowRead['value']
            . '. Each point carries its own figures.';
    }
}
