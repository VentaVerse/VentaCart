<?php

namespace Tests\Feature\Install;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A fresh install opens as VentaCart: its name, its logo and its favicon,
 * with no admin account waiting behind a default password.
 */
class FreshInstallBrandingTest extends TestCase
{
    use RefreshDatabase;

    private function identity(string $key): ?string
    {
        return Setting::where('group', 'identity')->where('key', $key)->value('value');
    }

    public function test_seeding_creates_no_admin(): void
    {
        Storage::fake('public');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }

    public function test_a_fresh_store_is_named_ventacart_and_wears_its_logo_and_favicon(): void
    {
        Storage::fake('public');

        $this->seed(ThemeSeeder::class);

        $this->assertSame('VentaCart', $this->identity('site_name'));
        $this->assertSame('identity/ventacart-logo.png', $this->identity('logo'));
        $this->assertSame('identity/ventacart-favicon.png', $this->identity('favicon'));
        Storage::disk('public')->assertExists(['identity/ventacart-logo.png', 'identity/ventacart-favicon.png']);
    }

    public function test_reseeding_never_replaces_a_logo_the_merchant_chose(): void
    {
        Storage::fake('public');
        Setting::create(['group' => 'identity', 'key' => 'logo', 'value' => 'identity/mine.png']);
        Setting::create(['group' => 'identity', 'key' => 'favicon', 'value' => null]);

        $this->seed(ThemeSeeder::class);

        $this->assertSame('identity/mine.png', $this->identity('logo'));
        $this->assertNull($this->identity('favicon'));
        Storage::disk('public')->assertMissing(['identity/ventacart-logo.png', 'identity/ventacart-favicon.png']);
    }

    public function test_the_shipped_favicon_is_a_real_icon(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));

        $this->assertGreaterThan(0, strlen($ico));
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));
    }
}
