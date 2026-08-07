<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Cart;
use App\Models\SeoSuggestion;
use App\Models\Setting;
use App\Models\StockNotification;
use App\Services\ExtensionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class ModuleController extends Controller
{
    /**
     * States (per ExtensionManager::state()) that legally allow each
     * lifecycle action. Enforced here, server-side, so a replayed or
     * hand-crafted request can't drive an extension through a nonsensical
     * transition (e.g. "enable" on something that was never installed) —
     * ExtensionManager's own lifecycle methods would either throw
     * (ModelNotFoundException from enable()/disable()'s firstOrFail()) or,
     * worse, silently do the wrong thing; neither is a good admin-facing
     * failure mode. The Modules page never renders a button for a
     * disallowed transition (see extensionCard()/actionsForState() below),
     * so hitting this branch means the request didn't come from the
     * rendered form — a forged/stale request, not a real user flow.
     */
    private const ALLOWED_FROM_STATE = [
        'install' => ['not_installed', 'needs_migration'],
        'enable' => ['disabled'],
        'disable' => ['enabled'],
        'uninstall' => ['enabled', 'disabled', 'needs_migration'],
    ];

    public function __construct(protected ExtensionManager $manager)
    {
    }

    public function index()
    {
        $automations = Automation::whereIn('key', ['seo_generate', 'stock_notify', 'stock_check_low'])->get()->keyBy('key');
        $flags = Setting::whereIn('group', ['stock', 'abandoned_cart'])->get()
            ->groupBy('group')->map(fn ($rows) => $rows->pluck('value', 'key'));

        $backInStock = (bool) ($automations['stock_notify']->enabled ?? false);
        $lowStock = ($flags['stock']['low_stock_alert_enabled'] ?? '0') === '1'
            && (bool) ($automations['stock_check_low']->enabled ?? false);
        $stockAlertEnabled = $backInStock || $lowStock;
        $seoAutoEnabled = (bool) ($automations['seo_generate']->enabled ?? false);
        $abandonedCartEnabled = ($flags['abandoned_cart']['enabled'] ?? '0') === '1';

        // Core features: built-in, always-present functionality — never
        // uninstallable, so no lifecycle buttons. Kept as a hardcoded list
        // (no extension.json — they're not extensions) per the brief.
        $coreFeatures = [
            [
                'key' => 'stock-alerts',
                'name' => 'Stock Alerts',
                'description' => 'Back-in-stock customer emails and low-stock admin warnings.',
                'icon' => 'warning',
                'enabled' => $stockAlertEnabled,
                'status_label' => 'Back-in-stock ' . ($backInStock ? 'on' : 'off') . ' · Low-stock ' . ($lowStock ? 'on' : 'off'),
                'tone' => ($backInStock && $lowStock) ? 'success' : (($backInStock || $lowStock) ? 'warning' : 'neutral'),
                'stats' => StockNotification::where('status', 'waiting')->count() . ' waiting',
                'configure' => route('admin.stock-notifications.index'),
            ],
            [
                'key' => 'seo',
                'name' => 'SEO',
                'description' => 'AI meta suggestions, redirects, and Search Console.',
                'icon' => 'seo',
                'enabled' => $seoAutoEnabled,
                'status_label' => $seoAutoEnabled ? 'Auto-generating' : 'Manual only',
                'stats' => SeoSuggestion::where('status', 'pending')->count() . ' pending suggestions',
                'configure' => route('admin.extensions.seo.settings'),
            ],
            [
                'key' => 'abandoned-carts',
                'name' => 'Abandoned Carts',
                'description' => 'Cart recovery email rules and tracking.',
                'icon' => 'cart',
                'enabled' => $abandonedCartEnabled,
                'status_label' => $abandonedCartEnabled ? 'Active' : 'Disabled',
                'stats' => Cart::whereHas('items')->where('updated_at', '<', now()->subHour())->count() . ' tracked carts',
                'configure' => route('admin.abandoned-carts.settings'),
            ],
        ];

        $extensions = $this->manager->discovered()
            ->map(fn (array $manifest) => $this->extensionCard($manifest))
            ->values();

        return view('admin.settings.modules.index', [
            'coreFeatures' => $coreFeatures,
            'extensions' => $extensions,
        ]);
    }

    public function install(string $name): RedirectResponse
    {
        return $this->runLifecycle($name, 'install', 'installed');
    }

    public function enable(string $name): RedirectResponse
    {
        return $this->runLifecycle($name, 'enable', 'enabled');
    }

    public function disable(string $name): RedirectResponse
    {
        return $this->runLifecycle($name, 'disable', 'disabled');
    }

    public function uninstall(string $name): RedirectResponse
    {
        return $this->runLifecycle($name, 'uninstall', 'uninstalled');
    }

    /**
     * Shared body for install/enable/disable/uninstall.
     *
     * Security note: {$name} (the raw route parameter) is validated against
     * discovered() — ExtensionManager's filesystem scan of extensions/*\/
     * extension.json — and NEVER against a DB lookup of the raw input. The
     * `extensions` table is a mutable registry (rows can exist without
     * corresponding files — "missing_files" state); trusting it as the
     * allow-list would let a request act on any name ever written there.
     * discovered() is a small, fixed set the *filesystem*, not the
     * request, controls, so anything not in it 404s before any DB read or
     * write is attempted — the same posture as validating a foreign key
     * against a real table rather than trusting client-supplied IDs.
     */
    private function runLifecycle(string $name, string $action, string $verbPast): RedirectResponse
    {
        $manifest = $this->manager->discovered()->get($name);

        if (! $manifest) {
            abort(404);
        }

        $state = $this->manager->state($name);

        if (! in_array($state, self::ALLOWED_FROM_STATE[$action], true)) {
            return back()->with('error', "Can't {$action} \"{$manifest['title']}\" — it's currently {$state}.");
        }

        $wasCacheStale = $this->manager->routesCacheStale();

        try {
            $this->manager->{$action}($name);
        } catch (\RuntimeException $e) {
            // Thrown by ExtensionManager itself with an already-safe,
            // non-sensitive message (e.g. "no extension.json found") —
            // fine to surface directly.
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Anything else (failed migration, DB error, etc.) may carry
            // details we don't want on an admin screen (schema/table
            // names, driver errors). Log the full exception, flash a
            // generic message.
            Log::error("ModuleController: {$action}('{$name}') failed", ['exception' => $e]);

            return back()->with('error', ucfirst($action) . ' failed. Check the application log for details.');
        }

        $message = "{$manifest['title']} {$verbPast}.";

        if ($wasCacheStale) {
            $message .= ' The compiled route cache was stale and has been automatically cleared, so its routes are live immediately.';
        }

        return redirect()->route('admin.modules.index')->with('success', $message);
    }

    private function extensionCard(array $manifest): array
    {
        $name = $manifest['name'];
        $state = $this->manager->state($name);
        $registryRow = $this->manager->registry()->get($name);

        $stateMeta = [
            'not_installed' => ['label' => 'Not installed', 'tone' => 'neutral'],
            'enabled' => ['label' => 'Enabled', 'tone' => 'success'],
            'disabled' => ['label' => 'Disabled', 'tone' => 'neutral'],
            'needs_migration' => ['label' => 'Update available', 'tone' => 'warning'],
            'missing_files' => ['label' => 'Missing files', 'tone' => 'danger'],
        ][$state];

        return [
            'name' => $name,
            'title' => $manifest['title'] ?? $name,
            'description' => $manifest['description'] ?? '',
            'icon' => $manifest['icon'] ?? 'categories',
            'version' => $registryRow->version ?? ($manifest['version'] ?? null),
            'state' => $state,
            'state_label' => $stateMeta['label'],
            'state_tone' => $stateMeta['tone'],
            'actions' => self::actionsForState($state),
            'configure' => $state === 'enabled' ? $this->configureUrl($manifest, $name) : null,
        ];
    }

    /**
     * The Modules page's "Configure" link target for an enabled extension.
     *
     * Prefers an explicit `configure_route` in extension.json — needed for
     * any extension (courier-fulfillment included) whose main admin surface
     * is an *operational* page at a frozen route name that doesn't match
     * the "admin.{name}.index" convention (its queue lives under Sales, at
     * admin.fulfillment.index — "Configure" on this card means the
     * SETTINGS page, admin.fulfillment.settings, not that queue). Falls
     * back to the original "admin.{name}.index" convention — the settings/
     * modules/{name} mount convention banners established pre-kernel —
     * for every extension that doesn't need the override. Route::has()
     * guards both branches so a manifest typo or an extension whose
     * provider hasn't booted yet just hides the link rather than 500ing.
     */
    private function configureUrl(array $manifest, string $name): ?string
    {
        $route = $manifest['configure_route'] ?? "admin.{$name}.index";

        return Route::has($route) ? route($route) : null;
    }

    private static function actionsForState(string $state): array
    {
        return match ($state) {
            'not_installed' => ['install'],
            'enabled' => ['disable', 'uninstall'],
            'disabled' => ['enable', 'uninstall'],
            'needs_migration' => ['install', 'uninstall'],
            // missing_files: manifest is gone, so discovered() won't
            // validate any action against it anyway (see runLifecycle's
            // docblock) — no button would be actionable, so none is shown.
            default => [],
        };
    }
}
