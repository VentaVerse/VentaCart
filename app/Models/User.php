<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'username', 'email', 'password', 'role_id', 'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'web_login_at' => 'datetime',
        ];
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function stores(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user');
    }

    /**
     * The role's permission slugs, read once per loaded user: the sidebar
     * alone asks a few dozen times per page.
     *
     * @var list<string>|null
     */
    protected ?array $permissionSlugs = null;

    /**
     * Super admins hold every permission, including ones added after their
     * role was saved, and pass every admin check. A user with no role holds
     * none: nobody is given access by accident of a missing value.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        $this->permissionSlugs ??= $this->role->permissions()->pluck('slug')->all();

        return in_array($slug, $this->permissionSlugs, true);
    }

    /** Forget the cached permissions, after the role or its permissions change. */
    public function refreshPermissions(): static
    {
        $this->permissionSlugs = null;
        $this->unsetRelation('role');

        return $this;
    }

    public function hasAccessToStore(int $storeId): bool
    {
        // Super admin (no store restrictions) or explicitly assigned
        if ($this->stores()->count() === 0) {
            return true;
        }

        return $this->stores()->where('stores.id', $storeId)->exists();
    }
}
