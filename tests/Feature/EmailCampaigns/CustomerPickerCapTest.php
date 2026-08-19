<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F2: the "selected" audience picklist caps the pool it renders at 1000
 * customers, ordered by name. A saved campaign's customer_ids are passed
 * straight through as :selected, but the picklist only posts a checkbox
 * for the ids it was actually given a row for -- so a selected customer
 * outside the cap used to vanish, silently, the next time the campaign
 * was edited and saved.
 */
class CustomerPickerCapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        $this->actingAs(User::factory()->create());
    }

    /**
     * 1005 customers named so alphabetical order matches insertion order:
     * the picker's 1000-cap pool (ordered by first_name, last_name) leaves
     * out everyone from #1001 onward unless explicitly kept in.
     */
    private function seedCustomers(int $count): void
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);
        $now = now();

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'store_id' => 1,
                'customer_group_id' => $group->id,
                'first_name' => 'Cust' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'last_name' => 'Test',
                'email' => "cust{$i}@example.com",
                'password' => bcrypt('secret'),
                'newsletter' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('customers')->insert($chunk);
        }
    }

    public function test_a_selected_customer_outside_the_cap_survives_an_edit_and_save_round_trip(): void
    {
        $this->seedCustomers(1005);

        // Sorts last alphabetically (Cust01005), well outside the 1000-row cap.
        $outsideCap = Customer::where('email', 'cust1005@example.com')->firstOrFail();
        // Sorts well within the cap, as a control.
        $insideCap = Customer::where('email', 'cust1@example.com')->firstOrFail();

        $campaign = EmailCampaign::create([
            'store_id' => 1,
            'subject' => 'Targeted send',
            'body' => '<p>Hi</p>',
            'audience_type' => 'selected',
            'audience_params' => ['customer_ids' => [$insideCap->id, $outsideCap->id]],
            'status' => 'draft',
        ]);

        // The edit screen must render a checkbox for the out-of-cap customer.
        $this->get(route('admin.email-campaigns.edit', $campaign))
            ->assertOk()
            ->assertSee('value="' . $outsideCap->id . '"', false);

        // Re-saving with exactly what the form would have posted (both
        // checkboxes checked) must keep both ids, not silently drop one.
        $this->put(route('admin.email-campaigns.update', $campaign), [
            'subject' => 'Targeted send',
            'body' => '<p>Hi</p>',
            'audience_type' => 'selected',
            'customer_ids' => [$insideCap->id, $outsideCap->id],
            'action' => 'draft',
        ])->assertRedirect();

        $campaign->refresh();
        $this->assertEqualsCanonicalizing(
            [$insideCap->id, $outsideCap->id],
            $campaign->audience_params['customer_ids']
        );
    }

    public function test_the_unselected_pool_is_still_capped(): void
    {
        $this->seedCustomers(1005);

        $this->get(route('admin.email-campaigns.create'))
            ->assertOk()
            ->assertDontSee('cust1005@example.com');
    }
}
