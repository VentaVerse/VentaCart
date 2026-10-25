<?php

namespace Tests\Feature\Admin\Permissions;

use App\Models\Role;
use App\Models\User;
use App\Support\AdminNav;
use App\Support\SettingsNav;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar and the Settings hub read the same permission map the routes
 * enforce, so nobody is shown a link that answers 403.
 */
class NavVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /** @return list<string> */
    private function navKeys(User $user): array
    {
        return collect(AdminNav::groups($user))->flatMap(fn ($group) => array_column($group['items'], 'key'))->all();
    }

    public function test_staff_sees_orders_and_lookups_but_no_configuration(): void
    {
        $keys = $this->navKeys(User::factory()->role('staff')->create());

        foreach (['dashboard', 'orders', 'couriers', 'products', 'customers'] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach (['categories', 'brands', 'menus', 'layout', 'all', 'modules', 'payments', 'settings'] as $key) {
            $this->assertNotContains($key, $keys);
        }
        $this->assertSame([], SettingsNav::groups(User::factory()->role('staff')->create()));
    }

    public function test_store_manager_sees_the_shop_but_not_extensions_layout_or_settings(): void
    {
        $manager = User::factory()->role('store-manager')->create();
        $keys = $this->navKeys($manager);

        foreach (['orders', 'products', 'categories', 'brands', 'attributes', 'customers', 'groups', 'pages', 'menus'] as $key) {
            $this->assertContains($key, $keys);
        }
        foreach (['layout', 'all', 'modules', 'payments', 'shipping', 'order-totals', 'settings'] as $key) {
            $this->assertNotContains($key, $keys);
        }
        $this->assertSame([], SettingsNav::groups($manager));
    }

    public function test_super_admin_sees_settings_and_its_access_pages(): void
    {
        $owner = User::factory()->role(Role::SUPER_ADMIN)->create();

        $this->assertContains('settings', $this->navKeys($owner));
        $settingsKeys = collect(SettingsNav::groups($owner))->flatMap(fn ($group) => array_column($group['items'], 'key'))->all();
        $this->assertContains('users', $settingsKeys);
        $this->assertContains('user-groups', $settingsKeys);
    }

    public function test_no_link_a_role_is_shown_answers_403(): void
    {
        foreach (['staff', 'store-manager'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user);

            foreach (AdminNav::groups($user) as $group) {
                foreach ($group['items'] as $item) {
                    $status = $this->get(route($item['route']))->status();
                    $this->assertNotSame(403, $status, "{$role} is shown {$item['key']} but is refused it.");
                }
            }
        }
    }

    public function test_the_rendered_sidebar_leaves_out_what_staff_cannot_open(): void
    {
        $this->actingAs(User::factory()->role('staff')->create());

        $this->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('href="' . route('admin.products.index') . '"', false)
            ->assertDontSee('href="' . route('admin.settings.index') . '"', false)
            ->assertDontSee('href="' . route('admin.categories.index') . '"', false);
    }

    public function test_the_search_box_returns_only_what_the_user_may_open(): void
    {
        \App\Models\Customer::create([
            'store_id' => \App\Models\Store::create(['name' => 'Demo Store', 'code' => 'demo'])->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com', 'password' => 'password123',
        ]);

        $role = Role::create(['name' => 'Orders only', 'slug' => 'orders-only']);
        $role->permissions()->sync(\App\Models\Permission::where('slug', 'orders.view')->pluck('id'));
        $this->actingAs(User::factory()->role('orders-only')->create());

        $this->getJson(route('admin.palette.search', ['q' => 'Maria']))
            ->assertOk()
            ->assertJsonMissing(['group' => 'Customers']);

        $this->actingAs(User::factory()->role(Role::SUPER_ADMIN)->create());
        $this->getJson(route('admin.palette.search', ['q' => 'Maria']))
            ->assertOk()
            ->assertJsonFragment(['group' => 'Customers']);
    }
}
