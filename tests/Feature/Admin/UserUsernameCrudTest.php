<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserUsernameCrudTest extends TestCase
{
    use RefreshDatabase;

    private function role(): Role
    {
        return Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    }

    public function test_users_index_lists_the_username_column(): void
    {
        $role = $this->role();
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'username' => 'admin-owner']));

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('admin-owner');
    }

    public function test_a_user_can_be_created_with_a_username(): void
    {
        $role = $this->role();
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jane Doe',
            'username' => 'jane_doe',
            'email' => 'jane.doe@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@example.com',
            'username' => 'jane_doe',
        ]);
    }

    public function test_creating_a_user_requires_a_unique_alpha_dash_username(): void
    {
        $role = $this->role();
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        User::factory()->create(['username' => 'taken']);

        $duplicate = $this->post(route('admin.users.store'), [
            'name' => 'Jane Doe',
            'username' => 'taken',
            'email' => 'jane.duplicate@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
        ]);
        $duplicate->assertSessionHasErrors('username');

        $invalidFormat = $this->post(route('admin.users.store'), [
            'name' => 'Jane Doe',
            'username' => 'not a valid username',
            'email' => 'jane.invalid@example.com',
            'password' => 'password123',
            'role_id' => $role->id,
        ]);
        $invalidFormat->assertSessionHasErrors('username');
    }

    public function test_a_user_username_can_be_updated(): void
    {
        $role = $this->role();
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        $target = User::factory()->create(['role_id' => $role->id, 'username' => 'old_name']);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'username' => 'new_name',
            'email' => $target->email,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame('new_name', $target->fresh()->username);
    }

    public function test_updating_a_user_cannot_reuse_another_users_username(): void
    {
        $role = $this->role();
        $this->actingAs(User::factory()->create(['role_id' => $role->id]));
        User::factory()->create(['username' => 'existing']);
        $target = User::factory()->create(['role_id' => $role->id, 'username' => 'target']);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'username' => 'existing',
            'email' => $target->email,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertSame('target', $target->fresh()->username);
    }
}
