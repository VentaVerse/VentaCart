<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A fresh install has no admin and no known default login. Whoever opens the
 * admin first creates the account; after that the setup page is gone.
 */
class FirstAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'email' => 'owner@example.com',
            'username' => 'owner',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ], $overrides);
    }

    public function test_the_login_page_sends_a_store_with_no_admin_to_setup(): void
    {
        $this->get(route('login'))->assertRedirect(route('setup'));
    }

    public function test_an_admin_page_reaches_setup_through_the_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertRedirect(route('setup'));
    }

    public function test_the_setup_page_asks_for_the_four_fields(): void
    {
        $this->get(route('setup'))
            ->assertOk()
            ->assertSee('Create your admin account')
            ->assertSee('name="email"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Create account and sign in');
    }

    public function test_setup_creates_an_active_super_admin_and_signs_them_in(): void
    {
        $this->post(route('setup.store'), $this->form())
            ->assertRedirect(route('admin.dashboard'));

        $user = User::sole();
        $this->assertSame('owner@example.com', $user->email);
        $this->assertSame('owner', $user->username);
        $this->assertSame('owner', $user->name);
        $this->assertTrue($user->is_active);
        $this->assertSame(Role::where('slug', 'super-admin')->value('id'), $user->role_id);
        $this->assertTrue(password_verify('correct-horse', $user->password));
        $this->assertNotNull($user->web_login_at);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_once_an_admin_exists_the_setup_page_is_gone(): void
    {
        User::factory()->create();

        $this->get(route('setup'))->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
    }

    public function test_once_an_admin_exists_setup_cannot_create_another(): void
    {
        User::factory()->create();

        $this->post(route('setup.store'), $this->form())->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
        $this->assertGuest('web');
    }

    public function test_the_password_must_be_confirmed(): void
    {
        $this->from(route('setup'))
            ->post(route('setup.store'), $this->form(['password_confirmation' => 'something-else']))
            ->assertRedirect(route('setup'))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_the_rules_match_the_users_page(): void
    {
        $this->from(route('setup'))
            ->post(route('setup.store'), $this->form([
                'email' => 'not-an-email',
                'username' => 'has spaces',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]))
            ->assertSessionHasErrors(['email', 'username', 'password']);

        $this->assertSame(0, User::count());
    }

    public function test_a_rejected_save_keeps_email_and_username_but_not_the_password(): void
    {
        $this->from(route('setup'))
            ->post(route('setup.store'), $this->form(['password' => 'short', 'password_confirmation' => 'short']));

        $this->get(route('setup'))
            ->assertSee('value="owner@example.com"', false)
            ->assertSee('value="owner"', false)
            ->assertDontSee('value="short"', false);
    }
}
