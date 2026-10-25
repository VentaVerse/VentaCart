<?php

namespace App\Support;

use App\Services\ThemeService;

/**
 * The column rules rows are drawn on: how many parts a row is split into,
 * which widths a column may take, and whether a row must add up exactly.
 *
 * Core's own rows sit on eight parts (eighths), the same grid as the page's
 * side rails, with the shapes the Layout editor offers. A theme that owns
 * its rows may declare other rules, either in theme.json:
 *
 *     "grid": {"units": 12, "widths": [1, 2, ..., 12], "fill": true,
 *              "auto": true, "max_columns": 12, "shapes": [[12], [6, 6]]}
 *
 * or from its service provider:
 *
 *     ColumnGrid::register('my-theme', ColumnGrid::make([...]));
 *
 * Settings:
 *
 *   units        the parts a row is split into (8, 12, 100 for percent)
 *   widths       the numeric widths a column may take; default 1 to units
 *   fill         a column may be "fill": it takes the space left in the row
 *   auto         a column may be "auto": it is as wide as its content
 *   wrap         false: numeric widths add up to units exactly (to at most
 *                units when the row holds a fill or auto column);
 *                true: any total, and columns past the row's end wrap onto
 *                the next line
 *   shares       a width's true share of the row where width / units is not
 *                it, e.g. {"33": 0.3333, "66": 0.6667} for thirds on a
 *                percent grid
 *   max_columns  the most columns a row may hold (default 6)
 *   shapes       the column shapes an editor offers
 *
 * Every rule works per device too: a row's tablet and phone widths are
 * checked against the same widths. A row that does not fit the rules
 * collapses to one full-width column rather than drawing a grid nothing
 * adds up in, the rule saved rows have always followed.
 *
 * The storefront draws a row on the default grid with the eight span
 * classes it always had. Any other grid is drawn as a wrapping flex row:
 * each column carries its width as a custom property (--col-flex, and
 * --col-flex-tablet and --col-flex-phone when set), so no width needs a
 * class of its own. See resources/css/storefront/layout-rows.css.
 */
final class ColumnGrid
{
    public const DEFAULT_UNITS = 8;

    public const DEFAULT_MAX_COLUMNS = 6;

    /** A column that takes the space left in the row. */
    public const FILL = 'fill';

    /** A column as wide as its content. */
    public const AUTO = 'auto';

    /**
     * The shapes core's Layout editor offers, in eighths.
     *
     * @var list<list<int>>
     */
    public const DEFAULT_SHAPES = [
        [8],
        [4, 4],
        [3, 5],
        [5, 3],
        [2, 6],
        [6, 2],
        [3, 3, 2],
        [2, 3, 3],
        [2, 4, 2],
        [2, 2, 2, 2],
    ];

    /** @var array<string, self> grids themes registered from providers */
    private static array $registered = [];

    /** @var array<string, self> per theme, for this request */
    private static array $resolved = [];

    /**
     * @param  list<int>  $widths
     * @param  array<int, float>  $shares
     * @param  list<list<int|string>>  $shapes
     */
    private function __construct(
        public readonly int $units,
        public readonly array $widths,
        public readonly bool $fill,
        public readonly bool $auto,
        public readonly bool $wrap,
        public readonly array $shares,
        public readonly int $maxColumns,
        public readonly array $shapes,
    ) {
    }

    /** Core's eight-part grid. */
    public static function default(): self
    {
        return new self(self::DEFAULT_UNITS, range(1, self::DEFAULT_UNITS), false, false, false, [], self::DEFAULT_MAX_COLUMNS, self::DEFAULT_SHAPES);
    }

