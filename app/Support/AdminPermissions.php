<?php

namespace App\Support;

use App\Models\User;
use App\Services\ExtensionManager;
use Illuminate\Support\Str;

/**
 * The admin permission catalog and the route map that enforces it.
 *
 * Every admin route is checked by App\Http\Middleware\EnforceAdminPermission,
 * which asks requirementFor() what a route name needs. The answer comes from
 * config/admin-permissions.php ('routes'), topped up by whatever enabled
 * extensions and themes declare for their own route names. A route nothing
 * maps is super-admin only, so a new screen is closed until someone decides
 * who may use it.
 *
 * A map value is one of:
 *
 *   'products'       a permission group. The action comes from the request:
 *                    GET and HEAD need view; a write needs create, edit or
 *                    delete by the route name's last segment (store, destroy,
 *                    and so on, see ACTION_SUFFIXES) and the HTTP method.
 *                    A group without that action (settings has no create)
 *                    falls back to its edit permission.
 *   'products.edit'  one exact permission, whatever the method.
 *   '@admin'         any signed-in admin, whatever their role (profile,
 *                    sign-out of an impersonation, legacy redirects).
 *   '@super-admin'   super admin only, said on purpose.
 *
 * Patterns use Str::is() wildcards. The most specific pattern wins: an exact
 * name first, then the longest pattern.
 *
 * Extensions and themes declare their own entries in one of two ways:
 *
 *   - extension.json: "admin_permissions": {"admin.my-module.*": "marketing"}
 *     (read while the extension is enabled);
 *   - from a service provider: AdminPermissions::map(['admin.my-theme.*' => 'themes.edit']).
 *
 * Core's own entries always win a collision, and the 'super_admin_only' list
 * can never be relaxed by a declaration: a module can say who may use its
 * screens, not loosen core's. A declared pattern must also name its own
 * route prefix ("admin.{slug}" or "admin.{slug}.*"); one as broad as
 * "admin.*" is ignored, so a package cannot open screens that are not its
 * own, including any core screen that is unmapped and so super admin only.
 */
class AdminPermissions
{
    public const ANY_ADMIN = '@admin';

    public const SUPER_ADMIN = '@super-admin';

    /** The gate for writing raw HTML, CSS or script that the storefront prints. */
    public const STOREFRONT_CODE = 'storefront.code';

    private const CRUD = ['view', 'create', 'edit', 'delete'];

    /**
     * Every permission group and its actions, in the order the user group
     * editor shows them. A slug is "{group}.{action}". Group names match
     * the admin screens they open.
     *
     * @var array<string, list<string>>
     */
    public const GROUPS = [
        'dashboard' => ['view'],
        'reports' => ['view'],
        'orders' => self::CRUD,
        'couriers' => self::CRUD,
        'reviews' => ['view', 'edit', 'delete'],
        'products' => self::CRUD,
        'categories' => self::CRUD,
        'brands' => self::CRUD,
        'tags' => self::CRUD,
        'attributes' => self::CRUD,
        'media' => ['view', 'create', 'delete'],
        'customers' => ['view', 'create', 'edit', 'delete', 'impersonate'],
        'customer-groups' => self::CRUD,
        'marketing' => self::CRUD,
        'pages' => self::CRUD,
        'blog' => self::CRUD,
        'menus' => self::CRUD,
        'layout' => ['view', 'edit'],
        'themes' => ['view', 'edit'],
        'extensions' => ['view', 'edit'],
        'settings' => ['view', 'edit'],
        'users' => self::CRUD,
    ];

    /**
     * What the shipped roles start with. Super Admin is not listed: it is
     * granted every permission and bypasses every check anyway.
     *
     * Store Manager runs the shop (orders, catalog, customers, marketing,
     * content) and is kept out of the store's configuration: settings,
     * users, extensions, layout and themes. Staff works orders and can look
     * products and customers up.
     *
     * @var array<string, list<string>>  role slug => groups or exact slugs
     */
    public const ROLE_DEFAULTS = [
        'store-manager' => [
            'dashboard', 'reports',
            'orders', 'couriers', 'reviews',
            'products', 'categories', 'brands', 'tags', 'attributes', 'media',
            'customers', 'customer-groups',
            'marketing',
            'pages', 'blog', 'menus',
        ],
        'staff' => [
            'dashboard.view',
            'orders',
            'couriers.view',
            'products.view',
            'customers.view',
        ],
    ];

    /**
     * Route name endings that say which write a request is. Anything else
     * that writes is an edit; DELETE is always a delete.
     */
    private const ACTION_SUFFIXES = [
        'store' => 'create',
        'quick-create' => 'create',
        'duplicate' => 'create',
        'upload' => 'create',
        'upload-to-catalog' => 'create',
        'import-url' => 'create',
        'folder' => 'create',
        'destroy' => 'delete',
        'delete' => 'delete',
        'destroy-all' => 'delete',
        'mass-delete' => 'delete',
        'bulk-delete' => 'delete',
    ];

    /** @var array<string, string> entries registered from service providers */
    private static array $registered = [];

    /** @var array<string, string|null> requirement per "METHOD name" */
    private array $resolved = [];

    /** @var array<string, string>|null */
    private ?array $declared = null;

    /**
     * Declare permissions for an extension's or theme's own route names.
     * Call it from a service provider's register() or boot().
     *
     * @param  array<string, string>  $entries  route name pattern => map value
     */
    public static function map(array $entries): void
    {
        foreach ($entries as $pattern => $value) {
            if (static::acceptsDeclaration($pattern, $value)) {
                static::$registered[$pattern] = $value;
            }
        }

        if (app()->resolved(static::class)) {
            app(static::class)->forget();
        }
    }

    /** Drop what this instance has worked out, after the maps change. */
    public function forget(): void
    {
        $this->resolved = [];
        $this->declared = null;
    }

