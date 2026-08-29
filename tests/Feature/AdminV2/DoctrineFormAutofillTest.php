<?php

namespace Tests\Feature\AdminV2;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer edit page raised Unsaved changes on load in the owner's
 * browser and never in a clean one. Chrome had classified it as a login
 * form (email beside password) and autofilled the admin's saved
 * credentials into the customer's record after the baseline was taken.
 * Two guards: no doctrine form invites autofill or reload-time restoration,
 * and the edit page carries no password field until asked.
 */
class DoctrineFormAutofillTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Default', 'is_default' => true]);

        return Customer::create(['store_id' => 1, 'customer_group_id' => $group->id, 'first_name' => 'Pierre', 'last_name' => 'Reyes', 'email' => 'pierre@example.com', 'password' => bcrypt('x')]);
    }

    public function test_the_edit_page_has_no_password_field_until_asked_and_the_form_refuses_autofill(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs(User::factory()->create())
            ->get(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Change password')
            ->getContent();

        // The field lives inside a template Alpine only stamps on request,
        // so a parser sees no live password input.
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="password"[^>]*>(?![^<]*<\/template>)/', preg_replace('/<template x-if="changing">.*?<\/template>/s', '', $html));
        $this->assertMatchesRegularExpression('/<form[^>]*autocomplete="off"/', $html);
    }

    public function test_the_customer_pages_run_without_the_dirty_tracker(): void
    {
        $customer = $this->customer();
        $admin = User::factory()->create();

        foreach ([route('admin.customers.edit', $customer), route('admin.customers.create')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('x-data="vFormPlain()"', $html);
            $this->assertStringNotContainsString('x-data="vForm()"', $html);
        }
    }

    public function test_the_create_page_still_requires_a_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.customers.create'))
            ->assertOk()
            ->assertSee('type="password"', false)
            ->assertSee('autocomplete="new-password"', false);
    }

    public function test_the_admin_user_edit_page_gets_the_same_guard(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $html = $this->actingAs($admin)->get(route('admin.users.edit', $other))->assertOk()->assertSee('Change password')->getContent();

        $this->assertDoesNotMatchRegularExpression('/<input[^>]*type="password"/', preg_replace('/<template x-if="changing">.*?<\/template>/s', '', $html));
    }
}
