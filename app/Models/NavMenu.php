<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A composed menu: main, footer, a mobile one, any the merchant names.
 *
 * Themes find a menu by its key, so the key is set once, from the name the
 * menu was created with, and never changes when the menu is renamed.
 */
class NavMenu extends Model
{
    public const MAIN = 'main';

    /**
     * Menus the store cannot do without: the default storefront layout
     * draws the main menu in its header, so it can be renamed but never
     * deleted.
     */
    public const REQUIRED = [self::MAIN];

    /** The longest key a new menu is given; the column holds 64. */
    private const KEY_MAX = 60;

    protected $fillable = ['key', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(NavMenuItem::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public static function main(): self
    {
        return static::firstOrCreate(['key' => self::MAIN], ['name' => 'Main menu']);
    }

    public function isRequired(): bool
    {
        return in_array($this->key, self::REQUIRED, true);
    }

    /**
     * A key for a new menu called $name: the name as a slug, made unique
     * with a number ("footer", "footer-2"). A name with no letters or
     * digits a slug can keep becomes "menu".
     */
    public static function keyFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), self::KEY_MAX, '');
        $base = trim($base, '-') !== '' ? trim($base, '-') : 'menu';

        $taken = static::query()->where('key', $base)->orWhere('key', 'like', addcslashes($base, '%_\\') . '-%')
            ->pluck('key')->all();

        $key = $base;
        for ($n = 2; in_array($key, $taken, true); $n++) {
            $key = $base . '-' . $n;
        }

        return $key;
    }
}
