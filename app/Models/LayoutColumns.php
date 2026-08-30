<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LayoutColumns extends Model
{
    /** The row is eight columns wide. Journal's is twelve; eighths divide better. */
    public const GRID = 8;

    /** Both sides together may not leave the content narrower than this. */
    public const MIN_CONTENT = 3;

    protected $table = 'layout_columns';

    protected $fillable = ['theme', 'page_type', 'left_width', 'right_width', 'settings'];

    protected $casts = [
        'left_width' => 'integer',
        'right_width' => 'integer',
        'settings' => 'array',
    ];

    /**
     * The widths for one page type, always a complete answer.
     *
     * A page nobody has configured has no columns, which is the same shape as
     * a page configured to have none. The caller never has to know which.
     *
     * All pages is a real answer, not a placeholder. Placements made there
     * already appear on every page, so the editor teaches a merchant that the
     * screen means everywhere; columns and page parts set there used to be
     * written and never read, which broke that promise twice on the same
     * screen. A concrete page overrides, and silence there defers upward.
     */
    public static function forPage(?string $pageType, ?string $theme = null): array
    {
        $theme = $theme ?: app(\App\Services\ThemeService::class)->getActiveTheme();

        // A specific page (one category, one level) takes its own columns
        // when it has them, else the nearest key up its chain.
        $row = $pageType === LayoutPlacement::EVERYWHERE
            ? static::where('theme', $theme)->where('page_type', LayoutPlacement::EVERYWHERE)->first()
            : null;
        foreach ($pageType ? \App\Support\LayoutPage::chain($pageType) : [] as $key) {
            if ($key === LayoutPlacement::EVERYWHERE) {
                break;
            }
            $candidate = static::where('theme', $theme)->where('page_type', $key)->first();
            if ($candidate && ($row === null)) {
                $row = $candidate;
            }
            if ($candidate && ($candidate->left_width > 0 || $candidate->right_width > 0)) {
                $row = $candidate;
                break;
            }
        }

        $every = $pageType === LayoutPlacement::EVERYWHERE
            ? null
            : static::where('theme', $theme)->where('page_type', LayoutPlacement::EVERYWHERE)->first();

        // A page that has never been given columns of its own takes the ones
        // set for every page. A page that HAS been given them, including a
        // deliberate zero, keeps its own.
        $hasOwn = $row && ($row->left_width > 0 || $row->right_width > 0);
        $widths = $hasOwn ? $row : ($every ?: $row);

        $left = $widths->left_width ?? 0;
        $right = $widths->right_width ?? 0;

        // Clamped rather than trusted. A bad row must not be able to render a
        // page whose content column is zero or negative columns wide.
        $left = max(0, min($left, self::GRID - self::MIN_CONTENT));
        $right = max(0, min($right, self::GRID - self::MIN_CONTENT - $left));

        return [
            'left' => $left,
            'right' => $right,
            'content' => self::GRID - $left - $right,
            // Merged key by key, not row by row: a merchant who hid the
            // footer everywhere and the breadcrumbs on one page has answered
            // two different questions and must keep both answers.
            'settings' => array_merge($every->settings ?? [], $row->settings ?? []),
        ];
    }
}
