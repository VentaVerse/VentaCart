<?php

namespace Tests\Feature\AdminV2;

use App\Models\Automation;
use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The courier status poll is controlled where the feature lives (owner
 * ruling, 2026-08-30): the Fulfillment hub carries the switch and schedule
 * of the SAME automations row the global Settings, Automations page lists
 * and the schedule:run heartbeat reads. x-admin.automation is the reusable
 * door; AutomationInput::apply() is the reusable saver; both are what a
 * third-party extension drops into its own settings page.
 */
class FulfillmentAutomationPanelTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'courier_poll_status';

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    private function hub(): string
    {
        return '/' . config('admin.prefix', 'admin') . '/extensions/courier-fulfillment/settings';
    }

    private function row(): Automation
    {
        return Automation::where('key', self::KEY)->firstOrFail();
    }

    public function test_the_hub_heals_a_missing_row_then_carries_its_switch_and_schedule(): void
    {
        // A store that installed the module and never ran db:seed has no row,
        // and then nothing polls whatever the crontab says.
        Automation::where('key', self::KEY)->delete();

        $this->get($this->hub())
            ->assertOk()
            ->assertSee('Status updates')
            ->assertSee('Courier Status Polling')
            ->assertSee('name="automation[courier_poll_status][schedule_preset]"', false)
            ->assertSee('name="automation[courier_poll_status][enabled]"', false);

        $this->assertDatabaseHas('automations', ['key' => self::KEY, 'command' => 'courier:poll-status']);
    }

    public function test_saving_the_hub_saves_the_schedule_and_the_switch_on_the_shared_row(): void
    {
        $this->get($this->hub());
        $this->assertSame('*/10 * * * *', $this->row()->cron_expression);

        $this->post($this->hub(), [
            'settings' => ['fulfillment_enabled' => '1'],
            'automation' => [self::KEY => [
                'enabled' => '0',
                'schedule_preset' => 'every_n_minutes',
                'schedule_params' => ['minute' => '5'],
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = $this->row();
        $this->assertFalse($row->enabled);
        $this->assertSame('*/5 * * * *', $row->cron_expression);
        $this->assertSame(['minute' => 5], $row->schedule_params);
    }

    public function test_a_daily_schedule_stores_only_the_parameters_it_uses(): void
    {
        $this->get($this->hub());

        $this->post($this->hub(), [
            'settings' => ['fulfillment_enabled' => '1'],
            'automation' => [self::KEY => [
                'enabled' => '1',
                'schedule_preset' => 'daily',
                'schedule_params' => ['hour' => '8', 'minute' => '30'],
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $row = $this->row();
        $this->assertTrue($row->enabled);
        $this->assertSame('30 8 * * *', $row->cron_expression);
        $this->assertSame(['hour' => 8, 'minute' => 30], $row->schedule_params);
    }

    public function test_a_bad_interval_saves_nothing_at_all(): void
    {
        $this->get($this->hub());

        $this->post($this->hub(), [
            'settings' => ['fulfillment_enabled' => '0'],
            'automation' => [self::KEY => [
                'enabled' => '1',
                'schedule_preset' => 'every_n_minutes',
                'schedule_params' => ['minute' => '7'],
            ]],
        ])->assertSessionHasErrors('automation.courier_poll_status.schedule_params.minute');

        $this->assertSame('*/10 * * * *', $this->row()->cron_expression);
        // The module keys after it were not written either.
        $this->assertDatabaseMissing('settings', ['group' => 'courier', 'key' => 'fulfillment_enabled', 'value' => '0']);
    }

    public function test_a_form_without_the_panel_leaves_the_row_alone(): void
    {
        $this->get($this->hub());

        $this->post($this->hub(), ['settings' => ['fulfillment_enabled' => '1']])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('*/10 * * * *', $this->row()->cron_expression);
        $this->assertTrue($this->row()->enabled);
    }

    public function test_the_hub_says_when_the_heartbeat_has_not_fired(): void
    {
        $this->get($this->hub());
        Automation::query()->update(['last_run_at' => null]);

        $this->get($this->hub())
            ->assertOk()
            ->assertSee('schedule:run', false);

        Automation::query()->update(['last_run_at' => now()->subMinutes(5), 'last_run_status' => 'success']);

        $this->get($this->hub())
            ->assertOk()
            ->assertDontSee('is not running on this server');
    }

    public function test_the_global_automations_page_still_lists_the_same_row(): void
    {
        $this->get($this->hub());

        $this->get('/' . config('admin.prefix', 'admin') . '/settings/automations')
            ->assertOk()
            ->assertSee('Courier Status Polling');
    }
}
