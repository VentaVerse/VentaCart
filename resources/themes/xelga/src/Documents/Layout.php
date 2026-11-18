<?php

namespace Themes\Xelga\Documents;

/**
 * Where each section of a page sits: rows of one to four columns, and an
 * optional left and right side beside them.
 *
 * Both are optional keys of a template document. `rows` is a list of
 * `{id, split, columns, settings}`, each column a list of section ids;
 * `sides` is `{left: [ids], right: [ids]}`. A document with neither reads
 * as one full-width row per section in `order`, which is how every page
 * was stored before rows existed, so such a page draws exactly as before.
 *
 * A page with page content (see sidesAllowed()) also has areas: each row
 * is in Top, Content or Bottom (its `area`), in that order down the page.
 * Top and Bottom run the whole page width; the sides stand beside Content.
 *
 * `order` stays in every document as the reading order, derived here
 * from the rows and sides: Top rows, left side, Content rows (each row's
 * columns left to right), right side, Bottom rows. That is also the DOM
 * order, so phones stack the page the way it reads.
 */
final class Layout
{
    /** The column splits a row can have, in twelfths, with the name the editor shows. */
    public const SPLITS = [
        '12' => ['parts' => [12], 'label' => 'One column'],
        '6-6' => ['parts' => [6, 6], 'label' => 'Halves'],
        '8-4' => ['parts' => [8, 4], 'label' => 'Wide left'],
        '4-8' => ['parts' => [4, 8], 'label' => 'Wide right'],
        '4-4-4' => ['parts' => [4, 4, 4], 'label' => 'Thirds'],
        '3-6-3' => ['parts' => [3, 6, 3], 'label' => 'Wide middle'],
        '3-3-3-3' => ['parts' => [3, 3, 3, 3], 'label' => 'Quarters'],
    ];

    /** A row's settings: page or full width, a color scheme or none, the space between columns. */
    public const ROW_DEFAULTS = ['width' => 'page', 'scheme' => '', 'space' => 'md'];

    /** The values a row's width and space between columns can take; its scheme is any of the store's, or none. */
    public const ROW_CHOICES = ['width' => ['page', 'full'], 'space' => ['sm', 'md', 'lg']];

    public const MAX_ROWS = 40;

    public const MAX_PER_COLUMN = 6;

    public const MAX_PER_SIDE = 6;

    /** How wide each side is, in twelfths of the page. */
    public const SIDE_SPAN = 3;

    public const SIDES = ['left', 'right'];

    /**
     * The areas a page with page content has, top to bottom: Top and
     * Bottom run the whole page width, Content stands between the sides.
     */
    public const AREAS = ['top', 'content', 'bottom'];

    /**
     * Whether a document may have areas and sides: page templates whose
     * page type has a main section, so there is page content for Top and
     * Bottom to stand around and for the sides to stand beside. Never the
     * header or footer, not Home, whose page is all rows, and not the cart,
     * which stays focused on buying. A custom template follows its page
     * type.
     */
    public static function sidesAllowed(string $handle): bool
    {
        $pageType = Handles::pageType($handle);

        return Handles::kind($handle) === 'template' && $pageType !== 'cart' && Defaults::mainTypes((string) $pageType) !== [];
    }

    /**
     * How wide the page's content area is, in twelfths of a page $page
     * twelfths wide, when $sides sides stand beside it: 12, 9 or 6 on a
     * whole page. The renderer counts the sides it draws; the normalizer
     * counts the sides that hold a section, which is never fewer.
     */
    public static function contentSpan(int $sides, int $page = 12): int
    {
        return max(1, $page - self::SIDE_SPAN * max(0, min(count(self::SIDES), $sides)));
    }

