<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which permission an API request needs.
 *
 * Deliberately a map rather than a rule read off the URL. Fulfillment's reads
 * live under /orders/{id}/tracking, /label, /serviceability and so on, so a
 * key permitted to read orders would otherwise inherit the parcel operations
 * too. And POST does not mean write here: /estimate asks a courier what a
 * booking WOULD cost and changes nothing, so it is a read.
 *
 * Every api/v1 route must appear. ApiAbilityCoverageTest fails if one does
 * not, because an unmapped route would otherwise be reachable by any key at
 * all, which is the quiet way a permission system stops meaning anything.
 */
class ApiAbility
{
    public const AREAS = ['catalogue', 'orders', 'fulfillment', 'reviews'];

    public const MODES = ['read', 'write'];

    /**
     * Area and mode per route, as [method, path pattern] => [area, mode].
     *
     * Patterns are matched with Laravel's own route wildcards, so `*` stands
     * for one or more segments. Order matters: the first match wins, so the
     * specific paths sit above the general ones.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function map(): array
    {
        return [
            // Catalogue writes, above the reads they would otherwise match.
            ['POST',   'api/v1/products/*/images',   'catalogue',   'write'],
            ['PUT',    'api/v1/products/*/qty',      'catalogue',   'write'],
            ['POST',   'api/v1/products/*/variants', 'catalogue',   'write'],
            ['GET',    'api/v1/products/*/variants', 'catalogue',   'read'],
            ['POST',   'api/v1/products',            'catalogue',   'write'],
            ['PUT',    'api/v1/products/*',          'catalogue',   'write'],
            ['DELETE', 'api/v1/products/*',          'catalogue',   'write'],
            ['GET',    'api/v1/products',            'catalogue',   'read'],
            ['GET',    'api/v1/products/*',          'catalogue',   'read'],
            ['PUT',    'api/v1/variants/*',          'catalogue',   'write'],
            ['GET',    'api/v1/variants/*',          'catalogue',   'read'],
            ['GET',    'api/v1/categories',          'catalogue',   'read'],
            ['GET',    'api/v1/brands',              'catalogue',   'read'],

            // Fulfillment, including the parts that live on the order path.
            ['POST',   'api/v1/orders/*/book-courier',   'fulfillment', 'write'],
            ['POST',   'api/v1/orders/*/book-manual',    'fulfillment', 'write'],
            ['POST',   'api/v1/orders/*/cancel-booking', 'fulfillment', 'write'],
            ['POST',   'api/v1/orders/*/clear-manual',   'fulfillment', 'write'],
            // A quote, not a booking: it asks the courier what a shipment
            // would cost and changes nothing, so a read key may ask.
            ['POST',   'api/v1/orders/*/estimate',       'fulfillment', 'read'],
            ['GET',    'api/v1/orders/*/label',          'fulfillment', 'read'],
            ['GET',    'api/v1/orders/*/pickup-slots',   'fulfillment', 'read'],
            ['GET',    'api/v1/orders/*/serviceability', 'fulfillment', 'read'],
            ['GET',    'api/v1/orders/*/tracking',       'fulfillment', 'read'],
            ['GET',    'api/v1/pickup-addresses',        'fulfillment', 'read'],
            ['GET',    'api/v1/shipping-couriers',       'fulfillment', 'read'],

            // Orders, after fulfillment has claimed its own nested routes.
            ['PUT',    'api/v1/orders/*/status', 'orders', 'write'],
            ['GET',    'api/v1/order-statuses',  'orders', 'read'],
            ['GET',    'api/v1/orders',          'orders', 'read'],
            ['GET',    'api/v1/orders/*',        'orders', 'read'],

            ['POST',   'api/v1/reviews', 'reviews', 'write'],
            ['GET',    'api/v1/reviews', 'reviews', 'read'],
        ];
    }

    /**
     * What this request needs, or null when nothing claims it.
     *
     * Null is a refusal, never a pass: the middleware turns it into a 403.
     *
     * @return array{0: string, 1: string}|null [area, mode]
     */
    public static function for(Request $request): ?array
    {
        $path = trim($request->path(), '/');
        $method = strtoupper($request->method());

        foreach (self::map() as [$verb, $pattern, $area, $mode]) {
            if ($method === $verb && \Illuminate\Support\Str::is($pattern, $path)) {
                return [$area, $mode];
            }
        }

        return null;
    }
}
