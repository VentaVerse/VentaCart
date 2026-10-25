<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // alpha_dash-safe: Faker's userName() can contain dots (e.g. "jane.doe"),
            // so slug it to match the admin Users CRUD's alpha_dash validation rule.
            'username' => Str::slug(fake()->unique()->userName(), '_'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // A factory user is a super admin unless a test says otherwise,
            // stated here rather than left to a missing role: most tests are
            // about what a screen does, not who may open it. Tests about
            // access use role() or withoutRole().
            'role_id' => fn () => static::roleId(Role::SUPER_ADMIN, 'Super Admin'),
        ];
    }

    /**
     * A user in the role with this slug, created bare (no permissions) when
     * the test database has not seeded it. Seed RolePermissionSeeder first
     * to get the shipped role with its default permissions.
     */
    public function role(string $slug): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => static::roleId($slug, Str::headline($slug)),
        ]);
    }

    /** A user with no role, who may open only the screens every admin can. */
    public function withoutRole(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => null,
        ]);
    }

    private static function roleId(string $slug, string $name): int
    {
        return Role::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
