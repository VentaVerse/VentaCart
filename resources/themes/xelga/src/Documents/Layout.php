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
 * `order` stays in every document as the reading order, derived here
 * from the rows and sides: left side, then each row's columns left to
 * right, then right side. That is also the DOM order, so phones stack the
 * page the way it reads.
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

    public const MAX_ROWS = 40;

    public const MAX_PER_COLUMN = 6;

    public const MAX_PER_SIDE = 6;

    /** How wide each side is, in twelfths of the page. */
    public const SIDE_SPAN = 3;

    public const SIDES = ['left', 'right'];

    /**
     * Whether a document may have sides: page templates only, never the
     * header or footer, and not the cart, which stays focused on buying.
     */
    public static function sidesAllowed(string $handle): bool
    {
        return Handles::kind($handle) === 'template' && Handles::pageType($handle) !== 'cart';
    }

    /**
     * What the editor needs to build rows and sides, so the splits, the
     * defaults and the limits have one source.
     *
     * @return array{splits: array, row_defaults: array, max_rows: int, max_per_column: int, max_per_side: int, side_span: int, sides_on: list<string>}
     */
    public static function payload(): array
    {
        return [
            'splits' => self::SPLITS,
            'row_defaults' => self::ROW_DEFAULTS,
            'max_rows' => self::MAX_ROWS,
            'max_per_column' => self::MAX_PER_COLUMN,
            'max_per_side' => self::MAX_PER_SIDE,
            'side_span' => self::SIDE_SPAN,
            'sides_on' => array_values(array_filter(Handles::TEMPLATES, fn (string $type) => self::sidesAllowed($type))),
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
     * @return list<array{id: string, split: string, columns: list<list<string>>, settings: array}>
     */
    public static function rows(array $content): array
    {
        if (! is_array($content['rows'] ?? null)) {
            return self::impliedRows($content);
        }

        $rows = [];
        foreach ($content['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $columns = [];
            foreach (is_array($row['columns'] ?? null) ? $row['columns'] : [] as $column) {
                $columns[] = array_values(array_filter(is_array($column) ? $column : [], 'is_string'));
            }
            $rows[] = self::row((string) ($row['id'] ?? ''), (string) ($row['split'] ?? '12'), $columns, self::settings($row['settings'] ?? []));
        }

        return $rows;
    }

    /**
     * The document with every section placed once and `order` derived from
     * where it sits. Never refuses: what does not fit is moved and reported.
     *
     * - A section in no row and no side (an extension placement, a section
     *   added by an older editor) gets its own full-width row after the row
     *   of the nearest section before it in `order`, or first.
     * - A section too wide for its column or side, or a main section in a
     *   column or side, gets its own full-width row: after its row, or at
     *   the end when it came from a side. A theme update that narrows a
     *   section's span can so never make a stored page invalid.
     * - Sides on a page that cannot have them go to rows at the end.
     * - Ids that name no section are dropped, a one-column row holds one
     *   section, an empty one-column row goes; an empty multi-column row
     *   stays, since the merchant made it to fill.
     * - Empty sides are dropped, and rows that say no more than `order`
     *   does (all plain full-width rows with the ids rows() would give)
     *   are dropped, so a document only keeps `rows` when it needs them.
     *
     * A document with neither `rows` nor `sides` is returned as it is.
     * Reads no database; running it twice changes nothing more.
     *
     * @return array{content: array, moved: list<array{path: string, reason: string, value: string}>}
     */
    public static function normalize(array $content, LayoutRules $rules): array
    {
        if (! array_key_exists('rows', $content) && ! array_key_exists('sides', $content)) {
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

        $sides = ['left' => [], 'right' => []];
        $givenSides = is_array($content['sides'] ?? null) ? $content['sides'] : [];
        foreach (self::SIDES as $which) {
            foreach (is_array($givenSides[$which] ?? null) ? $givenSides[$which] : [] as $id) {
                if ($claim($id)) {
                    $sides[$which][] = $id;
                }
            }
        }

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
            // An empty row with no usable id gets one from a fixed seed, lengthened while taken,
            // so its id does not depend on where the row sits.
            $seed = array_merge(...$columns)[0] ?? 'row';

            if ($split === '12') {
                $column = $columns[0];
                if ($column === []) {
                    continue;
                }
                $first = array_shift($column);
                $rows[] = self::row($ids[$i] ?? self::freshId($first, $taken), '12', [[$first]], $settings);
                foreach ($column as $id) {
                    $rows[] = self::plainRow($id, $taken);
                }
                continue;
            }

            $id = $ids[$i] ?? self::freshId($seed, $taken);
            $misfits = [];
            foreach ($columns as $c => $column) {
                $keep = [];
                foreach ($column as $sid) {
                    $reason = self::misfit($sections[$sid], $parts[$c], $rules);
                    if ($reason === null) {
                        $keep[] = $sid;
                    } else {
                        $misfits[] = [$sid, $reason];
                    }
                }
                $columns[$c] = $keep;
            }
            $rows[] = self::row($id, $split, $columns, $settings);
            foreach ($misfits as [$sid, $reason]) {
                $rows[] = $plain = self::plainRow($sid, $taken);
                $moved[] = self::moved($sid, $reason, $plain['id']);
            }
        }

        // Sides: what cannot sit in one goes to its own row at the end.
        $sidesAllowed = $rules->sidesAllowed();
        foreach (self::SIDES as $which) {
            $keep = [];
            foreach ($sides[$which] as $sid) {
                $reason = $sidesAllowed ? self::misfit($sections[$sid], self::SIDE_SPAN, $rules) : 'no-sides';
                if ($reason === null) {
                    $keep[] = $sid;
                    continue;
                }
                $rows[] = $plain = self::plainRow($sid, $taken);
                $moved[] = self::moved($sid, $reason, $plain['id']);
            }
            $sides[$which] = $keep;
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

        $order = $sides['left'];
        foreach ($rows as $row) {
            foreach ($row['columns'] as $column) {
                array_push($order, ...$column);
            }
        }
        array_push($order, ...$sides['right']);

        $sides = array_filter($sides, fn (array $ids) => $ids !== []);

        $out = $content;
        $out['order'] = $order;
        unset($out['rows'], $out['sides']);
        if (! self::sayNoMoreThanOrder($rows, ['order' => $order, 'sections' => $content['sections'] ?? [], 'sides' => $sides])) {
            $out['rows'] = $rows;
        }
        if ($sides !== []) {
            $out['sides'] = $sides;
        }

        return ['content' => $out, 'moved' => $moved];
    }

    /** Why a section cannot sit in a column or side this wide, or null when it can. */
    private static function misfit(array $section, int $span, LayoutRules $rules): ?string
    {
        if ($rules->isMain($section)) {
            return 'main-section';
        }

        return $rules->minSpan($section) > $span ? 'too-wide' : null;
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
            $rows[] = self::plainRow($id, $taken);
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

    /** A full-width row for one section, with a fresh id. */
    private static function plainRow(string $sectionId, array &$taken): array
    {
        return self::row(self::freshId($sectionId, $taken), '12', [[$sectionId]], self::ROW_DEFAULTS);
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
     * document allows.
     *
     * @param  array<string, true>  $taken
     */
    private static function freshId(string $sectionId, array &$taken): string
    {
        $hash = sha1($sectionId);
        for ($length = 8; $length <= 36; $length += 2) {
            $id = 'row-' . substr($hash, 0, $length);
            if (! isset($taken[$id])) {
                $taken[$id] = true;

                return $id;
            }
        }

        throw new \LogicException("No free row id for section {$sectionId}.");
    }
}