    /**
     * A grid from its settings (see the class comment). Anything unreadable
     * takes the default's value; units are kept between 1 and 1000.
     */
    public static function make(array $spec): self
    {
        $units = is_numeric($spec['units'] ?? null) ? max(1, min(1000, (int) $spec['units'])) : self::DEFAULT_UNITS;

        $widths = [];
        foreach (is_array($spec['widths'] ?? null) ? $spec['widths'] : range(1, $units) as $w) {
            if (is_numeric($w) && (int) $w >= 1 && (int) $w <= $units) {
                $widths[(int) $w] = (int) $w;
            }
        }
        ksort($widths);
        $widths = array_values($widths) ?: [$units];
        if (! in_array($units, $widths, true)) {
            // Full width is always possible: it is what a row collapses to.
            $widths[] = $units;
        }

        $shares = [];
        foreach (is_array($spec['shares'] ?? null) ? $spec['shares'] : [] as $w => $share) {
            if (is_numeric($w) && is_numeric($share) && (float) $share > 0 && (float) $share <= 1) {
                $shares[(int) $w] = (float) $share;
            }
        }

        $grid = new self(
            $units,
            $widths,
            (bool) ($spec['fill'] ?? false),
            (bool) ($spec['auto'] ?? false),
            (bool) ($spec['wrap'] ?? false),
            $shares,
            is_numeric($spec['max_columns'] ?? null) ? max(1, min(48, (int) $spec['max_columns'])) : self::DEFAULT_MAX_COLUMNS,
            [],
        );

        $shapes = [];
        foreach (is_array($spec['shapes'] ?? null) ? $spec['shapes'] : [[$units]] as $shape) {
            if (is_array($shape) && $grid->normalizeColumns($shape) === array_values($shape)) {
                $shapes[] = array_values($shape);
            }
        }

        return new self($grid->units, $grid->widths, $grid->fill, $grid->auto, $grid->wrap, $grid->shares, $grid->maxColumns, $shapes ?: [[$units]]);
    }

    /** A theme's grid declared from its provider; it wins over theme.json. */
    public static function register(string $theme, self $grid): void
    {
        self::$registered[$theme] = $grid;
        unset(self::$resolved[$theme]);
    }

    /** Forget registered and resolved grids. For tests. */
    public static function flush(): void
    {
        self::$registered = [];
        self::$resolved = [];
    }

    /**
     * The grid a theme's rows are on: what its provider registered, else
     * its theme.json "grid", else the default. Null asks for the theme rows
     * are scoped to right now.
     */
    public static function forTheme(?string $theme = null): self
    {
        $theme ??= \App\Models\LayoutPlacement::scopeTheme();

        if (isset(self::$registered[$theme])) {
            return self::$registered[$theme];
        }
        if (isset(self::$resolved[$theme])) {
            return self::$resolved[$theme];
        }

        $grid = self::default();
        try {
            $path = app(ThemeService::class)->getThemePath($theme) . '/theme.json';
            $meta = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $grid = self::fromManifest(is_array($meta) ? ($meta['grid'] ?? null) : null);
        } catch (\Throwable) {
            // A theme that cannot be read draws on the default grid.
        }

        return self::$resolved[$theme] = $grid;
    }

    /** The grid a theme.json "grid" entry declares, or the default. */
    public static function fromManifest(mixed $grid): self
    {
        return is_array($grid) && $grid !== [] ? self::make($grid) : self::default();
    }

    public function isDefault(): bool
    {
        return $this->units === self::DEFAULT_UNITS
            && $this->widths === range(1, self::DEFAULT_UNITS)
            && ! $this->fill && ! $this->auto && ! $this->wrap
            && $this->shares === []
            && $this->maxColumns === self::DEFAULT_MAX_COLUMNS;
    }

    /** One full-width column: what a row that breaks the rules becomes. */
    public function full(): array
    {
        return [$this->units];
    }

    /**
     * Widths a row may carry, or one full-width column when they break the
     * rules. Numbers are read as whole numbers ("3" and 3.0 are 3); "fill"
     * and "auto" are kept where the grid allows them; anything else in the
     * list is skipped.
     *
     * @return list<int|string>
     */
    public function normalizeColumns(mixed $columns): array
    {
        $widths = [];
        foreach ((array) $columns as $w) {
            if (is_numeric($w)) {
                $widths[] = (int) $w;
            } elseif ($this->special($w) !== null) {
                $widths[] = $this->special($w);
            }
        }

        if ($widths === [] || count($widths) > $this->maxColumns) {
            return $this->full();
        }

        $numbers = array_filter($widths, 'is_int');
        foreach ($numbers as $w) {
            if (! in_array($w, $this->widths, true)) {
                return $this->full();
            }
        }

        if (! $this->wrap) {
            $sum = array_sum($numbers);
            $flexible = count($numbers) !== count($widths);
            if ($flexible ? $sum > $this->units : $sum !== $this->units) {
                return $this->full();
            }
        }

        return $widths;
    }

