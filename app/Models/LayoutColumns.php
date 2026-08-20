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

    protected $fillable = ['page_type', 'left_width', 'right_width'];

    protected $casts = [
        'left_width' => 'integer',
        'right_width' => 'integer',
    ];

    /**
     * The widths for one page type, always a complete answer.
     *
     * A page nobody has configured has no columns, which is the same shape as
     * a page configured to have none. The caller never has to know which.
     */
    public static function forPage(?string $pageType): array
    {
        $row = $pageType ? static::where('page_type', $pageType)->first() : null;

        $left = $row->left_width ?? 0;
        $right = $row->right_width ?? 0;

        // Clamped rather than trusted. A bad row must not be able to render a
        // page whose content column is zero or negative columns wide.
        $left = max(0, min($left, self::GRID - self::MIN_CONTENT));
        $right = max(0, min($right, self::GRID - self::MIN_CONTENT - $left));

        return [
            'left' => $left,
            'right' => $right,
            'content' => self::GRID - $left - $right,
        ];
    }
}
