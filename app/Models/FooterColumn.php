<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class FooterColumn extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'type', 'config', 'sort_order', 'is_enabled'];

    /**
     * Where each column type keeps its entries in config: what the footer
     * editor saves and every theme reads.
     */
    private const ENTRY_KEYS = [
        'links' => 'links',
        'social' => 'links',
        'payment_icons' => 'icons',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * The column's settings as an array. Columns written with their entries
     * under `items` (an earlier seeder and "add column" did) read with them
     * under the key the editor and themes use, so their entries show. Saving
     * the column from the editor stores that key.
     */
    protected function config(): Attribute
    {
        return Attribute::make(
            get: function (?string $value, array $attributes): array {
                $config = $value === null ? [] : json_decode($value, true);
                $config = is_array($config) ? $config : [];
                $key = self::ENTRY_KEYS[$attributes['type'] ?? ''] ?? null;

                if ($key !== null && array_key_exists('items', $config)) {
                    if (!isset($config[$key]) && is_array($config['items'])) {
                        $config[$key] = $config['items'];
                    }
                    unset($config['items']);
                }

                return $config;
            },
            set: fn ($value) => $value === null ? null : json_encode($value),
        );
    }
}
