<?php

namespace Extensions\Theme\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ThemeService;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * The theme directory: what is installed, and which one the storefront runs.
     *
     * The extension's home, like every other extension page. Going straight
     * into the editor made the platform look like it had one possible
     * appearance, which is the opposite of what a theme system is for. With a
     * single theme installed this is one card, and that is worth showing: it
     * tells a merchant a second theme is a thing that can exist.
     */
    public function directory(ThemeService $theme)
    {
        return view('theme::directory', [
            'themes' => $theme->getAvailableThemes(),
            'active' => $theme->getActiveTheme(),
            'live' => $theme->hasActiveTheme(),
        ]);
    }

    /**
     * Install a theme package found on disk, or update one whose files are
     * newer than its installed version. The kernel checks the contract,
     * refuses a package that would not draw every page, and runs the
     * theme's migrations.
     */
    public function install(Request $request, ThemeService $theme, \App\Services\ExtensionManager $extensions)
    {
        $slug = (string) $request->input('theme');
        $meta = $theme->getAvailableThemes()[$slug] ?? null;

        if (! $meta) {
            return back()->with('error', 'That theme is not on disk.');
        }

        $updating = ! empty($meta['update_available']);

        // The lock package uploads take, so an upload never swaps the
        // folder while it is being installed here.
        $lock = \App\Support\PackageLock::acquire((float) config('extensions.upload.lock_wait_seconds', 10));
        if ($lock === null) {
            return back()->with('error', 'Another package is being installed, updated or removed. Try again when it has finished.');
        }

        try {
            $extensions->install(ThemeService::registryName($slug));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // A failed migration may carry driver and schema details that do
            // not belong on an admin screen.
            \Illuminate\Support\Facades\Log::error("Theme {$slug}: " . ($updating ? 'update' : 'install') . ' failed', ['exception' => $e]);

            return back()->with('error', ($updating ? 'Updating ' : 'Installing ') . $meta['name'] . ' failed. Check the application log for details.');
        } finally {
            $lock->release();
        }

        $theme->clearCache();

        return back()->with('success', $updating
            ? $meta['name'] . ' is updated to version ' . ($meta['version'] ?? '') . '.'
            : $meta['name'] . ' is installed.');
    }

    /**
     * Uninstall a theme package. Its folder stays; its layout rows and
     * settings stay too, so installing it again finds them. Uninstalling
     * the live theme leaves the storefront on core's plain page until
     * another theme is made live.
     */
    public function uninstall(Request $request, ThemeService $theme, \App\Services\ExtensionManager $extensions)
    {
        $slug = (string) $request->input('theme');
        $meta = $theme->getAvailableThemes()[$slug] ?? null;

        if (! $meta || ! $meta['installed']) {
            return back()->with('error', 'That theme is not installed.');
        }

        $lock = \App\Support\PackageLock::acquire((float) config('extensions.upload.lock_wait_seconds', 10));
        if ($lock === null) {
            return back()->with('error', 'Another package is being installed, updated or removed. Try again when it has finished.');
        }

        try {
            $extensions->uninstall(ThemeService::registryName($slug));
        } finally {
            $lock->release();
        }
        $theme->clearCache();

        return back()->with(
            $slug === $theme->getActiveTheme() ? 'warning' : 'success',
            $meta['name'] . ' is uninstalled.' . ($slug === $theme->getActiveTheme() ? ' The storefront shows "No theme installed" until a theme is made live.' : '')
        );
    }

    /**
     * Point the storefront at another theme.
     *
     * Layout placements are NOT touched. They are store data keyed by page
     * type, and they survive a theme switch by design; a region the new theme
     * does not declare simply stops rendering and is reported as orphaned.
     * Deleting a merchant's layout because they tried a theme is how you teach
     * them never to try another one.
     */
    public function activate(Request $request, ThemeService $theme)
    {
        $slug = (string) $request->input('theme');

        try {
            $theme->activateTheme($slug);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $orphans = \App\Support\Regions::orphans()->count();

        return redirect()->route('admin.theme.index')->with(
            'success',
            'The storefront now runs ' . ($theme->getInstalledThemes()[$slug]['name'] ?? $slug) . '.'
                . ($orphans > 0
                    ? ' ' . $orphans . ' placement' . ($orphans === 1 ? '' : 's')
                        . ' sit in regions this theme does not have, and are waiting in Layout.'
                    : '')
        );
    }
}
