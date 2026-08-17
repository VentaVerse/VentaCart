<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Support\AdminNav;
use App\Support\CoreFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The master switches for built-in features (Modules page, Core features
 * rows). The contract: opt-out defaults (missing setting means enabled),
 * a toggle round-trips through the settings table, a disabled feature's
 * nav entry hides, its scheduled command no-ops, and its Modules row
 * offers the opposite action.
 */
class CoreFeatureTogglesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        // CoreFeatures memoizes per request; tests span several.
        \Closure::bind(fn () => CoreFeatures::$memo = null, null, CoreFeatures::class)();
    }

    public function test_features_default_enabled_with_no_settings_rows(): void
    {
        foreach (array_keys(CoreFeatures::FEATURES) as $feature) {
            $this->assertTrue(CoreFeatures::enabled($feature), "{$feature} should default to enabled");
        }
    }

    public function test_disable_round_trip_writes_setting_and_flips_the_row(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.feature-disable', 'seo'))
            ->assertRedirect(route('admin.extensions.index'));

        $this->assertDatabaseHas('settings', ['group' => 'features', 'key' => 'seo_enabled', 'value' => '0']);

        $this->get(route('admin.extensions.index'))
            ->assertOk()
            ->assertSee(route('admin.extensions.feature-enable', 'seo'), false);

        $this->post(route('admin.extensions.feature-enable', 'seo'))
            ->assertRedirect(route('admin.extensions.index'));

        $this->assertDatabaseHas('settings', ['group' => 'features', 'key' => 'seo_enabled', 'value' => '1']);
    }

    public function test_unknown_feature_key_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.extensions.feature-disable', 'not-a-feature'))->assertNotFound();

        $this->assertDatabaseMissing('settings', ['group' => 'features', 'key' => 'not-a-feature_enabled']);
    }

    public function test_disabled_features_leave_the_nav(): void
    {
        $user = User::factory()->create();

        $labels = fn () => collect(AdminNav::groups($user))->flatMap(fn ($g) => collect($g['items'])->pluck('label'));

        $this->assertContains('SEO', $labels()->all());

        Setting::create(['group' => 'features', 'key' => 'seo_enabled', 'value' => '0', 'type' => 'text']);

        $this->assertNotContains('SEO', $labels()->all());
    }

    public function test_disabled_feature_commands_no_op(): void
    {
        // stock-alerts converted to a real extension (wave 1); seo remains
        // the core-feature example until its own conversion.
        Setting::create(['group' => 'features', 'key' => 'seo_enabled', 'value' => '0', 'type' => 'text']);
        \Closure::bind(fn () => CoreFeatures::$memo = null, null, CoreFeatures::class)();

        $this->artisan('seo:generate')
            ->expectsOutputToContain('Feature disabled in Modules')
            ->assertExitCode(0);
    }
}
