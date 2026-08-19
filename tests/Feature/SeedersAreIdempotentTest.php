<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\EmailTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Store;
use Database\Seeders\AutomationSeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\ThemeSeeder;
use Database\Seeders\WorldGeographicSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner ruling, 2026-08-19: "a Laravel website, live or otherwise, has
 * never ending updates. Any seeded data from a fresh install and any data
 * edited by users must be untouched in every update."
 *
 * That is the standard, and a seeder that fails it is broken. Seeding fills
 * gaps; it never restores defaults over a decision somebody made. A fresh
 * install is unaffected either way, because there is nothing there to keep,
 * so idempotence only ever changes the run that would have destroyed
 * something.
 *
 * Each test below seeds, edits the way an admin would, seeds again, and
 * asserts the edit survived. The failure this catches is silent: the seeder
 * reports success while replacing the store's identity, which is why it went
 * unnoticed long enough to be mistaken for "never run seeders".
 */
class SeedersAreIdempotentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_keeps_the_stores_own_theme_settings(): void
    {
        $this->seed(ThemeSeeder::class);

        // The admin makes the shop theirs.
        $edits = [
            ['identity', 'site_name',      'New Gear Day'],
            ['identity', 'logo',           'catalog/ngd-logo.png'],
            ['identity', 'favicon',        'catalog/ngd-favicon.png'],
            ['identity', 'active_preset',  'stockroom'],
            ['colors',   'primary',        '#F5A623'],
            ['fonts',    'heading',        'Bebas Neue'],
            ['badges',   'new_count',      '25'],
            ['blog',     'posts_per_page', '30'],
        ];
        foreach ($edits as [$group, $key, $value]) {
            Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        }

        $this->seed(ThemeSeeder::class);

        foreach ($edits as [$group, $key, $value]) {
            $this->assertSame(
                $value,
                Setting::where('group', $group)->where('key', $key)->value('value'),
                "Re-seeding overwrote {$group}.{$key}."
            );
        }
    }

    /**
     * The other half of the contract: a setting the store never had must still
     * arrive on upgrade. Idempotence means "fill the gap, keep the answer",
     * not "write nothing after the first run".
     */
    public function test_reseeding_still_fills_in_a_setting_the_store_never_had(): void
    {
        $this->seed(ThemeSeeder::class);
        Setting::where('group', 'badges')->where('key', 'new_count')->delete();

        $this->seed(ThemeSeeder::class);

        $this->assertSame(
            '10',
            Setting::where('group', 'badges')->where('key', 'new_count')->value('value'),
            'Re-seeding failed to restore a setting that was genuinely missing.'
        );
    }

    public function test_reseeding_keeps_an_edited_email_template(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $template = EmailTemplate::query()->firstOrFail();
        $template->update(['subject' => 'Salamat sa iyong order!', 'body' => '<p>Custom NGD wording.</p>']);

        $this->seed(EmailTemplateSeeder::class);

        $template->refresh();
        $this->assertSame('Salamat sa iyong order!', $template->subject, 'Re-seeding overwrote an edited email subject.');
        $this->assertSame('<p>Custom NGD wording.</p>', $template->body, 'Re-seeding overwrote an edited email body.');
    }

    public function test_reseeding_keeps_the_configured_store_record(): void
    {
        $this->seed(StoreSeeder::class);

        Store::where('id', 1)->update(['name' => 'New Gear Day', 'currency' => 'PHP', 'timezone' => 'Asia/Manila']);

        $this->seed(StoreSeeder::class);

        $store = Store::find(1);
        $this->assertSame('New Gear Day', $store->name, 'Re-seeding overwrote the store name.');
        $this->assertSame('PHP', $store->currency, 'Re-seeding overwrote the store currency.');
        $this->assertSame('Asia/Manila', $store->timezone, 'Re-seeding overwrote the store timezone.');
    }

    public function test_reseeding_does_not_duplicate_roles_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $roles = Role::count();
        $permissions = Permission::count();

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($roles, Role::count(), 'Re-seeding duplicated roles.');
        $this->assertSame($permissions, Permission::count(), 'Re-seeding duplicated permissions.');
    }

    public function test_reseeding_keeps_a_country_the_admin_switched_off(): void
    {
        $this->seed(WorldGeographicSeeder::class);

        // A store that ships only to the Philippines turns the rest off.
        Country::where('iso2', 'US')->update(['is_active' => false, 'sort_order' => 99]);

        $this->seed(WorldGeographicSeeder::class);

        $country = Country::where('iso2', 'US')->first();
        $this->assertFalse((bool) $country->is_active, 'Re-seeding reactivated a country the admin switched off.');
        $this->assertSame(99, (int) $country->sort_order, 'Re-seeding reset a country sort order.');
    }

    /**
     * The control. AutomationSeeder already documents this contract in a
     * comment and honours it, so this passes before any fix and proves the
     * tests above are measuring the seeder rather than the harness.
     */
    public function test_reseeding_keeps_an_edited_automation_schedule(): void
    {
        $this->seed(AutomationSeeder::class);

        $automation = \App\Models\Automation::query()->firstOrFail();
        $automation->update(['enabled' => false, 'cron_expression' => '0 4 * * *']);

        $this->seed(AutomationSeeder::class);

        $automation->refresh();
        $this->assertFalse((bool) $automation->enabled, 'Re-seeding re-enabled a disabled automation.');
        $this->assertSame('0 4 * * *', $automation->cron_expression, 'Re-seeding overwrote an edited schedule.');
    }
}
