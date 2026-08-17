<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The master switches for built-in features (the "Core features" rows on
 * the Modules page). Extensions have their lifecycle in ExtensionManager;
 * core features are never uninstallable, but the merchant can still turn
 * one OFF: its nav entry hides (via admin-nav's 'enabled_setting'), its
 * scheduled commands no-op, and its Modules row says so.
 *
 * Opt-out semantics throughout: a missing setting means enabled, matching
 * fulfillment's courier.fulfillment_enabled precedent, so a fresh install
 * ships with everything on and no seeded rows.
 *
 * Distinct from a feature's own operational toggles: Abandoned Carts'
 * abandoned_cart.enabled (send recovery emails or not) stays a setting
 * INSIDE the feature; this switch is whether the feature exists for this
 * store at all.
 */
class CoreFeatures
{
    /** @var array<string, array{0: string, 1: string}> feature key => [settings group, settings key] */
    public const FEATURES = [
        'seo' => ['features', 'seo_enabled'],
        'abandoned-carts' => ['features', 'abandoned_carts_enabled'],
    ];

    /** @var array<string, bool>|null per-request memo */
    protected static ?array $memo = null;

    public static function enabled(string $feature): bool
    {
        if (! isset(self::FEATURES[$feature])) {
            return true;
        }

        return self::all()[$feature];
    }

    public static function set(string $feature, bool $on): void
    {
        [$group, $key] = self::FEATURES[$feature];

        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $on ? '1' : '0', 'type' => 'text'],
        );

        self::$memo = null;
    }

    /** @return array<string, bool> */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $stored = Setting::where('group', 'features')
            ->pluck('value', 'key');

        $states = [];
        foreach (self::FEATURES as $feature => [$group, $key]) {
            $states[$feature] = ($stored[$key] ?? null) !== '0';
        }

        return self::$memo = $states;
    }
}
