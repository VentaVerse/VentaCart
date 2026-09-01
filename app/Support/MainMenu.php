<?php

namespace App\Support;

use App\Models\Category;
use App\Models\NavMenu;
use App\Models\NavMenuItem;

/**
 * Putting a category in the main menu, from wherever the merchant happens to
 * be standing.
 *
 * The menu composer can build anything: custom links, pages, brand dropdowns,
 * nesting. What it could not do was be found, and the one thing almost every
 * merchant wants from a menu is their departments in it. That belongs on the
 * category, next to the category's own name, the way OpenCart has always had
 * it: a tick, and it is in the menu.
 *
 * A category item builds its own subcategory dropdown, so ticking Computers
 * puts Laptops and Monitors under it without anybody arranging anything.
 */
class MainMenu
{
    /** Is this category in the main menu? */
    public static function holds(Category $category): bool
    {
        return self::item($category) !== null;
    }

    /**
     * Put it in, or take it out, to match the tick.
     *
     * Position is set when the item is created and never afterwards. The
     * composer's drag is the menu's order, and a category saved for an
     * unrelated reason must not quietly undo it.
     */
    public static function set(Category $category, bool $wanted): void
    {
        $item = self::item($category);

        if (! $wanted) {
            if ($item) {
                $item->delete();
                NavTree::forget();
            }

            return;
        }

        if ($item) {
            // Keep the label honest when the category is renamed, and nothing
            // else: the merchant may have moved or retitled it deliberately.
            if ($item->label === '' || $item->label === null) {
                $item->update(['label' => $category->name]);
                NavTree::forget();
            }

            return;
        }

        $menu = self::menu();

        NavMenuItem::create([
            'menu_id' => $menu->id,
            'label' => $category->name,
            'type' => 'category',
            'category_id' => $category->id,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'is_enabled' => true,
        ]);

        NavTree::forget();
    }

    private static function item(Category $category): ?NavMenuItem
    {
        $menu = NavMenu::where('key', NavMenu::MAIN)->first();

        if (! $menu) {
            return null;
        }

        return NavMenuItem::where('menu_id', $menu->id)
            ->where('type', 'category')
            ->where('category_id', $category->id)
            ->first();
    }

    private static function menu(): NavMenu
    {
        return NavMenu::firstOrCreate(
            ['key' => NavMenu::MAIN],
            ['name' => 'Main menu']
        );
    }
}
