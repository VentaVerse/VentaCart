<?php

namespace Tests\Feature\AdminV2;

use App\Models\PaymentMethodRestriction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_renders_method_cards_in_new_shell(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.payment-settings.index'))
            ->assertOk()
            ->assertSee('v-admin', false)
            ->assertSee('Bank Transfer')->assertSee('Cash on Delivery')->assertSee('HitPay')
            ->assertSee('Manage');
    }

    public function test_restricted_method_shows_availability_chip(): void
    {
        $this->actingAs(User::factory()->create());
        PaymentMethodRestriction::create([
            'payment_method' => 'cod',
            'min_total' => 500,
            'is_active' => true,
        ]);

        $this->get(route('admin.payment-settings.index'))->assertSee('Restricted');
    }

    public function test_detail_page_renders_config_tab_per_method(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.payment-settings.show', 'cod'))
            ->assertOk()->assertSee('Configuration')->assertSee('Availability')
            ->assertSee('settings[cod_display_name]', false);

        $this->get(route('admin.payment-settings.show', 'hitpay'))
            ->assertOk()->assertSee('settings[hitpay_mode]', false);

        $this->get(route('admin.payment-settings.show', 'nope'))->assertNotFound();
    }
}
