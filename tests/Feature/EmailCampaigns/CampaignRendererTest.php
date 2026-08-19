<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Setting;
use App\Models\Store;
use App\Support\StoreIdentity;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        // {store_name} is resolved through StoreIdentity, which memoizes for
        // the life of the PHP process (not just this test): without a
        // flush, a name resolved by an earlier test class would leak in.
        StoreIdentity::flush();
    }

    private function customer(string $first, string $email): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => $first,
            'last_name' => 'Dela Cruz',
            'email' => $email,
            'password' => bcrypt('secret'),
            'newsletter' => true,
            'is_active' => true,
        ]);
    }

    public function test_each_recipient_gets_their_own_name(): void
    {
        $juan = $this->customer('Juan', 'juan@example.com');
        $maria = $this->customer('Maria', 'maria@example.com');

        $body = '<p>Hi {first_name}, we mailed {email}.</p>';

        $this->assertStringContainsString('Hi Juan, we mailed juan@example.com.', CampaignRenderer::render($body, $juan));
        $this->assertStringContainsString('Hi Maria, we mailed maria@example.com.', CampaignRenderer::render($body, $maria));
    }

    public function test_the_store_name_comes_from_settings(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'site_name'], ['value' => 'New Gear Day']);
        app(\App\Services\ThemeService::class)->clearCache();
        StoreIdentity::flush();

        $this->assertStringContainsString(
            'New Gear Day',
            CampaignRenderer::render('<p>{store_name}</p>', $this->customer('Juan', 'juan@example.com'))
        );
    }

    public function test_the_unsubscribe_token_renders_a_signed_link_for_that_recipient(): void
    {
        $customer = $this->customer('Juan', 'juan@example.com');

        $rendered = CampaignRenderer::render('<a href="{unsubscribe_url}">Stop these</a>', $customer);

        $this->assertStringContainsString('newsletter/unsubscribe/' . $customer->id, $rendered);
        $this->assertStringContainsString('signature=', $rendered);
    }

    /** OpenCart parity: no placeholder in the body means no link in the mail. */
    public function test_a_body_without_the_token_gets_no_unsubscribe_link(): void
    {
        $rendered = CampaignRenderer::render('<p>Just a note.</p>', $this->customer('Juan', 'juan@example.com'));

        $this->assertStringNotContainsString('unsubscribe', $rendered);
    }

    public function test_an_unknown_token_is_left_alone_rather_than_blanked(): void
    {
        $rendered = CampaignRenderer::render('<p>{not_a_token}</p>', $this->customer('Juan', 'juan@example.com'));

        $this->assertStringContainsString('{not_a_token}', $rendered);
    }

    /**
     * Task 6's admin test-send has no Customer to render against (an admin is
     * not a customer), so it supplies its own token map and reuses this loop
     * rather than copying it.
     */
    public function test_render_with_substitutes_an_explicit_map_without_a_customer(): void
    {
        $rendered = CampaignRenderer::renderWith('<p>Hi {first_name}, unsubscribe: {unsubscribe_url}</p>', [
            'first_name' => 'Preview',
            'unsubscribe_url' => '#',
        ]);

        $this->assertSame('<p>Hi Preview, unsubscribe: #</p>', $rendered);
    }
}
