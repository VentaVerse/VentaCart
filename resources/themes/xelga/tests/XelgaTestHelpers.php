<?php

namespace Themes\Xelga\Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Preview\PreviewMode;
use Themes\Xelga\Rendering\Storefront;

/**
 * Installing and driving the theme in a test: a fresh store with Xelga
 * installed (and live, unless asked otherwise), people with and without the
 * theme permission, and the editor's endpoints.
 */
trait XelgaTestHelpers
{
    protected function installXelga(bool $live = true): void
    {
        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'site_name'], ['value' => 'Demo Store']);

        $manager = app(ExtensionManager::class);
        $manager->install('theme');
        $manager->install(ThemeService::registryName('xelga'));
        $manager->bootEnabled();

        $themes = app(ThemeService::class);
        if ($live) {
            $themes->activateTheme('xelga');
        }
        $themes->clearCache();

        StoreIdentity::flush();
        Catalog::flush();
        Defaults::flush();
        PreviewMode::reset();
        Storefront::forgetRequestState();
        $this->withoutVite();
    }

    protected function admin(): User
    {
        return User::where('email', 'juan@example.com')->first()
            ?? User::factory()->create(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com']);
    }

    /** A member of a group that holds only the theme permission. */
    protected function themeEditor(string $name = 'Maria Santos'): User
    {
        $user = User::factory()->role('designer')->create(['name' => $name]);
        $permission = Permission::firstOrCreate(['slug' => 'themes.edit'], ['name' => 'Edit themes', 'group' => 'themes']);
        Role::where('slug', 'designer')->firstOrFail()->permissions()->syncWithoutDetaching([$permission->id]);

        return $user->fresh();
    }

    protected function staff(): User
    {
        return User::factory()->role('staff')->create();
    }

    protected function defaults(string $handle): array
    {
        return Defaults::content($handle);
    }

    /** The shipped home page with the banner heading changed. */
    protected function homeWithHeading(string $heading): array
    {
        $home = $this->defaults('home');
        $home['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading'] = $heading;

        return $home;
    }

    protected function saveDraft(User $user, string $handle, array $content, int $version): TestResponse
    {
        return $this->actingAs($user)->putJson(route('admin.xelga.documents.draft', ['handle' => $handle]), [
            'content' => $content,
            'draft_version' => $version,
        ]);
    }

    /** @param array<string, int> $versions */
    protected function publish(User $user, array $versions): TestResponse
    {
        return $this->actingAs($user)->postJson(route('admin.xelga.publish'), ['versions' => $versions]);
    }

    /**
     * Open the editor as the app does: load the shell, then open the
     * preview window with a POST. Returns the window's nonce.
     */
    protected function openEditor(User $user): string
    {
        $this->actingAs($user)->get(route('admin.xelga.editor'))->assertOk();
        $nonce = (string) $this->actingAs($user)->postJson(route('admin.xelga.preview.open'))->assertOk()->json('data.nonce');
        $this->assertSame($nonce, $this->previewWindow()['nonce'] ?? null);

        return $nonce;
    }

    /**
     * The preview window open for the signed-in admin, read where the app
     * keeps it.
     *
     * @return array{nonce: string, until: int, user: int}|null
     */
    protected function previewWindow(): ?array
    {
        $user = auth('web')->user();
        if ($user === null) {
            return null;
        }
        $window = \Illuminate\Support\Facades\Cache::get(
            \Themes\Xelga\Preview\PreviewSession::KEY . ':' . (int) $user->id
        );

        return is_array($window) ? $window : null;
    }

    /** Sign out of the admin, as a shopper in another browser would be. */
    protected function asGuest(): void
    {
        auth('web')->logout();
        $this->app['auth']->forgetGuards();
        session()->flush();
    }
}