    /**
     * A row's columns on tablets and phones. A width of null keeps the
     * desktop width on a tablet and stacks the column full width on a
     * phone; orders are 1-based positions per device. Every list is as long
     * as the row has columns.
     *
     * @return array{tablet: list<int|string|null>, phone: list<int|string|null>, tablet_order: list<int>, phone_order: list<int>}
     */
    public function normalizeResponsive(mixed $in, int $count): array
    {
        $in = is_array($in) ? $in : [];
        $count = max(1, $count);
        $out = ['tablet' => [], 'phone' => [], 'tablet_order' => [], 'phone_order' => []];
        for ($i = 0; $i < $count; $i++) {
            foreach (['tablet', 'phone'] as $device) {
                $out[$device][] = $this->width($in[$device][$i] ?? null);
            }
            foreach (['tablet_order', 'phone_order'] as $key) {
                $o = $in[$key][$i] ?? ($i + 1);
                $out[$key][] = is_numeric($o) ? max(1, min($count, (int) $o)) : $i + 1;
            }
        }

        return $out;
    }

    /** Whether any column departs from the default on a smaller screen. */
    public function isResponsive(?array $responsive, int $count): bool
    {
        if (! $responsive) {
            return false;
        }
        $n = $this->normalizeResponsive($responsive, $count);
        foreach (['tablet', 'phone'] as $device) {
            foreach ($n[$device] as $w) {
                if ($w !== null) {
                    return true;
                }
            }
        }
        foreach (['tablet_order', 'phone_order'] as $key) {
            foreach ($n[$key] as $i => $o) {
                if ($o !== $i + 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /** One width this grid allows (a number, fill or auto), or null. */
    public function width(mixed $w): int|string|null
    {
        if (is_numeric($w)) {
            return in_array((int) $w, $this->widths, true) ? (int) $w : null;
        }

        return $this->special($w);
    }

    /**
     * A width's share of the row, 0 to 1, or null for fill and auto.
     */
    public function share(int|string $w): ?float
    {
        if (! is_int($w)) {
            return null;
        }

        return $this->shares[$w] ?? min(1.0, $w / $this->units);
    }

    /**
     * The CSS flex value a column of this width takes, for --col-flex. A
     * numeric width is its share of the row less its part of the gaps, so a
     * line of columns whose shares add up to one fills the row exactly.
     */
    public function flex(int|string $w): string
    {
        return match ($w) {
            self::FILL => '1 1 0%',
            self::AUTO => '0 0 auto',
            default => (function () use ($w) {
                $share = (float) $this->share($w);
                $pct = rtrim(rtrim(number_format($share * 100, 4, '.', ''), '0'), '.');
                $gap = rtrim(rtrim(number_format(1 - $share, 4, '.', ''), '0'), '.');

                return $gap === '0' ? "0 0 {$pct}%" : "0 0 calc({$pct}% - var(--layout-gap) * {$gap})";
            })(),
        };
    }

    /** The grid as an editor reads it. */
    public function toArray(): array
    {
        return [
            'units' => $this->units,
            'widths' => $this->widths,
            'fill' => $this->fill,
            'auto' => $this->auto,
            'wrap' => $this->wrap,
            'shares' => (object) $this->shares,
            'max_columns' => $this->maxColumns,
            'shapes' => $this->shapes,
        ];
    }

    private function special(mixed $w): ?string
    {
        return match (true) {
            $w === self::FILL && $this->fill => self::FILL,
            $w === self::AUTO && $this->auto => self::AUTO,
            default => null,
        };
    }
}