    /**
     * True when a package may declare this entry: a route name pattern that
     * starts with "admin." and a literal segment of its own before any
     * wildcard, and a non-empty value.
     */
    public static function acceptsDeclaration(mixed $pattern, mixed $value): bool
    {
        return is_string($pattern) && is_string($value) && $value !== ''
            && preg_match('/^admin\.[a-z0-9_-]+(\.[a-z0-9_.*-]*)?$/i', $pattern) === 1;
    }

    /** Forget registered entries. For tests. */
    public static function flushRegistered(): void
    {
        static::$registered = [];
    }

    /** Every permission slug the catalog defines. @return list<string> */
    public static function slugs(): array
    {
        $slugs = [];
        foreach (self::GROUPS as $group => $actions) {
            foreach ($actions as $action) {
                $slugs[] = "{$group}.{$action}";
            }
        }

        return $slugs;
    }

    /** The label the user group editor shows, e.g. "View Customer Groups". */
    public static function label(string $slug): string
    {
        [$group, $action] = explode('.', $slug, 2);

        return ucfirst($action) . ' ' . Str::headline($group);
    }

    /**
     * Permission slugs a shipped role starts with.
     *
     * @return list<string>
     */
    public static function defaultsFor(string $roleSlug): array
    {
        $out = [];
        foreach (self::ROLE_DEFAULTS[$roleSlug] ?? [] as $entry) {
            if (str_contains($entry, '.')) {
                $out[] = $entry;

                continue;
            }
            foreach (self::GROUPS[$entry] ?? [] as $action) {
                $out[] = "{$entry}.{$action}";
            }
        }

        return $out;
    }

    /**
     * True when the user may make this request. Super admins may always.
     */
    public function allows(User $user, ?string $routeName, string $method): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $required = $this->requirementFor($routeName, $method);

        return match (true) {
            $required === null, $required === self::SUPER_ADMIN => false,
            $required === self::ANY_ADMIN => true,
            default => $user->hasPermission($required),
        };
    }

    /**
     * What a route needs: a permission slug, ANY_ADMIN or SUPER_ADMIN.
     * Null means nothing maps it, which is treated as super admin only.
     */
    public function requirementFor(?string $routeName, string $method): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        $method = strtoupper($method);
        $key = $method . ' ' . $routeName;

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        return $this->resolved[$key] = $this->resolve($routeName, $method);
    }

    /** True when some map entry covers this route name. */
    public function isMapped(string $routeName): bool
    {
        return $this->match($this->coreMap(), $routeName) !== null
            || $this->match($this->declaredMap(), $routeName) !== null;
    }

    /** True when the route's writes are reserved for super admins. */
    public function isSuperAdminOnly(string $routeName, string $method): bool
    {
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        foreach ((array) config('admin-permissions.super_admin_only', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    private function resolve(string $routeName, string $method): ?string
    {
        if ($this->isSuperAdminOnly($routeName, $method)) {
            return self::SUPER_ADMIN;
        }

        $value = $this->match($this->coreMap(), $routeName)
            ?? $this->match($this->declaredMap(), $routeName);

        if ($value === null) {
            return null;
        }

        if ($value === self::ANY_ADMIN || $value === self::SUPER_ADMIN || str_contains($value, '.')) {
            return $value;
        }

        return $this->groupPermission($value, $routeName, $method);
    }

    /** A group value turned into the slug this request needs. */
    private function groupPermission(string $group, string $routeName, string $method): ?string
    {
        $actions = self::GROUPS[$group] ?? null;

        if ($actions === null) {
            // A group the catalog does not know (an extension's own): the
            // conventional actions, checked against whatever the table holds.
            $actions = self::CRUD;
        }

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $action = 'view';
        } elseif ($method === 'DELETE') {
            $action = 'delete';
        } else {
            $action = self::ACTION_SUFFIXES[Str::afterLast($routeName, '.')] ?? 'edit';
        }

        if (! in_array($action, $actions, true)) {
            if ($action === 'view' || ! in_array('edit', $actions, true)) {
                return null;
            }
            $action = 'edit';
        }

        return "{$group}.{$action}";
    }

    /** The best entry for a name: exact first, then the longest pattern. */
    private function match(array $map, string $routeName): ?string
    {
        if (isset($map[$routeName]) && ! str_contains($routeName, '*')) {
            return $map[$routeName];
        }

        $best = null;
        $bestLength = -1;

        foreach ($map as $pattern => $value) {
            if (! str_contains($pattern, '*') || ! Str::is($pattern, $routeName)) {
                continue;
            }
            if (strlen($pattern) > $bestLength) {
                $best = $value;
                $bestLength = strlen($pattern);
            }
        }

        return $best;
    }

    /** @return array<string, string> */
    private function coreMap(): array
    {
        return (array) config('admin-permissions.routes', []);
    }

    /**
     * Entries from enabled extensions' manifests and from providers.
     *
     * @return array<string, string>
     */
    private function declaredMap(): array
    {
        if ($this->declared !== null) {
            return $this->declared;
        }

        $map = [];

        try {
            $manager = app(ExtensionManager::class);
            foreach ($manager->discovered() as $name => $manifest) {
                if (! is_array($manifest['admin_permissions'] ?? null) || $manager->state((string) $name) !== 'enabled') {
                    continue;
                }
                foreach ($manifest['admin_permissions'] as $pattern => $value) {
                    if (static::acceptsDeclaration($pattern, $value)) {
                        $map[$pattern] = $value;
                    }
                }
            }
        } catch (\Throwable) {
            // No registry yet (a fresh install mid-migration): nothing is
            // declared, and an unmapped route stays super admin only.
        }

        return $this->declared = $map + static::$registered;
    }
}
