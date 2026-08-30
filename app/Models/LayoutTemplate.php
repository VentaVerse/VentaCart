<?php

namespace App\Models;

use App\Support\RowStyle;
use App\Support\Widgets;
use Illuminate\Database\Eloquent\Model;

/**
 * A named arrangement of rows a merchant kept to reuse: shapes, styles, and
 * the widgets with their settings, as data. Loading one into a region makes
 * new rows in the editor; nothing points back at the template.
 */
class LayoutTemplate extends Model
{
    protected $fillable = ['theme', 'name', 'region', 'rows'];

    protected $casts = ['rows' => 'array'];

    /**
     * Rows as they may be stored or handed to the editor: shapes normalised,
     * styles normalised, only registered widgets, no ids. The same cleaner
     * serves a template save, a JSON import, and a copy to another page.
     *
     * @return list<array{columns: list<int>, enabled: bool, style: array, placements: list<array>}>
     */
    public static function cleanRows(mixed $rows): array
    {
        $out = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $widths = LayoutRow::normalizeColumns($row['columns'] ?? [LayoutRow::GRID]);
            $placements = [];

            foreach (is_array($row['placements'] ?? null) ? $row['placements'] : [] as $p) {
                if (! is_array($p) || ! Widgets::has((string) ($p['widget'] ?? ''))) {
                    continue;
                }
                $placements[] = [
                    'column' => max(0, min((int) ($p['column'] ?? 0), count($widths) - 1)),
                    'widget' => (string) $p['widget'],
                    'title' => isset($p['title']) ? mb_substr((string) $p['title'], 0, 255) : null,
                    'enabled' => (bool) ($p['enabled'] ?? true),
                    'config' => is_array($p['config'] ?? null) ? $p['config'] : [],
                ];
            }

            $out[] = [
                'columns' => $widths,
                'enabled' => (bool) ($row['enabled'] ?? true),
                'style' => RowStyle::normalize($row['style'] ?? []),
                'responsive' => LayoutRow::normalizeResponsive($row['responsive'] ?? [], count($widths)),
                'placements' => $placements,
            ];
        }

        return $out;
    }

    /** The editor's shape for these rows: cards grouped per column, no ids. */
    public static function toUnits(array $rows): array
    {
        $units = [];

        foreach (self::cleanRows($rows) as $row) {
            $cols = array_fill(0, count($row['columns']), []);
            foreach ($row['placements'] as $p) {
                $widget = Widgets::get($p['widget']);
                $cols[$p['column']][] = [
                    'id' => null,
                    'widget' => $p['widget'],
                    'title' => $p['title'],
                    'label' => $widget['title'] ?? $p['widget'],
                    'enabled' => $p['enabled'],
                    'available' => $widget !== null,
                    'config' => $p['config'],
                ];
            }
            $units[] = [
                'id' => null,
                'columns' => $row['columns'],
                'enabled' => $row['enabled'],
                'style' => $row['style'],
                'cols' => $cols,
            ];
        }

        return $units;
    }
}
