<?php

namespace App\Support;

use App\Services\ExtensionManager;

class PaymentFamilies
{
    /**
     * Method families the payments UI knows how to render, manifest-driven
     * (wave 2): every discovered payment-type extension declares its
     * family key, default name, and description in extension.json. Shared
     * by the landing page (one row per family) and the detail page (one
     * page per family) so the two surfaces never drift out of sync.
     *
     * enabledFamilies() narrows to families whose extension is enabled —
     * the set the detail page serves and restrictions can bind to.
     *
     * @return array<string, array{default_name: string, description: string, extension: string}>
     */
    public static function all(): array
    {
        return self::families(false);
    }

    /** @return array<string, array{default_name: string, description: string, extension: string}> */
    public static function enabledFamilies(): array
    {
        return self::families(true);
    }

    private static function families(bool $enabledOnly): array
    {
        $manager = app(ExtensionManager::class);

        $families = [];
        foreach ($manager->discovered() as $manifest) {
            if (($manifest['type'] ?? '') !== 'payment' || empty($manifest['family'])) {
                continue;
            }
            if ($enabledOnly && $manager->state($manifest['name']) !== 'enabled') {
                continue;
            }
            $families[$manifest['family']] = [
                'default_name' => $manifest['title'] ?? $manifest['family'],
                'description' => $manifest['description'] ?? '',
                'extension' => $manifest['name'],
            ];
        }

        return $families;
    }
}
