<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function customer(bool $newsletter = true): Customer
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'password' => bcrypt('secret'),
            'newsletter' => $newsletter,
            'is_active' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_the_link_switches_the_customers_newsletter_flag_off(): void
    {
        $customer = $this->customer(true);

        $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))
            ->assertOk();

        $this->assertFalse($customer->fresh()->newsletter);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $customer = $this->customer(true);
        $url = URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]);

        // Swap the customer in the PATH, keeping the signature: exactly the
        // attack signing prevents. The id is a path segment, not a query
        // parameter, so this edits ".../unsubscribe/1?signature=..." into
        // ".../unsubscribe/999?signature=...".
        $this->get(str_replace("/unsubscribe/{$customer->id}?", '/unsubscribe/999?', $url))
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $customer = $this->customer(true);

        $this->get(route('newsletter.unsubscribe', ['customer' => $customer->id]))
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    /**
     * The resubscribe link is its OWN signed route, not the unsubscribe URL
     * with a parameter appended: Laravel signs the full query string, so
     * appending anything to a signed link invalidates it.
     */
    public function test_the_confirmation_page_offers_a_working_resubscribe_link(): void
    {
        $customer = $this->customer(true);

        $response = $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))->assertOk();

        $response->assertSee('newsletter/resubscribe', false);

        $this->get(URL::signedRoute('newsletter.resubscribe', ['customer' => $customer->id]))->assertOk();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    public function test_unsubscribing_twice_is_harmless(): void
    {
        $customer = $this->customer(false);

        $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))->assertOk();

        $this->assertFalse($customer->fresh()->newsletter);
    }
}