    /**
     * What the editor needs to build rows and sides, so the splits, the
     * defaults and the limits have one source.
     *
     * `sides_on` lists every page type the catalog knows, the pages of
     * installed extensions included, that have areas and sides.
     *
     * @return array{splits: array, row_defaults: array, row_choices: array, max_rows: int, max_per_column: int, max_per_side: int, side_span: int, sides_on: list<string>}
     */
    public static function payload(): array
    {
        return [
            'splits' => self::SPLITS,
            'row_defaults' => self::ROW_DEFAULTS,
            'row_choices' => self::ROW_CHOICES,
            'max_rows' => self::MAX_ROWS,
            'max_per_column' => self::MAX_PER_COLUMN,
            'max_per_side' => self::MAX_PER_SIDE,
            'side_span' => self::SIDE_SPAN,
            'sides_on' => array_values(array_filter([...Handles::TEMPLATES, ...array_keys(Handles::extensionTemplates())], fn (string $type) => self::sidesAllowed($type))),
        ];
    }

    /**
     * The id of a row made for one section: `row-` and the start of a hash
     * of the section id, so the same document always gets the same ids
     * (the editor computes them too). Made longer while it is taken.
     *
     * @param  list<string>  $taken  ids already used in the document
     */
    public static function rowIdFor(string $sectionId, array $taken): string
    {
        $set = array_fill_keys(array_filter($taken, 'is_string'), true);

        return self::freshId($sectionId, $set);
    }

    /**
     * The document's rows: its own when it has them, else one full-width
     * row per section in `order` that is not in a side.
     *
     * Given the page's rules, on a page with areas each row also has its
     * `area` (see areasOf()): the one it is stored with, else the one its
     * place on the page gives it.
     *
     * @return list<array{id: string, split: string, columns: list<list<string>>, settings: array, area?: string}>
     */
    public static function rows(array $content, ?LayoutRules $rules = null): array
    {
        $explicit = is_array($content['rows'] ?? null);
        $rows = [];
        foreach ($explicit ? $content['rows'] : self::impliedRows($content) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $columns = [];
            foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $column) {
                $columns[] = array_values(array_filter(is_array($column) ? $column : [], 'is_string'));
            }
            $out = self::row((string) ($row['id'] ?? ''), (string) ($row['split'] ?? '12'), $columns, self::settings($row['settings'] ?? []));
            $out['_area'] = self::storedArea($row);
            $rows[] = $out;
        }

        $sections = [];
        foreach ((array) ($content['sections'] ?? []) as $id => $section) {
            $sections[(string) $id] = (array) $section;
        }
        if ($rules === null || ! $rules->pageTypeKnown() || ! self::hasAreas($sections, $rules)) {
            return array_map(fn (array $row) => self::bare($row), $rows);
        }

        $inSides = false;
        $sides = is_array($content['sides'] ?? null) ? $content['sides'] : [];
        foreach (self::SIDES as $which) {
            foreach (is_array($sides[$which] ?? null) ? $sides[$which] : [] as $id) {
                $inSides = $inSides || (is_string($id) && isset($sections[$id]));
            }
        }
        $stored = array_filter($rows, fn (array $row) => $row['_area'] !== null) !== [];

        $main = INF;
        foreach ($rows as $index => $row) {
            foreach (array_merge(...$row['columns']) as $sid) {
                if (isset($sections[$sid]) && $rules->isMain($sections[$sid])) {
                    $main = min($main, $index);
                }
            }
        }

        $areas = self::areasOf($rows, $main, $inSides && ! $stored);

