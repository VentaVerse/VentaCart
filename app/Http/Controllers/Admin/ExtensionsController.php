<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\SeoSuggestion;
use App\Services\ExtensionManager;
use App\Support\CoreFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class ExtensionsController extends Controller
{
    /**
     * States (per ExtensionManager::state()) that legally allow each
     * lifecycle action. Enforced here, server-side, so a replayed or
     * hand-crafted request can't drive an extension through a nonsensical
     * transition (e.g. "enable" on something that was never installed) —
     * ExtensionManager's own lifecycle methods would either throw
     * (ModelNotFoundException from enable()/disable()'s firstOrFail()) or,
     * worse, silently do the wrong thing; neither is a good admin-facing
     * failure mode. No listing renders a button for a disallowed
     * transition (see App\Support\ExtensionCard::actionsForState()),
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
        return view('admin.extensions.index', [
            'modules' => $this->cards(),
            'heading' => 'Extensions',
            'standing' => 'Every packaged feature the platform ships, and its switch.',
            'showCategory' => true,
        ]);
    }

    /**
     * The Modules category: the residual bucket beside the typed Payments
     * and Shipping pages. Same rows, same lifecycle actions — only the
     * scope narrows.
     */
    public function modules()
    {
        return view('admin.extensions.index', [
            'modules' => $this->cards()->filter(fn (array $m) => $m['category'] === 'Modules')->values(),
            'heading' => 'Modules',
            'standing' => 'Features beyond payments and shipping, and their switches.',
            'showCategory' => false,
        ]);
    }

    private function cards(): \Illuminate\Support\Collection
    {
        $automations = Automation::whereIn('key', ['seo_generate'])->get()->keyBy('key');

        $seoAutoEnabled = (bool) ($automations['seo_generate']->enabled ?? false);

        // The master switches (CoreFeatures). A feature switched OFF hides
        // its nav entry and no-ops its scheduled commands; the sub-states
        // above describe what it is doing while ON.
        $masters = CoreFeatures::all();

        // Core features: built-in, always-present functionality — never
        // uninstallable, so no lifecycle buttons. Kept as a hardcoded list
        // (no extension.json — they're not extensions) per the brief.
        $coreFeatures = [
            [
                'kind' => 'core',
                'category' => 'Modules',
                'key' => 'seo',
                'name' => 'SEO',
                'title' => 'SEO',
                'description' => 'AI meta suggestions, redirects, and Search Console.',
                'icon' => 'seo',
                'enabled' => $masters['seo'],
                'status_label' => ! $masters['seo'] ? 'Disabled' : ($seoAutoEnabled ? 'Auto-generating' : 'Manual only'),
                'stats' => SeoSuggestion::where('status', 'pending')->count() . ' pending suggestions',
                'configure' => route('admin.extensions.seo.settings'),
            ],
        ];

        // One list (owner ruling 2026-08-17): anything shipped in the repo
        // is a core feature of the platform; the filesystem distinction
        // (extensions/ with lifecycle vs app/ with a master switch) shows
        // only in which actions a row offers, never as separate sections.
        return $this->manager->discovered()
            ->map(fn (array $manifest) => \App\Support\ExtensionCard::for($manifest, $this->manager))
            ->values()
            ->concat($coreFeatures)
            ->sortBy(fn (array $m) => $m['title'])
            ->values();
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

    public function featureEnable(string $feature): RedirectResponse
    {
        return $this->setFeature($feature, true);
    }

    public function featureDisable(string $feature): RedirectResponse
    {
        return $this->setFeature($feature, false);
    }

    /**
     * The master switch for a built-in feature. {$feature} validates
     * against CoreFeatures::FEATURES (a fixed code-side list, same
     * posture as runLifecycle's discovered() check) so nothing outside
     * it ever reaches a write.
     */
    private function setFeature(string $feature, bool $on): RedirectResponse
    {
        abort_unless(array_key_exists($feature, CoreFeatures::FEATURES), 404);

        CoreFeatures::set($feature, $on);

        $name = ['seo' => 'SEO'][$feature];

        return redirect()->route('admin.extensions.index')
            ->with('success', $name . ' ' . ($on ? 'enabled.' : 'disabled. Its nav entry is hidden and its scheduled jobs are paused.'));
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
            return back()->with('error', "Can't {$action} \"{$manifest['title']}\". It's currently {$state}.");
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

        return redirect()->route('admin.extensions.index')->with('success', $message);
    }

}
