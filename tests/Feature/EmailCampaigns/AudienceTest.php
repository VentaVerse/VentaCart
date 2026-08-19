<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Extensions\EmailCampaigns\Services\CampaignRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opt-out is absolute (owner ruling 2026-08-19). The filter lives in
 * CampaignRecipients, AFTER the resolver, so a future audience cannot forget
 * it. Every test here that names an audience also proves the filter applied.
 */
class AudienceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $resellers;
    private CustomerGroup $default;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();

        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        $this->default = CustomerGroup::create(['store_id' => 1, 'name' => 'Default']);
        $this->resellers = CustomerGroup::create(['store_id' => 1, 'name' => 'Resellers']);
    }

    private function customer(string $email, CustomerGroup $group, bool $newsletter, bool $active = true): Customer
    {
        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Test',
            'last_name' => 'Person',
            'email' => $email,
            'password' => bcrypt('secret'),
            'newsletter' => $newsletter,
            'is_active' => $active,
        ]);
    }

    private function campaign(string $type, ?array $params = null): EmailCampaign
    {
        return EmailCampaign::create([
            'store_id' => 1,
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'audience_type' => $type,
            'audience_params' => $params,
        ]);
    }

    public function test_the_group_audience_returns_only_that_groups_opted_in_members(): void
    {
        $in = $this->customer('in@example.com', $this->resellers, true);
        $this->customer('optedout@example.com', $this->resellers, false);
        $this->customer('othergroup@example.com', $this->default, true);

        $emails = CampaignRecipients::for($this->campaign('group', ['group_id' => $this->resellers->id]))
            ->pluck('email')->all();

        $this->assertSame([$in->email], $emails);
    }

    public function test_the_subscribers_audience_returns_everyone_opted_in(): void
    {
        $a = $this->customer('a@example.com', $this->default, true);
        $b = $this->customer('b@example.com', $this->resellers, true);
        $this->customer('c@example.com', $this->default, false);

        $emails = CampaignRecipients::for($this->campaign('subscribers'))->pluck('email')->sort()->values()->all();

        $this->assertSame([$a->email, $b->email], $emails);
    }

    /** "All Customers" quietly means "all customers who have not opted out". */
    public function test_all_customers_still_skips_the_opted_out(): void
    {
        $in = $this->customer('in@example.com', $this->default, true);
        $this->customer('out@example.com', $this->default, false);

        $emails = CampaignRecipients::for($this->campaign('all'))->pluck('email')->all();

        $this->assertSame([$in->email], $emails);
    }

    /** The one people assume is exempt. It is not. */
    public function test_hand_picked_customers_are_still_filtered_by_consent(): void
    {
        $in = $this->customer('in@example.com', $this->default, true);
        $out = $this->customer('out@example.com', $this->default, false);

        $emails = CampaignRecipients::for(
            $this->campaign('selected', ['customer_ids' => [$in->id, $out->id]])
        )->pluck('email')->all();

        $this->assertSame([$in->email], $emails);
    }

    public function test_disabled_accounts_and_missing_addresses_are_excluded(): void
    {
        $ok = $this->customer('ok@example.com', $this->default, true);
        $this->customer('disabled@example.com', $this->default, true, false);

        $emails = CampaignRecipients::for($this->campaign('all'))->pluck('email')->all();

        $this->assertSame([$ok->email], $emails);
    }

    public function test_the_compose_screen_can_report_both_figures(): void
    {
        $this->customer('in@example.com', $this->default, true);
        $this->customer('out1@example.com', $this->default, false);
        $this->customer('out2@example.com', $this->default, false);

        $campaign = $this->campaign('all');

        $this->assertSame(3, CampaignRecipients::audienceTotal($campaign));
        $this->assertSame(1, CampaignRecipients::willReceive($campaign));
    }
}