        return array_map(fn (array $row, int $index) => self::bare($row) + ['area' => $areas[$index]], $rows, array_keys($rows));
    }

    /**
     * The document with every section placed once and `order` derived from
     * where it sits. Never refuses: what does not fit is moved and reported.
     *
     * - A section in no row and no side (an extension placement, a section
     *   added by an older editor) gets its own full-width row after the row
     *   of the nearest section before it in `order`, or first.
     * - A section too wide for its column or side, a main section in a
     *   column or side, or a section that keeps a row of its own found in
     *   a column or side, gets its own full-width row: after its row, or at
     *   the end when it came from a side. A column is measured at the width
     *   it really draws: its part of the area it is in, so a section never
     *   draws narrower than it was made for. A theme update that narrows a
     *   section's span can so never make a stored page invalid.
     * - On a page with areas (see areasOf()), each row gets its area. Top
     *   and Bottom are measured at the whole page; Content at what the
     *   sides leave, one-column rows included, and what does not fit there
     *   leaves Content with the rows between it and the edge of Content, so
     *   the page keeps its reading order. A section that only belongs in
     *   Content moves into it from Top or Bottom.
     * - A page without areas has no sides: they go to rows at the end.
     * - Ids that name no section are dropped, a one-column row holds one
     *   section, an empty one-column row goes; an empty multi-column row
     *   stays, since the merchant made it to fill, unless the page would
     *   then have more than MAX_ROWS rows (see fitRowLimit()).
     * - Empty sides are dropped, and rows that say no more than `order`
     *   does (all plain full-width rows with the ids and areas rows() would
     *   give) are dropped, so a document only keeps `rows` when it needs
     *   them. A page with sides keeps its rows, which carry the areas.
     *
     * A document with neither `rows` nor `sides` is returned as it is, and
     * so is a page whose page type is away (its extension is not
     * registered), which waits untouched until it is back. Reads no
     * database; running it twice changes nothing more.
     *
     * @return array{content: array, moved: list<array{path: string, reason: string, value: string}>}
     */
    public static function normalize(array $content, LayoutRules $rules): array
    {
        if (! array_key_exists('rows', $content) && ! array_key_exists('sides', $content)) {
            return ['content' => $content, 'moved' => []];
        }
        if (! $rules->pageTypeKnown()) {
            return ['content' => $content, 'moved' => []];
        }

        $sections = [];
        foreach ((array) ($content['sections'] ?? []) as $id => $section) {
            $sections[(string) $id] = (array) $section;
        }

        // Every section once, in the order the document gives.
        $known = [];
        foreach (is_array($content['order'] ?? null) ? $content['order'] : [] as $id) {
            if (is_string($id) && isset($sections[$id])) {
                $known[$id] = true;
            }
        }
        $known = array_keys($known + array_fill_keys(array_keys($sections), true));
        $known = array_map('strval', $known);

        $placed = [];
        $moved = [];
        $taken = array_fill_keys(array_keys($sections), true);
        $claim = function (mixed $id) use ($sections, &$placed): bool {
            if (! is_string($id) || ! isset($sections[$id]) || isset($placed[$id])) {
                return false;
            }

            return $placed[$id] = true;
        };

        $areas = self::hasAreas($sections, $rules);

        $sides = ['left' => [], 'right' => []];
        $givenSides = is_array($content['sides'] ?? null) ? $content['sides'] : [];
        foreach (self::SIDES as $which) {
            foreach (is_array($givenSides[$which] ?? null) ? $givenSides[$which] : [] as $id) {
                if ($claim($id)) {
                    $sides[$which][] = $id;
                }
            }
        }
        $hadSides = $sides['left'] !== [] || $sides['right'] !== [];

        // What stays in the sides decides the content area Content is measured in.
        $sideMisfits = [];
        foreach (self::SIDES as $which) {
            $keep = [];
            foreach ($sides[$which] as $sid) {
                $reason = $areas ? self::misfit($sections[$sid], self::SIDE_SPAN, $rules) : 'no-sides';
                if ($reason === null) {
                    $keep[] = $sid;
                } else {
                    $sideMisfits[] = [$sid, $reason];
                }
            }
            $sides[$which] = $keep;
        }
        $contentSpan = self::contentSpan(count(array_filter($sides, fn (array $ids) => $ids !== [])));

        // The rows as given, or one per section when only sides were given.
        if (is_array($content['rows'] ?? null)) {
            $given = array_values(array_filter($content['rows'], 'is_array'));
        } else {
            $given = [];
            foreach ($known as $id) {
                if (! isset($placed[$id])) {
                    $given[] = ['split' => '12', 'columns' => [[$id]]];
                }
            }
        }

        // The rows stored before areas ran beside the sides from top to bottom.
        $beside = $areas && $hadSides && array_filter($given, fn (array $row) => self::storedArea($row) !== null) === [];

        // The area each given row will be in, so its columns are measured at
        // the width they draw: Top and Bottom at the whole page, Content at
        // what the sides leave.
        $spanOf = array_fill(0, count($given), $contentSpan);
        if ($areas) {
            $first = [];
            foreach ($given as $i => $row) {
                $first[$i] = ['_area' => self::storedArea($row), '_inherit' => false];
            }
            foreach (self::areasOf($first, self::mainPlace($given, $sides, $known, $sections, $rules), $beside) as $i => $area) {
                $spanOf[$i] = $area === 'content' ? $contentSpan : 12;
            }
        }

        // Row ids that can stay: well formed, used once, not a section's id.
        $ids = [];
        foreach ($given as $i => $row) {
            $id = $row['id'] ?? null;
            if (is_string($id) && preg_match(Shape::ID, $id) === 1 && ! isset($taken[$id])) {
                $ids[$i] = $id;
                $taken[$id] = true;
            }
        }

        $rows = [];
        $emptied = [];
        foreach ($given as $i => $row) {
            $split = is_string($row['split'] ?? null) || is_int($row['split'] ?? null) ? (string) $row['split'] : '';
            $columns = [];
            foreach (is_array($row['columns'] ?? null) ? array_values($row['columns']) : [] as $column) {
                $columns[] = is_array($column) ? array_values($column) : [];
            }
            if (! isset(self::SPLITS[$split])) {
                $split = '12';
                $columns = [array_merge(...$columns)];
            }
            $parts = self::SPLITS[$split]['parts'];
            $columns = self::fitColumns($columns, count($parts));

            foreach ($columns as $c => $column) {
                $columns[$c] = array_values(array_filter($column, $claim));
            }
            $settings = self::settings($row['settings'] ?? []);
            $area = $areas ? self::storedArea($row) : null;
            // An empty row with no usable id gets one from a fixed seed, lengthened while taken,
            // so its id does not depend on where the row sits.
            $seed = array_merge(...$columns)[0] ?? 'row';

            if ($split === '12') {
                $column = $columns[0];
                if ($column === []) {
                    continue;
                }
                $first = array_shift($column);
                $rows[] = self::row($ids[$i] ?? self::freshId($first, $taken), '12', [[$first]], $settings) + ['_area' => $area, '_inherit' => false];
                foreach ($column as $id) {
                    $rows[] = self::plainRow($id, $taken, true);
                }
                continue;
            }

            $id = $ids[$i] ?? self::freshId($seed, $taken);
            $misfits = [];
            foreach ($columns as $c => $column) {
                $keep = [];
                foreach ($column as $sid) {
                    $reason = self::misfit($sections[$sid], $parts[$c], $rules, $spanOf[$i]);
                    if ($reason === null) {
                        $keep[] = $sid;
                    } else {
                        $misfits[] = [$sid, $reason];
                    }
                }
                $columns[$c] = $keep;
            }
            $rows[] = self::row($id, $split, $columns, $settings) + ['_area' => $area, '_inherit' => false];
            if ($misfits !== [] && array_merge(...$columns) === []) {
                $emptied[$id] = true;
            }
            foreach ($misfits as [$sid, $reason]) {
                $rows[] = $plain = self::plainRow($sid, $taken, true);
                $moved[] = self::moved($sid, $reason, $plain['id']);
            }
        }

        // Sides: what cannot sit in one goes to its own row at the end.
        foreach ($sideMisfits as [$sid, $reason]) {
            $rows[] = $plain = self::plainRow($sid, $taken);
            $moved[] = self::moved($sid, $reason, $plain['id']);
        }

        // Sections in no row and no side: after the row of the nearest one before them.
        foreach ($known as $k => $sid) {
            if (isset($placed[$sid])) {
                continue;
            }
            $at = 0;
            for ($j = $k - 1; $j >= 0; $j--) {
                $index = self::rowOf($rows, $known[$j]);
                if ($index !== null) {
                    $at = $index + 1;
                    break;
                }
            }
            array_splice($rows, $at, 0, [self::plainRow($sid, $taken)]);
            $placed[$sid] = true;
        }

        if ($areas) {
            $main = self::mainRow($rows, $sections, $rules);
            foreach (self::areasOf($rows, $main ?? INF, $beside) as $index => $area) {
                $rows[$index]['_area'] = $area;
            }
            if ($main !== null) {
                array_push($moved, ...self::fitContent($rows, $main, $sections, $rules, $contentSpan));
            }
            array_push($moved, ...self::contentOnlyIntoContent($rows, $emptied, $sections, $rules, $taken));
        }

        $rows = self::fitRowLimit($rows, $emptied);

        // Top, then Content between the sides, then Bottom.
        $order = [];
        foreach ($areas ? self::AREAS : ['content'] as $area) {
            if ($area === 'content') {
                array_push($order, ...$sides['left']);
            }
            foreach ($rows as $row) {
                if (! $areas || $row['_area'] === $area) {
                    foreach ($row['columns'] as $column) {
                        array_push($order, ...$column);
                    }
                }
            }
            if ($area === 'content') {
                array_push($order, ...$sides['right']);
            }
        }

        $sides = array_filter($sides, fn (array $ids) => $ids !== []);

        $bare = array_map(fn (array $row) => self::bare($row), $rows);
        $plain = self::sayNoMoreThanOrder($bare, ['order' => $order, 'sections' => $content['sections'] ?? [], 'sides' => $sides]);
        if ($areas) {
            $plain = $plain && $sides === [] && self::areasArePlaces($rows, self::mainRow($rows, $sections, $rules));
            $bare = array_map(fn (array $row) => self::bare($row) + ['area' => $row['_area']], $rows);
        }

        $out = $content;
        $out['order'] = $order;
        unset($out['rows'], $out['sides']);
        if (! $plain) {
            $out['rows'] = $bare;
        }
        if ($sides !== []) {
            $out['sides'] = $sides;
        }

        return ['content' => $out, 'moved' => $moved];
    }

    /**
     * The area of each row, top to bottom.
     *
     * 1. A row keeps a valid stored area.
     * 2. On a page stored with sides before rows had areas ($beside), the
     *    sides ran beside every row, so a row without one is Content.
     * 3. Otherwise a row takes its place on the page: Top above the main
     *    section's row, Content for that row, Bottom below it. A row split
     *    off another (a section moved out of its column) takes the area of
     *    the row it left.
     * 4. Then the main row is Content, a row above it at most Content, a row
     *    below it at least Content, and down the page the area never goes
     *    back, so the areas always read Top, Content, Bottom.
     *
     * @param  list<array{_area: ?string, _inherit: bool}>  $rows
     * @param  float|int  $main  the index of the main row; between two rows
     *                           or past the end while it is still to be placed
     * @return list<string>
     */
    private static function areasOf(array $rows, float|int $main, bool $beside): array
    {
        $rank = array_flip(self::AREAS);
        $out = [];
        $least = 0;
        foreach (array_values($rows) as $index => $row) {
            $area = $row['_area'] ?? null;
            if ($area === null && ($row['_inherit'] ?? false) && $index > 0) {
                $area = $out[$index - 1];
            }
            $area ??= match (true) {
                $beside => 'content',
                $index < $main => 'top',
                $index == $main => 'content',
                default => 'bottom',
            };
            $r = $rank[$area];
            $r = match (true) {
                $index < $main => min($r, $rank['content']),
                $index == $main => $rank['content'],
                default => max($r, $rank['content']),
            };
            $least = max($least, $r);
            $out[] = self::AREAS[$least];
        }

        return $out;
    }

    /**
     * Content holds what fits beside the sides. Each Content row is
     * measured at the content span, one-column rows too, leaving out the
     * main section and sections that belong in Content. The last row above
     * the main row that does not fit goes to Top with every Content row
     * above it; the first one below goes to Bottom with every Content row
     * below it, so the page keeps its reading order.
     *
     * @param  list<array>  $rows  rows with `_area`, changed in place
     * @return list<array{path: string, reason: string, value: string}>
     */
    private static function fitContent(array &$rows, int $main, array $sections, LayoutRules $rules, int $contentSpan): array
    {
        $misfits = [];
        foreach ($rows as $index => $row) {
            if ($row['_area'] !== 'content') {
                continue;
            }
            $parts = self::SPLITS[$row['split']]['parts'];
            foreach ($row['columns'] as $c => $column) {
                foreach ($column as $sid) {
                    $section = $sections[$sid];
                    if (! $rules->isMain($section) && ! $rules->contentOnly($section) && $rules->minSpan($section) * 12 > $parts[$c] * $contentSpan) {
                        $misfits[$index][$sid] = true;
                    }
                }
            }
        }

        $above = array_filter(array_keys($misfits), fn (int $index) => $index < $main);
        $below = array_filter(array_keys($misfits), fn (int $index) => $index > $main);
        $leave = [];
        if ($above !== []) {
            $last = max($above);
            foreach ($rows as $index => $row) {
                if ($index <= $last && $row['_area'] === 'content') {
                    $leave[$index] = 'top';
                }
            }
        }
        if ($below !== []) {
            $first = min($below);
            foreach ($rows as $index => $row) {
                if ($index >= $first && $row['_area'] === 'content') {
                    $leave[$index] = 'bottom';
                }
            }
        }

        $moved = [];
        foreach ($leave as $index => $area) {
            $rows[$index]['_area'] = $area;
            foreach (array_merge(...$rows[$index]['columns']) as $sid) {
                if (! $rules->contentOnly($sections[$sid])) {
                    $moved[] = self::moved($sid, isset($misfits[$index][$sid]) ? 'too-wide' : 'in-order', $area);
                }
            }
        }

        return $moved;
    }

    /**
     * A section that belongs in Content found in Top or Bottom moves to a
     * one-column row of its own: at the start of Content from Top, at the
     * end of Content from Bottom. A one-column row that holds only it moves
     * as it is.
     *
     * @param  list<array>  $rows  rows with `_area` in area order, changed in place
     * @param  array<string, true>  $emptied  ids of rows this run emptied, added to
     * @param  array<string, true>  $taken
     * @return list<array{path: string, reason: string, value: string}>
     */
    private static function contentOnlyIntoContent(array &$rows, array &$emptied, array $sections, LayoutRules $rules, array &$taken): array
    {
        $moved = [];
        $into = ['top' => [], 'bottom' => []];
        $kept = [];
        foreach ($rows as $row) {
            $area = $row['_area'];
            if ($area === 'content') {
                $kept[] = $row;
                continue;
            }
            $out = [];
            foreach ($row['columns'] as $c => $column) {
                foreach ($column as $sid) {
                    if ($rules->contentOnly($sections[$sid])) {
                        $out[] = [$c, $sid];
                    }
                }
            }
            if ($out === []) {
                $kept[] = $row;
                continue;
            }
            if ($row['split'] === '12') {
                $into[$area][] = ['_area' => 'content'] + $row;
                $moved[] = self::moved($out[0][1], 'content-only', $row['id']);
                continue;
            }
            foreach ($out as [$c, $sid]) {
                $row['columns'][$c] = array_values(array_diff($row['columns'][$c], [$sid]));
                $into[$area][] = $plain = ['_area' => 'content'] + self::plainRow($sid, $taken);
                $moved[] = self::moved($sid, 'content-only', $plain['id']);
            }
            if (array_merge(...$row['columns']) === []) {
                $emptied[$row['id']] = true;
            }
            $kept[] = $row;
        }

        if ($moved === []) {
            return [];
        }

        $byArea = ['top' => [], 'content' => [], 'bottom' => []];
        foreach ($kept as $row) {
            $byArea[$row['_area']][] = $row;
        }
        $rows = [...$byArea['top'], ...$into['top'], ...$byArea['content'], ...$into['bottom'], ...$byArea['bottom']];

        return $moved;
    }

    /** Whether each row's area is the one its place on the page gives it. */
    private static function areasArePlaces(array $rows, ?int $main): bool
    {
        if ($main === null) {
            return false;
        }
        foreach (array_values($rows) as $index => $row) {
            $place = $index < $main ? 'top' : ($index === $main ? 'content' : 'bottom');
            if ($row['_area'] !== $place) {
                return false;
            }
        }

        return true;
    }

    /** Whether the page has areas: it may have them, and it holds its main section. */
    private static function hasAreas(array $sections, LayoutRules $rules): bool
    {
        if (! $rules->sidesAllowed()) {
            return false;
        }
        foreach ($sections as $section) {
            if ($rules->isMain($section)) {
                return true;
            }
        }

        return false;
    }

    /** The index of the first row holding a main section, or null. */
    private static function mainRow(array $rows, array $sections, LayoutRules $rules): ?int
    {
        foreach (array_values($rows) as $index => $row) {
            foreach (array_merge(...$row['columns']) as $sid) {
                if ($rules->isMain($sections[$sid])) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Where the main section's row will be among the given rows: its row
     * when that is one column, just after it when it sits in a column (it
     * moves out to a row of its own), at the end when it sits in a side,
     * and after the row of the section before it when it sits nowhere.
     */
    private static function mainPlace(array $given, array $sides, array $known, array $sections, LayoutRules $rules): float|int
    {
        $isMain = fn (mixed $id) => is_string($id) && isset($sections[$id]) && $rules->isMain($sections[$id]);
        foreach (self::SIDES as $which) {
            if (array_filter($sides[$which], $isMain) !== []) {
                return INF;
            }
        }

        $rowOf = [];
        foreach ($given as $index => $row) {
            $columns = is_array($row['columns'] ?? null) ? array_values($row['columns']) : [];
            foreach ($columns as $column) {
                foreach (is_array($column) ? $column : [] as $id) {
                    if (is_string($id) && ! isset($rowOf[$id])) {
                        $rowOf[$id] = $index;
                    }
                }
            }
            foreach ($columns as $column) {
                foreach (is_array($column) ? $column : [] as $id) {
                    if ($isMain($id)) {
                        $split = (string) ($row['split'] ?? '12');

                        return $split === '12' || ! isset(self::SPLITS[$split]) ? $index : $index + 0.5;
                    }
                }
            }
        }

        foreach ($known as $k => $id) {
            if (! $isMain($id)) {
                continue;
            }
            for ($j = $k - 1; $j >= 0; $j--) {
                if (isset($rowOf[$known[$j]])) {
                    return $rowOf[$known[$j]] + 0.5;
                }
            }

            return -0.5;
        }

        return INF;
    }

    /** A row's stored area when it is one of AREAS, else null. */
    private static function storedArea(array $row): ?string
    {
        $area = $row['area'] ?? null;

        return is_string($area) && in_array($area, self::AREAS, true) ? $area : null;
    }

    /** A row without the normalizer's own keys. */
    private static function bare(array $row): array
    {
        return ['id' => $row['id'], 'split' => $row['split'], 'columns' => $row['columns'], 'settings' => $row['settings']];
    }

    /**
     * At most MAX_ROWS rows, so what this method stores is never refused by
     * the next save. The rows it adds (a moved or unplaced section's own
     * row) can push a page at the limit over it; then empty rows go: first
     * those this run emptied by moving their sections out, then other
     * empty rows from the end. Each row left holds a section, and a page
     * holds at most Shape::MAX_SECTIONS (equal to MAX_ROWS), so for any
     * page Shape accepts this always gets back within the limit.
     *
     * @param  list<array>  $rows
     * @param  array<string, true>  $emptied  ids of rows this run emptied
     * @return list<array>
     */
    private static function fitRowLimit(array $rows, array $emptied): array
    {
        $isEmpty = fn (array $row) => array_merge(...$row['columns']) === [];
        $drop = [];
        foreach ($rows as $index => $row) {
            if (isset($emptied[$row['id']]) && $isEmpty($row)) {
                $drop[] = $index;
            }
        }
        for ($index = count($rows) - 1; $index >= 0; $index--) {
            if (! isset($emptied[$rows[$index]['id']]) && $isEmpty($rows[$index])) {
                $drop[] = $index;
            }
        }

        $over = count($rows) - self::MAX_ROWS;
        foreach (array_slice($drop, 0, max(0, $over)) as $index) {
            unset($rows[$index]);
        }

        return array_values($rows);
    }

    /**
     * Why a section cannot sit in a column or side $span twelfths wide, or
     * null when it can. A column's span is its part of an area $content
     * twelfths wide, so it really draws $span x $content / 12 twelfths of
     * the page; a side is always measured on the whole page.
     */
    private static function misfit(array $section, int $span, LayoutRules $rules, int $content = 12): ?string
    {
        if ($rules->isMain($section)) {
            return 'main-section';
        }
        if ($rules->ownRow($section)) {
            return 'own-row';
        }

        return $rules->minSpan($section) * 12 > $span * $content ? 'too-wide' : null;
    }

    /** @return array{path: string, reason: string, value: string} */
    private static function moved(string $sectionId, string $reason, string $rowId): array
    {
        return ['path' => "content.sections.{$sectionId}", 'reason' => $reason, 'value' => $rowId];
    }

    /** Exactly $count columns: missing ones empty, extra ones poured into the last. */
    private static function fitColumns(array $columns, int $count): array
    {
        while (count($columns) < $count) {
            $columns[] = [];
        }
        if (count($columns) > $count) {
            $extra = array_splice($columns, $count);
            $columns[$count - 1] = array_merge($columns[$count - 1], ...$extra);
        }

        return $columns;
    }

    /** Whether the rows are exactly the ones rows() gives the document without them. */
    private static function sayNoMoreThanOrder(array $rows, array $content): bool
    {
        foreach ($rows as $row) {
            if ($row['split'] !== '12' || $row['settings'] !== self::ROW_DEFAULTS) {
                return false;
            }
        }

        return $rows === self::impliedRows($content);
    }

    /** @return list<array{id: string, split: string, columns: list<list<string>>, settings: array}> */
    private static function impliedRows(array $content): array
    {
        $sections = [];
        foreach ((array) ($content['sections'] ?? []) as $id => $section) {
            $sections[(string) $id] = true;
        }
        $inSides = [];
        $sides = is_array($content['sides'] ?? null) ? $content['sides'] : [];
        foreach (self::SIDES as $which) {
            foreach (is_array($sides[$which] ?? null) ? $sides[$which] : [] as $id) {
                if (is_string($id)) {
                    $inSides[$id] = true;
                }
            }
        }

        $taken = $sections;
        $rows = [];
        $seen = [];
        foreach (is_array($content['order'] ?? null) ? $content['order'] : [] as $id) {
            if (! is_string($id) || ! isset($sections[$id]) || isset($inSides[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = self::bare(self::plainRow($id, $taken));
        }

        return $rows;
    }

    /** The index of the row holding the section, or null. */
    private static function rowOf(array $rows, string $sectionId): ?int
    {
        foreach ($rows as $index => $row) {
            foreach ($row['columns'] as $column) {
                if (in_array($sectionId, $column, true)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * A full-width row for one section, with a fresh id and no area yet;
     * $inherit when it is split off the row before it, whose area it takes.
     */
    private static function plainRow(string $sectionId, array &$taken, bool $inherit = false): array
    {
        return self::row(self::freshId($sectionId, $taken), '12', [[$sectionId]], self::ROW_DEFAULTS) + ['_area' => null, '_inherit' => $inherit];
    }

    private static function row(string $id, string $split, array $columns, array $settings): array
    {
        return ['id' => $id, 'split' => $split, 'columns' => $columns, 'settings' => $settings];
    }

    /** A row's settings with every key, defaults where a value is missing; values are checked by Shape. */
    private static function settings(mixed $settings): array
    {
        $given = is_array($settings) || is_object($settings) ? (array) $settings : [];
        $out = self::ROW_DEFAULTS;
        foreach (array_keys($out) as $key) {
            if (is_string($given[$key] ?? null)) {
                $out[$key] = $given[$key];
            }
        }

        return $out;
    }

    /**
     * rowIdFor() against a set of taken ids, which it adds the new id to.
     * Section and row ids share one namespace (both mark the page's HTML),
     * so the set holds both. At most 40 characters, the longest id a
     * document allows. When every length of the seed's hash is taken (many
     * empty rows share the seed `row`), the seed gets a number (`row`
     * becomes `row-2`, then `row-3`, ...) and that is hashed the same way.
     *
     * @param  array<string, true>  $taken
     */
    private static function freshId(string $sectionId, array &$taken): string
    {
        for ($n = 1; $n <= 1000; $n++) {
            $hash = sha1($n === 1 ? $sectionId : "{$sectionId}-{$n}");
            for ($length = 8; $length <= 36; $length += 2) {
                $id = 'row-' . substr($hash, 0, $length);
                if (! isset($taken[$id])) {
                    $taken[$id] = true;

                    return $id;
                }
            }
        }

        throw new \LogicException("No free row id for section {$sectionId}.");
    }
}
