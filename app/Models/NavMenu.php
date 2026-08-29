<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A composed menu: main, footer, a mobile one, any the merchant names. */
class NavMenu extends Model
{
    public const MAIN = 'main';

    protected $fillable = ['key', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(NavMenuItem::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public static function main(): self
    {
        return static::firstOrCreate(['key' => self::MAIN], ['name' => 'Main menu']);
    }
}
