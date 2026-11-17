<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Links extensions add to the customer's account menu ("My shops",
 * "Returns"), after the links every theme draws itself (dashboard, orders,
 * addresses, wishlist, profile).
 *
 * A link is a route name, never a typed address, so nothing a request
 * carries can reach the menu. It is added from a provider's boot(), so a
 * link whose extension is switched off is simply absent, and a link whose
 * route does not exist is left out.
 */
final class AccountMenu
{
    /** Icons a theme is asked to draw; it draws any other as `file`. */
    public const ICONS = ['file', 'wallet', 'return', 'store', 'star', 'gift'];

    private const ROUTE = '/^[a-z0-9][a-z0-9._-]{0,99}$/';

    private const MATCH = '/^[a-z0-9][a-z0-9._*-]{0,99}$/';

    /** @var array<string, array{route: string, label: string, icon: string, match: string, position: int, order: int}> by route */
    private static array $links = [];

    private static int $added = 0;

    /**
     * @param  string  $route  the route name the link opens, e.g. 'marketplace.account.shops'
     * @param  string  $label  plain text, up to 40 characters
     * @param  string|null  $match  route name pattern that marks the link current, `{route}*` by default
     * @param  int  $position  smaller comes first; links with the same position keep the order they were added in
     */
    public static function add(string $route, string $label, string $icon = 'file', ?string $match = null, int $position = 100): void
    {
        $label = trim(SafeInput::text($label, 40));
        $match ??= $route . '*';
        if (preg_match(self::ROUTE, $route) !== 1 || $label === '' || preg_match(self::MATCH, $match) !== 1) {
            Log::warning('An account menu link was refused: it needs a route name, a label and a route pattern.', ['route' => $route]);

            return;
        }

        self::$links[$route] = [
            'route' => $route,
            'label' => $label,
            'icon' => in_array($icon, self::ICONS, true) ? $icon : 'file',
            'match' => $match,
            'position' => max(0, min(1000, $position)),
            'order' => self::$added++,
        ];
    }

    public static function forget(?string $route = null): void
    {
        if ($route === null) {
            self::$links = [];
            self::$added = 0;

            return;
        }
        unset(self::$links[$route]);
    }

    /**
     * The links whose route exists, by position.
     *
     * @return list<array{url: string, label: string, icon: string, match: string, current: bool}>
     */
    public static function links(): array
    {
        $links = array_filter(self::$links, fn (array $link) => Route::has($link['route']));
        usort($links, fn (array $a, array $b) => [$a['position'], $a['order']] <=> [$b['position'], $b['order']]);

        $request = request();
        $out = [];
        foreach ($links as $link) {
            try {
                $url = route($link['route']);
            } catch (\Throwable) {
                // A route that needs parameters cannot be a menu link.
                continue;
            }
            $out[] = [
                'url' => $url,
                'label' => $link['label'],
                'icon' => $link['icon'],
                'match' => $link['match'],
                'current' => $request->routeIs($link['match']),
            ];
        }

        return $out;
    }
}
