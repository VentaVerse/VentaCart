<?php

namespace App\Support;

use App\Services\ExtensionManager;
use Illuminate\Support\Facades\Route;

/**
 * One extension row, shaped for the standard button set (owner ruling
 * 2026-08-18: not installed shows Install and nothing else; installed
 * shows Uninstall, Enable/Disable, Configure — on every listing). The
 * directory, the Shipping chain, and the Payments page all build their
 * rows here so the grammar cannot drift between pages.
 */
class ExtensionCard
{
    /**
     * Manifest type => directory category (mirroring OpenCart's typed
     * extension views). Unknown types land in Modules so a new type is
     * never invisible.
     */
    public const CATEGORY_BY_TYPE = [
        'feature' => 'Modules',
        'courier' => 'Shipping',
        'shipping' => 'Shipping',
        'payment' => 'Payments',
        'theme' => 'Themes',
    ];

    public static function for(array $manifest, ExtensionManager $manager): array
    {
        $name = $manifest['name'];
        $state = $manager->state($name);
        $registryRow = $manager->registry()->get($name);

        $stateMeta = [
            'not_installed' => ['label' => 'Not installed', 'tone' => 'neutral'],
            'enabled' => ['label' => 'Enabled', 'tone' => 'success'],
            'disabled' => ['label' => 'Disabled', 'tone' => 'neutral'],
            'needs_migration' => ['label' => 'Update available', 'tone' => 'warning'],
            'missing_files' => ['label' => 'Missing files', 'tone' => 'danger'],
        ][$state];

        return [
            'kind' => 'extension',
            'category' => self::CATEGORY_BY_TYPE[$manifest['type'] ?? 'feature'] ?? 'Modules',
            'name' => $name,
            'title' => $manifest['title'] ?? $name,
            'description' => $manifest['description'] ?? '',
            'icon' => $manifest['icon'] ?? 'categories',
            'version' => $registryRow->version ?? ($manifest['version'] ?? null),
            'state' => $state,
            'state_label' => $stateMeta['label'],
            'state_tone' => $stateMeta['tone'],
            'actions' => self::actionsForState($state),
            'configure' => $state === 'enabled' ? self::configureUrl($manifest, $name) : null,
        ];
    }

    /**
     * The card's "Configure" link target for an enabled extension.
     *
     * Prefers an explicit `configure_route` in extension.json — needed for
     * any extension (courier-fulfillment included) whose main admin surface
     * is an *operational* page at a frozen route name that doesn't match
     * the "admin.{name}.index" convention. Route::has() guards both
     * branches so a manifest typo or an extension whose provider hasn't
     * booted yet just hides the link rather than 500ing.
     */
    private static function configureUrl(array $manifest, string $name): ?string
    {
        $route = $manifest['configure_route'] ?? "admin.{$name}.index";

        return Route::has($route) ? route($route, $manifest['configure_params'] ?? []) : null;
    }

    private static function actionsForState(string $state): array
    {
        return match ($state) {
            'not_installed' => ['install'],
            'enabled' => ['disable', 'uninstall'],
            'disabled' => ['enable', 'uninstall'],
            'needs_migration' => ['install', 'uninstall'],
            // missing_files: manifest is gone, so discovered() won't
            // validate any action against it anyway — no button would be
            // actionable, so none is shown.
            default => [],
        };
    }
}
