# Admin permissions

Every admin route is checked against a permission before it runs. This page
explains the permission set, the shipped roles, how a route is mapped to a
permission, and how an extension or theme declares permissions for its own
screens.

The screen where roles are edited is **Settings > User Groups**. A "user
group" there is a role in the code (`roles` table, `App\Models\Role`).

## Permissions

A permission slug is `{group}.{action}`. Group names match the admin screens.

| Section | Group | Actions |
|---|---|---|
| Overview | `dashboard` | view |
| Overview | `reports` | view |
| Sales | `orders` | view, create, edit, delete |
| Sales | `couriers` | view, create, edit, delete |
| Sales | `reviews` | view, edit, delete |
| Catalog | `products` | view, create, edit, delete |
| Catalog | `categories` | view, create, edit, delete |
| Catalog | `brands` | view, create, edit, delete |
| Catalog | `tags` | view, create, edit, delete |
| Catalog | `attributes` | view, create, edit, delete |
| Catalog | `media` | view, create, delete |
| Customers | `customers` | view, create, edit, delete, impersonate |
| Customers | `customer-groups` | view, create, edit, delete |
| Marketing | `marketing` | view, create, edit, delete (coupons, email campaigns, abandoned carts, banners, stock alerts, SEO work) |
| Content | `pages` | view, create, edit, delete |
| Content | `blog` | view, create, edit, delete (posts and blog categories) |
| Content | `menus` | view, create, edit, delete |
| Layout and themes | `layout` | view, edit |
| Layout and themes | `themes` | view, edit (also every theme's own editor) |
| Extensions | `extensions` | view, edit (the directory, modules, payments, shipping, and each module's settings page), upload (Extensions > Upload: installs a package's PHP code on the server, so no shipped role but Super Admin holds it) |
| System | `settings` | view, edit |
| System | `users` | view, create, edit, delete |

The catalog lives in `App\Support\AdminPermissions::GROUPS`.

## Shipped roles

| Group | Super Admin | Store Manager | Staff |
|---|---|---|---|
| dashboard, reports | all | all | dashboard.view |
| orders | all | all | all |
| couriers | all | all | view |
| reviews | all | all | none |
| products | all | all | view |
| categories, brands, tags, attributes, media | all | all | none |
| customers | all | all, including impersonate | view |
| customer-groups | all | all | none |
| marketing | all | all | none |
| pages, blog, menus | all | all | none |
| layout, themes | all | none | none |
| extensions | all | none | none |
| settings | all | none | none |
| users | all | none | none |

**Super Admin** (role slug `super-admin`) passes every check, including
routes nobody mapped and permissions added later. It cannot be deleted, and
its permissions cannot be reduced.

**A user with no role** can open only the screens every admin can: their own
profile, the search box, and old URLs that redirect. The first-run setup
creates a Super Admin, and the users screen requires a role, so this only
happens when a role is taken away on purpose.

## Actions only a super admin can take

These stay with Super Admin whatever a role holds:

- creating, changing or deleting users and user groups (`users.view` still
  lets a role read the lists);
- installing and uninstalling extensions, and installing or removing theme
  packages;
- importing a theme preset or a layout template (both can carry HTML);
- revealing a stored secret, and issuing or revoking API keys;
- writing raw HTML, CSS or script that the storefront prints: the HTML
  layout block, a custom HTML menu item, and any theme's custom code field.
  This is the `storefront.code` gate.

Rich text (a product's or category's description, a page's body, a blog
post's content) is edited by whoever holds those groups, a Store Manager
included. A change saved without `storefront.code` passes through an
allow-list (`App\Support\RichText`): headings, paragraphs, lists, links,
images, tables, quotes, code, video and audio, and inline styles for text
and box formatting stay; script, event handlers, forms, `javascript:` links
and styles that load anything are removed, and the admin is told so. Frames
are kept only from the hosts in `RICH_TEXT_EMBED_HOSTS`. Content nobody
changes is left as written, and commands and queued jobs are trusted. A
model opts in with `App\Models\Concerns\HasRichText`.

The route list is `super_admin_only` in `config/admin-permissions.php`. It
applies to writes; reading a reserved screen still follows the role.

## How a route is mapped

`config/admin-permissions.php` maps route name patterns to a requirement:

```php
'admin.products.*' => 'products',        // a group: the action follows the request
'admin.products.images.*' => 'products.edit', // one exact permission
'admin.profile.*' => '@admin',           // any signed-in admin
'admin.something' => '@super-admin',     // super admin only, said on purpose
```

For a group value, GET and HEAD need `view`. A write needs `create` when the
route name ends in `store`, `quick-create`, `duplicate`, `upload` and
similar, `delete` for `destroy`, `delete` or a DELETE request, and `edit`
otherwise. A group without that action (settings has no create) uses its
`edit` permission. The most specific pattern wins: an exact name, then the
longest pattern.

**A route that nothing maps is super admin only.** The test
`tests/Feature/Admin/Permissions/EveryAdminRouteIsMappedTest.php` walks every
core and bundled-extension admin route and fails on one nobody mapped.

A refused request answers 403: the normal error page, or for a JSON request

```json
{"success": false, "message": "You do not have permission to do this. Ask a super admin for access.", "data": null}
```

The middleware (`App\Http\Middleware\EnforceAdminPermission`) runs in the
`web` group for every route named `admin.*` or carrying the
`admin.session.cap` middleware, so extension and theme routes are checked
without registering anything.

## Declaring permissions in an extension or theme

Name your admin routes `admin.{your-slug}.*` and declare what they need.

In an extension's `extension.json`, read while the extension is enabled:

```json
"admin_permissions": {
    "admin.my-module.*": "marketing",
    "admin.my-module.settings": "extensions"
}
```

From any service provider (a theme's or a package's):

```php
use App\Support\AdminPermissions;

public function boot(): void
{
    AdminPermissions::map([
        'admin.my-theme.*' => 'themes.edit',
    ]);
}
```

Use an existing group where one fits. A new group name works too (its
create, view, edit and delete slugs are checked), but only a super admin
passes until those permissions exist in the `permissions` table, so add them
in your migration with a `group` set and they appear under Extensions in
User Groups.

Core's own entries win any collision, and a declaration can never relax the
super-admin-only list. A declared pattern must start with `admin.` and a
literal segment of its own (`admin.my-theme`, `admin.my-theme.*`); a broader
one such as `admin.*` is ignored. Core maps no theme's or extension's routes,
so an editor whose package declares nothing stays super admin only.

## Checking permissions in code

Every permission slug is a gate ability, and Super Admin passes every gate:

```php
Gate::allows('orders.edit');
$user->hasPermission('orders.edit');
$user->isSuperAdmin();
@can('storefront.code') ... @endcan
```

A field that saves HTML, CSS or script the storefront prints must check
`Gate::allows('storefront.code')` before it writes a changed value. Leaving
an unchanged value as it is should stay allowed, so a manager can still save
the rest of the form.
