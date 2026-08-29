<?php

namespace Tests\Feature\AdminV2;

use App\Models\Automation;
use App\Models\Extension;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A scheduled job seeded by an extension belongs to it: not scheduled while
 * the extension is disabled, gone when it is uninstalled (owner question,
 * 2026-08-30: "if I uninstall fulfilment, will the polling be removed?").
 * Core's own jobs carry no owner and are untouched by any of this.
 */
class ExtensionAutomationOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function installAndSeed(): void
    {
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        (new \Extensions\CourierFulfillment\Database\Seeders\CourierFulfillmentAutomationSeeder())->run();
    }

    public function test_the_seeder_claims_its_row_even_one_seeded_before_ownership_existed(): void
    {
        Automation::create(['key' => 'courier_poll_status', 'name' => 'Courier Status Polling', 'command' => 'courier:poll-status',
            'schedule_preset' => 'every_n_minutes', 'schedule_params' => ['minute' => 30], 'cron_expression' => '*/30 * * * *', 'enabled' => false]);

        $this->installAndSeed();

        $row = Automation::where('key', 'courier_poll_status')->firstOrFail();
        $this->assertSame('courier-fulfillment', $row->extension);
        // Claiming ownership touched nothing the merchant had edited.
        $this->assertSame('*/30 * * * *', $row->cron_expression);
        $this->assertFalse($row->enabled);
    }

    public function test_a_disabled_extensions_job_is_not_runnable_and_the_page_says_so(): void
    {
        $this->installAndSeed();
        Automation::create(['key' => 'sitemap_generate', 'name' => 'Sitemap', 'command' => 'sitemap:generate',
            'schedule_preset' => 'daily', 'schedule_params' => ['hour' => 4, 'minute' => 0], 'cron_expression' => '0 4 * * *', 'enabled' => true]);

        $this->assertContains('courier_poll_status', Automation::runnable()->pluck('key')->all());

        Extension::where('name', 'courier-fulfillment')->update(['enabled' => false]);

        $runnable = Automation::runnable()->pluck('key')->all();
        $this->assertNotContains('courier_poll_status', $runnable, 'A disabled module\'s job must not be scheduled.');
        $this->assertContains('sitemap_generate', $runnable, 'Core jobs carry no owner and keep running.');

        $this->actingAs(User::factory()->create())
            ->get('/' . config('admin.prefix', 'admin') . '/settings/automations')
            ->assertOk()
            ->assertSee('Off with courier-fulfillment');
    }

    public function test_uninstalling_the_extension_removes_its_job_and_nothing_else(): void
    {
        $this->installAndSeed();
        Automation::create(['key' => 'sitemap_generate', 'name' => 'Sitemap', 'command' => 'sitemap:generate',
            'schedule_preset' => 'daily', 'schedule_params' => ['hour' => 4, 'minute' => 0], 'cron_expression' => '0 4 * * *', 'enabled' => true]);

        app(ExtensionManager::class)->uninstall('courier-fulfillment');

        $this->assertDatabaseMissing('automations', ['key' => 'courier_poll_status']);
        $this->assertDatabaseHas('automations', ['key' => 'sitemap_generate']);
    }
}
