# Email Campaigns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild OpenCart's `Marketing > Mail` in Venta as an extension, with a TinyMCE editor, placeholder tokens, saved templates, four audiences, and an unsubscribe link wired to the existing `customers.newsletter` flag.

**Architecture:** A self-contained extension at `extensions/email-campaigns` mirroring the Coupons layout. Audiences are small resolver classes returning an Eloquent query; one service appends the consent filter after every resolver so no audience can forget it. Sending is a queued job that chunks the recipient query. Unsubscribe is a Laravel signed route with no token storage.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, TinyMCE (self-hosted at `public/vendor/tinymce/`), database queue driver, Blade with the `x-admin.*` component set.

**Spec:** `docs/superpowers/specs/2026-08-19-email-campaigns-design.md`

## Global Constraints

- Extension namespace is `Extensions\EmailCampaigns\`; PSR-4 for `Extensions\` is registered dynamically by `ExtensionManager`, not in `composer.json`.
- Admin routes live under `{admin.prefix}/extensions/email-campaigns` and are named `admin.email-campaigns.*`. Middleware `['web', 'auth', 'admin.session.cap']`, matching Coupons.
- `config/admin-nav.php` MUST NOT be modified. The nav entry is contributed through `extension.json`'s `admin_nav` into the existing Marketing group.
- The manifest MUST NOT carry `default_enabled`. This extension ships **not installed** (owner ruling 2026-08-19).
- Migrations are adopt-on-install: every `Schema::create` is wrapped in `if (! Schema::hasTable(...))`.
- Never write CSS inside a Blade file. Extension styles go in `extensions/email-campaigns/resources/css/` and are imported through Vite.
- No em dashes anywhere: code, comments, UI strings, commit messages.
- No instructional UI text (no help paragraphs or explanation cards). Name controls well and use tooltips.
- Opt-out is absolute: every audience is filtered by `newsletter = true`. The only exception is the admin test-send, which goes to the logged-in admin.
- `store_id` is hardcoded to 1, consistent with the rest of Venta.
- Run `php artisan view:clear` after Blade changes and `npm run build` after CSS or JS changes.
- Tests live under `tests/Feature/EmailCampaigns/`.

---

### Task 1: Extension skeleton, migrations, models

**Files:**
- Create: `extensions/email-campaigns/extension.json`
- Create: `extensions/email-campaigns/src/EmailCampaignsServiceProvider.php`
- Create: `extensions/email-campaigns/routes/web.php`
- Create: `extensions/email-campaigns/database/migrations/2026_08_19_100001_create_email_campaign_tables.php`
- Create: `extensions/email-campaigns/src/Models/EmailCampaign.php`
- Create: `extensions/email-campaigns/src/Models/EmailCampaignTemplate.php`
- Test: `tests/Feature/EmailCampaigns/ExtensionInstallTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Extensions\EmailCampaigns\Models\EmailCampaign` with fillable `store_id, subject, body, audience_type, audience_params, status, recipients_total, sent_at` and casts `audience_params => array`, `sent_at => datetime`, `recipients_total => integer`. `Extensions\EmailCampaigns\Models\EmailCampaignTemplate` with fillable `store_id, name, subject, body`. Route name `admin.email-campaigns.index`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Services\ExtensionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExtensionInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_extension_ships_not_installed(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\DB::table('extensions')->where('name', 'email-campaigns')->exists(),
            'email-campaigns must not be installed on a fresh install.'
        );
    }

    public function test_it_is_offered_in_the_directory(): void
    {
        $this->assertContains('email-campaigns', app(ExtensionManager::class)->discovered()->keys()->all());
    }

    public function test_installing_it_creates_its_tables(): void
    {
        app(ExtensionManager::class)->install('email-campaigns');

        $this->assertTrue(Schema::hasTable('email_campaigns'));
        $this->assertTrue(Schema::hasTable('email_campaign_templates'));
    }

    public function test_its_nav_pointer_appears_only_once_installed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.email-campaigns.index'));

        app(ExtensionManager::class)->install('email-campaigns');
        app(ExtensionManager::class)->bootEnabled();

        $this->assertTrue(\Illuminate\Support\Facades\Route::has('admin.email-campaigns.index'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/ExtensionInstallTest.php`
Expected: FAIL. `test_it_is_offered_in_the_directory` fails because no `extension.json` exists yet.

- [ ] **Step 3: Create the manifest**

`extensions/email-campaigns/extension.json`:

```json
{
    "name": "email-campaigns",
    "title": "Email",
    "version": "1.0.0",
    "description": "Write and send an email to a customer group, your newsletter subscribers, or a handful of people you pick.",
    "provider": "Extensions\\EmailCampaigns\\EmailCampaignsServiceProvider",
    "icon": "customers",
    "type": "feature",
    "configure_route": "admin.email-campaigns.index",
    "admin_nav": [
        {
            "group": "Marketing",
            "after": "coupons",
            "key": "email",
            "label": "Email",
            "route": "admin.email-campaigns.index",
            "icon": "customers",
            "keywords": ["blast", "newsletter", "campaign", "mail", "marketing"]
        }
    ]
}
```

Note the absence of `default_enabled`. That is deliberate and load-bearing.

- [ ] **Step 4: Create the service provider**

`extensions/email-campaigns/src/EmailCampaignsServiceProvider.php`:

```php
<?php

namespace Extensions\EmailCampaigns;

use Illuminate\Support\ServiceProvider;

class EmailCampaignsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'email-campaigns');
    }
}
```

- [ ] **Step 5: Create the routes file**

`extensions/email-campaigns/routes/web.php`:

```php
<?php

use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignController;
use Illuminate\Support\Facades\Route;

$adminPrefix = config('admin.prefix', 'admin');

// One home: everything this extension owns lives under
// extensions/email-campaigns; the Marketing nav entry is a pointer in.
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/email-campaigns')
    ->name('admin.email-campaigns.')
    ->group(function () {
        Route::get('/', [AdminEmailCampaignController::class, 'index'])->name('index');
    });
```

Later tasks add routes to this group. The controller class is created in Task 6; for now create a stub so the route file loads:

`extensions/email-campaigns/src/Http/Controllers/AdminEmailCampaignController.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;

class AdminEmailCampaignController extends Controller
{
    public function index()
    {
        return response('', 200);
    }
}
```

- [ ] **Step 6: Create the migration**

`extensions/email-campaigns/database/migrations/2026_08_19_100001_create_email_campaign_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adopt-on-install; see the shipping extensions' migration note. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->default(1);
                $table->string('subject', 255);
                $table->longText('body')->nullable();
                $table->enum('audience_type', ['group', 'subscribers', 'all', 'selected']);
                $table->json('audience_params')->nullable();
                $table->enum('status', ['draft', 'queued', 'sent', 'failed'])->default('draft');
                $table->unsignedInteger('recipients_total')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['store_id', 'status']);
            });
        }

        if (! Schema::hasTable('email_campaign_templates')) {
            Schema::create('email_campaign_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->default(1);
                $table->string('name', 128);
                $table->string('subject', 255)->nullable();
                $table->longText('body')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_templates');
        Schema::dropIfExists('email_campaigns');
    }
};
```

- [ ] **Step 7: Create the models**

`extensions/email-campaigns/src/Models/EmailCampaign.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Models;

use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    protected $fillable = [
        'store_id', 'subject', 'body', 'audience_type', 'audience_params',
        'status', 'recipients_total', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_params' => 'array',
            'recipients_total' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
```

`extensions/email-campaigns/src/Models/EmailCampaignTemplate.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A starting point an admin saved, NOT one of core's transactional
 * email_templates. Those fire from code by slug and belong to the order
 * pipeline; merging the two would put "Order Confirmation" in the campaign
 * picker and a half-written promo in the order flow.
 */
class EmailCampaignTemplate extends Model
{
    protected $fillable = ['store_id', 'name', 'subject', 'body'];
}
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EmailCampaigns/ExtensionInstallTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 9: Verify the whole suite is still green**

Run: `php artisan test`
Expected: PASS. The fresh-install defaults test in `tests/Feature/FreshInstallExtensionDefaultsTest.php` asserts on a `NOT_INSTALLED` list; add `'email-campaigns'` to that list so it stays exhaustive.

- [ ] **Step 10: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns tests/Feature/FreshInstallExtensionDefaultsTest.php
git commit -m "feat(email): the extension skeleton, its two tables and its one nav pointer"
```

---

### Task 2: Audience resolvers and the consent filter

**Files:**
- Create: `extensions/email-campaigns/src/Audiences/Audience.php`
- Create: `extensions/email-campaigns/src/Audiences/CustomerGroupAudience.php`
- Create: `extensions/email-campaigns/src/Audiences/NewsletterSubscribersAudience.php`
- Create: `extensions/email-campaigns/src/Audiences/AllCustomersAudience.php`
- Create: `extensions/email-campaigns/src/Audiences/SelectedCustomersAudience.php`
- Create: `extensions/email-campaigns/src/Audiences/AudienceRegistry.php`
- Create: `extensions/email-campaigns/src/Services/CampaignRecipients.php`
- Test: `tests/Feature/EmailCampaigns/AudienceTest.php`

**Interfaces:**
- Consumes: `EmailCampaign` from Task 1.
- Produces: `AudienceRegistry::for(string $type): Audience` and `AudienceRegistry::all(): array<string, string>` (type => label). `CampaignRecipients::for(EmailCampaign $c): Builder`, `CampaignRecipients::audienceTotal(EmailCampaign $c): int`, `CampaignRecipients::willReceive(EmailCampaign $c): int`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/AudienceTest.php`
Expected: FAIL with `Class "Extensions\EmailCampaigns\Services\CampaignRecipients" not found`.

- [ ] **Step 3: Create the interface and the four resolvers**

`extensions/email-campaigns/src/Audiences/Audience.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use Illuminate\Database\Eloquent\Builder;

/**
 * Who a campaign goes to, BEFORE consent is considered.
 *
 * A resolver returns a query and never a collection, so the caller can count
 * cheaply and append the consent filter. Consent is deliberately NOT applied
 * here: CampaignRecipients adds it to every audience, which is what stops a
 * future resolver from forgetting it.
 */
interface Audience
{
    /** @param array<string, mixed> $params */
    public function query(array $params): Builder;

    public function label(): string;
}
```

`CustomerGroupAudience.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class CustomerGroupAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query()->where('customer_group_id', $params['group_id'] ?? 0);
    }

    public function label(): string
    {
        return 'Customer group';
    }
}
```

`NewsletterSubscribersAudience.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class NewsletterSubscribersAudience implements Audience
{
    public function query(array $params): Builder
    {
        // Consent is applied again by CampaignRecipients. Harmless, and it
        // keeps this class honest about what it selects on its own.
        return Customer::query()->where('newsletter', true);
    }

    public function label(): string
    {
        return 'All newsletter subscribers';
    }
}
```

`AllCustomersAudience.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class AllCustomersAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query();
    }

    public function label(): string
    {
        return 'All customers';
    }
}
```

`SelectedCustomersAudience.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

class SelectedCustomersAudience implements Audience
{
    public function query(array $params): Builder
    {
        return Customer::query()->whereIn('id', $params['customer_ids'] ?? []);
    }

    public function label(): string
    {
        return 'Selected customers';
    }
}
```

- [ ] **Step 4: Create the registry**

`extensions/email-campaigns/src/Audiences/AudienceRegistry.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Audiences;

use InvalidArgumentException;

class AudienceRegistry
{
    /** @var array<string, class-string<Audience>> */
    private const TYPES = [
        'group' => CustomerGroupAudience::class,
        'subscribers' => NewsletterSubscribersAudience::class,
        'all' => AllCustomersAudience::class,
        'selected' => SelectedCustomersAudience::class,
    ];

    public static function for(string $type): Audience
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown audience type [{$type}].");
        }

        return app(self::TYPES[$type]);
    }

    /** @return array<string, string> type => label, for the compose screen's select */
    public static function all(): array
    {
        $out = [];
        foreach (self::TYPES as $type => $class) {
            $out[$type] = app($class)->label();
        }

        return $out;
    }
}
```

- [ ] **Step 5: Create the consent gate**

`extensions/email-campaigns/src/Services/CampaignRecipients.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Services;

use Extensions\EmailCampaigns\Audiences\AudienceRegistry;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single place consent is enforced.
 *
 * Opt-out is absolute (owner ruling 2026-08-19): a customer with
 * newsletter = false is skipped by EVERY audience, hand-picked included, so
 * "All customers" quietly means "all customers who have not opted out".
 * Applying the filter here rather than inside each resolver is what makes
 * that true of audiences nobody has written yet.
 *
 * is_active and a non-null email are design decisions rather than rulings: a
 * disabled account should not receive marketing, and a null address cannot be
 * mailed.
 */
class CampaignRecipients
{
    public static function for(EmailCampaign $campaign): Builder
    {
        return AudienceRegistry::for($campaign->audience_type)
            ->query($campaign->audience_params ?? [])
            ->where('newsletter', true)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->orderBy('id');
    }

    /** Everyone the audience names, before consent. The bigger of the two figures. */
    public static function audienceTotal(EmailCampaign $campaign): int
    {
        return AudienceRegistry::for($campaign->audience_type)
            ->query($campaign->audience_params ?? [])
            ->count();
    }

    /** Everyone who will actually be mailed. */
    public static function willReceive(EmailCampaign $campaign): int
    {
        return self::for($campaign)->count();
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EmailCampaigns/AudienceTest.php`
Expected: PASS, 6 tests.

- [ ] **Step 7: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/AudienceTest.php
git commit -m "feat(email): four audiences, and one place that decides who consented"
```

---

### Task 3: Unsubscribe and resubscribe

**Files:**
- Create: `extensions/email-campaigns/src/Http/Controllers/NewsletterSubscriptionController.php`
- Modify: `extensions/email-campaigns/routes/web.php` (append the storefront group)
- Create: `extensions/email-campaigns/resources/views/newsletter/result.blade.php`
- Test: `tests/Feature/EmailCampaigns/UnsubscribeTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: named routes `newsletter.unsubscribe` and `newsletter.resubscribe`, each taking `{customer}` and each `signed`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private function customer(bool $newsletter = true): Customer
    {
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
        $group = CustomerGroup::create(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'password' => bcrypt('secret'),
            'newsletter' => $newsletter,
            'is_active' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_the_link_switches_the_customers_newsletter_flag_off(): void
    {
        $customer = $this->customer(true);

        $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))
            ->assertOk();

        $this->assertFalse($customer->fresh()->newsletter);
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $customer = $this->customer(true);
        $url = URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]);

        // Swap the customer in the PATH, keeping the signature: exactly the
        // attack signing prevents. The id is a path segment, not a query
        // parameter, so this edits ".../unsubscribe/1?signature=..." into
        // ".../unsubscribe/999?signature=...".
        $this->get(str_replace("/unsubscribe/{$customer->id}?", '/unsubscribe/999?', $url))
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $customer = $this->customer(true);

        $this->get(route('newsletter.unsubscribe', ['customer' => $customer->id]))
            ->assertForbidden();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    /**
     * The resubscribe link is its OWN signed route, not the unsubscribe URL
     * with a parameter appended: Laravel signs the full query string, so
     * appending anything to a signed link invalidates it.
     */
    public function test_the_confirmation_page_offers_a_working_resubscribe_link(): void
    {
        $customer = $this->customer(true);

        $response = $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))->assertOk();

        $response->assertSee('newsletter/resubscribe', false);

        $this->get(URL::signedRoute('newsletter.resubscribe', ['customer' => $customer->id]))->assertOk();

        $this->assertTrue($customer->fresh()->newsletter);
    }

    public function test_unsubscribing_twice_is_harmless(): void
    {
        $customer = $this->customer(false);

        $this->get(URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]))->assertOk();

        $this->assertFalse($customer->fresh()->newsletter);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/UnsubscribeTest.php`
Expected: FAIL with `Route [newsletter.unsubscribe] not defined.`

- [ ] **Step 3: Create the controller**

`extensions/email-campaigns/src/Http/Controllers/NewsletterSubscriptionController.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;

/**
 * One click, one column. Both actions write customers.newsletter, the same
 * field the registration form, the account profile and the admin customer
 * form already write, so a customer who unsubscribes from a mail sees the box
 * unticked in their account and vice versa.
 *
 * Signed routes carry the customer id and a signature, so nothing has to be
 * stored and a link cannot be edited to act on somebody else.
 */
class NewsletterSubscriptionController extends Controller
{
    // The customer arrives as an int and is loaded by hand, NOT as an implicit
    // route-model binding. SubstituteBindings runs BEFORE the signed middleware,
    // so a forged link naming an id that does not exist would 404 out of the
    // binder before the signature was ever checked, leaking which ids exist and
    // returning the wrong status for a tampered link.
    public function unsubscribe(int $customer)
    {
        $customer = Customer::findOrFail($customer);
        $customer->update(['newsletter' => false]);

        return view('email-campaigns::newsletter.result', [
            'customer' => $customer,
            'subscribed' => false,
        ]);
    }

    public function resubscribe(int $customer)
    {
        $customer = Customer::findOrFail($customer);
        $customer->update(['newsletter' => true]);

        return view('email-campaigns::newsletter.result', [
            'customer' => $customer,
            'subscribed' => true,
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

Append to `extensions/email-campaigns/routes/web.php`:

```php
// Storefront, signed and public: the link inside a campaign. `signed`
// middleware 403s a tampered or unsigned request. Two routes rather than one
// with a flag, because Laravel signs the full query string and a resubscribe
// parameter appended to an unsubscribe link would break its own signature.
Route::middleware(['web', 'signed', 'noindex'])->group(function () {
    Route::get('/newsletter/unsubscribe/{customer}', [NewsletterSubscriptionController::class, 'unsubscribe'])
        ->name('newsletter.unsubscribe')->whereNumber('customer');

    Route::get('/newsletter/resubscribe/{customer}', [NewsletterSubscriptionController::class, 'resubscribe'])
        ->name('newsletter.resubscribe')->whereNumber('customer');
});
```

Add the import at the top of the file:

```php
use Extensions\EmailCampaigns\Http\Controllers\NewsletterSubscriptionController;
```

- [ ] **Step 5: Create the confirmation view**

`extensions/email-campaigns/resources/views/newsletter/result.blade.php`. It extends the active storefront layout so it carries the store's header and footer:

The theme uses slot-based layout components and has ZERO uses of `@extends`;
`layouts.store` is reachable only as `<x-store-layout>`. Compare
`resources/themes/venta/views/contact.blade.php`.

```blade
<x-store-layout title="Newsletter">
    <div class="mx-auto max-w-xl px-4 py-16 text-center">
        @if ($subscribed)
            <h1 class="font-heading text-2xl text-[var(--color-text-heading)]">You are back on the list</h1>
            <p class="mt-3 text-[var(--color-text-muted)]">{{ $customer->email }} will receive our emails again.</p>
        @else
            <h1 class="font-heading text-2xl text-[var(--color-text-heading)]">You are unsubscribed</h1>
            <p class="mt-3 text-[var(--color-text-muted)]">{{ $customer->email }} will no longer receive our emails.</p>
            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('newsletter.resubscribe', ['customer' => $customer->id]) }}"
               class="mt-6 inline-block rounded bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-[var(--color-btn-text)]">
                Undo, keep me subscribed
            </a>
        @endif
    </div>
</x-store-layout>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan view:clear && php artisan test tests/Feature/EmailCampaigns/UnsubscribeTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 7: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/UnsubscribeTest.php
git commit -m "feat(email): one click off the list, writing the same flag the profile box does"
```

---

### Task 4: Placeholder rendering and the email shell

**Files:**
- Create: `extensions/email-campaigns/src/Services/CampaignRenderer.php`
- Create: `extensions/email-campaigns/resources/views/emails/shell.blade.php`
- Test: `tests/Feature/EmailCampaigns/CampaignRendererTest.php`

**Interfaces:**
- Consumes: `newsletter.unsubscribe` route from Task 3.
- Produces: `CampaignRenderer::render(string $text, Customer $customer): string`, `CampaignRenderer::renderWith(string $text, array $values): string` (substitute an explicit token map, so the admin test-send in Task 6 reuses this loop instead of copying it), `CampaignRenderer::tokens(): array<string, string>` (token => human label, for the compose toolbar).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Setting;
use App\Models\Store;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignRendererTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
    }

    private function customer(string $first, string $email): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => $first,
            'last_name' => 'Dela Cruz',
            'email' => $email,
            'password' => bcrypt('secret'),
            'newsletter' => true,
            'is_active' => true,
        ]);
    }

    public function test_each_recipient_gets_their_own_name(): void
    {
        $juan = $this->customer('Juan', 'juan@example.com');
        $maria = $this->customer('Maria', 'maria@example.com');

        $body = '<p>Hi {first_name}, we mailed {email}.</p>';

        $this->assertStringContainsString('Hi Juan, we mailed juan@example.com.', CampaignRenderer::render($body, $juan));
        $this->assertStringContainsString('Hi Maria, we mailed maria@example.com.', CampaignRenderer::render($body, $maria));
    }

    public function test_the_store_name_comes_from_settings(): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'site_name'], ['value' => 'New Gear Day']);

        $this->assertStringContainsString(
            'New Gear Day',
            CampaignRenderer::render('<p>{store_name}</p>', $this->customer('Juan', 'juan@example.com'))
        );
    }

    public function test_the_unsubscribe_token_renders_a_signed_link_for_that_recipient(): void
    {
        $customer = $this->customer('Juan', 'juan@example.com');

        $rendered = CampaignRenderer::render('<a href="{unsubscribe_url}">Stop these</a>', $customer);

        $this->assertStringContainsString('newsletter/unsubscribe/' . $customer->id, $rendered);
        $this->assertStringContainsString('signature=', $rendered);
    }

    /** OpenCart parity: no placeholder in the body means no link in the mail. */
    public function test_a_body_without_the_token_gets_no_unsubscribe_link(): void
    {
        $rendered = CampaignRenderer::render('<p>Just a note.</p>', $this->customer('Juan', 'juan@example.com'));

        $this->assertStringNotContainsString('unsubscribe', $rendered);
    }

    public function test_an_unknown_token_is_left_alone_rather_than_blanked(): void
    {
        $rendered = CampaignRenderer::render('<p>{not_a_token}</p>', $this->customer('Juan', 'juan@example.com'));

        $this->assertStringContainsString('{not_a_token}', $rendered);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/CampaignRendererTest.php`
Expected: FAIL with `Class "Extensions\EmailCampaigns\Services\CampaignRenderer" not found`.

- [ ] **Step 3: Create the renderer**

`extensions/email-campaigns/src/Services/CampaignRenderer.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Services;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\URL;

/**
 * Placeholder substitution for a campaign body or subject.
 *
 * Uses core's existing {key} convention rather than inventing one: the shipped
 * order confirmation already reads "Hi {customer_name}". The helper is kept
 * local to this extension rather than widening EmailTemplate, because a
 * campaign body is not an EmailTemplate row and the two have different token
 * sets.
 *
 * A token the author invented is left in place rather than blanked, so a typo
 * is visible in the test send instead of silently deleting a sentence.
 */
class CampaignRenderer
{
    public static function render(string $text, Customer $customer): string
    {
        return self::renderWith($text, self::valuesFor($customer));
    }

    /**
     * Substitute an explicit token map. The admin test-send has no Customer to
     * render against (an admin is not a customer), so it supplies its own
     * values and reuses this loop rather than copying it.
     *
     * @param array<string, string> $values
     */
    public static function renderWith(string $text, array $values): string
    {
        foreach ($values as $token => $value) {
            $text = str_replace('{' . $token . '}', (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string, string> token => value */
    private static function valuesFor(Customer $customer): array
    {
        return [
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'email' => $customer->email,
            'store_name' => Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name'),
            'unsubscribe_url' => URL::signedRoute('newsletter.unsubscribe', ['customer' => $customer->id]),
        ];
    }

    /**
     * What the compose toolbar offers. Label is what the button says; the key
     * is what gets inserted at the cursor.
     *
     * @return array<string, string>
     */
    public static function tokens(): array
    {
        return [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'store_name' => 'Store name',
            'unsubscribe_url' => 'Unsubscribe link',
        ];
    }
}
```

- [ ] **Step 4: Create the email shell**

`extensions/email-campaigns/resources/views/emails/shell.blade.php`. Table-based with inline styles, because Outlook renders with Word and modern CSS does not survive it. The editor only ever fills `$body`, so it cannot emit layout that breaks:

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:4px;">
                @if ($logo)
                    <tr><td style="padding:24px 24px 0;" align="center"><img src="{{ $logo }}" alt="{{ $storeName }}" style="max-height:48px;"></td></tr>
                @endif
                <tr>
                    <td style="padding:24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1a1a1a;">
                        {!! $body !!}
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 24px 24px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:#777777;border-top:1px solid #eeeeee;">
                        {{ $storeName }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
```

Note the footer carries the store name only. There is no unsubscribe link here by design: it appears only where the author put `{unsubscribe_url}`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan view:clear && php artisan test tests/Feature/EmailCampaigns/CampaignRendererTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/CampaignRendererTest.php
git commit -m "feat(email): tokens the author chooses, in a shell Outlook cannot break"
```

---

### Task 5: The send pipeline

**Files:**
- Create: `extensions/email-campaigns/src/Mail/CampaignMail.php`
- Create: `extensions/email-campaigns/src/Jobs/SendCampaignJob.php`
- Test: `tests/Feature/EmailCampaigns/SendCampaignTest.php`

**Interfaces:**
- Consumes: `CampaignRecipients::for()` (Task 2), `CampaignRenderer::render()` (Task 4), `EmailCampaign` (Task 1).
- Produces: `SendCampaignJob::__construct(int $campaignId)` and `CampaignMail::__construct(string $subject, string $body, string $storeName, ?string $logo)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('email-campaigns');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Store::firstOrCreate(['id' => 1], ['name' => 'Test', 'code' => 'test']);
    }

    private function customer(string $email, bool $newsletter = true): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1,
            'customer_group_id' => $group->id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => $email,
            'password' => bcrypt('secret'),
            'newsletter' => $newsletter,
            'is_active' => true,
        ]);
    }

    private function campaign(): EmailCampaign
    {
        return EmailCampaign::create([
            'store_id' => 1,
            'subject' => 'Hello {first_name}',
            'body' => '<p>Hi {first_name}</p>',
            'audience_type' => 'all',
            'status' => 'queued',
        ]);
    }

    public function test_it_mails_exactly_the_consenting_recipients_once_each(): void
    {
        Mail::fake();
        $this->customer('in1@example.com');
        $this->customer('in2@example.com');
        $this->customer('out@example.com', false);

        (new SendCampaignJob($this->campaign()->id))->handle();

        Mail::assertSent(CampaignMail::class, 2);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('in1@example.com'));
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('in2@example.com'));
        Mail::assertNotSent(CampaignMail::class, fn ($m) => $m->hasTo('out@example.com'));
    }

    public function test_the_subject_is_rendered_per_recipient(): void
    {
        Mail::fake();
        $this->customer('juan@example.com');

        (new SendCampaignJob($this->campaign()->id))->handle();

        Mail::assertSent(CampaignMail::class, fn ($m) => $m->subject === 'Hello Juan');
    }

    public function test_it_records_what_was_sent(): void
    {
        Mail::fake();
        $this->customer('a@example.com');
        $this->customer('b@example.com');
        $campaign = $this->campaign();

        (new SendCampaignJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_total);
        $this->assertNotNull($campaign->sent_at);
    }

    /**
     * One bad address must never mark the whole send failed. `failed` is
     * reserved for the JOB failing, not a recipient bouncing.
     */
    public function test_one_failing_recipient_does_not_abort_the_run(): void
    {
        $this->customer('good1@example.com');
        $this->customer('bad@example.com');
        $this->customer('good2@example.com');
        $campaign = $this->campaign();

        Mail::shouldReceive('to')->andReturnUsing(function ($address) {
            if ($address === 'bad@example.com') {
                throw new \RuntimeException('mailbox unavailable');
            }

            return new class {
                public function send($mailable) {}
            };
        });

        (new SendCampaignJob($campaign->id))->handle();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->recipients_total, 'Only successful sends are counted.');
    }

    public function test_a_campaign_already_sent_is_not_sent_again(): void
    {
        Mail::fake();
        $this->customer('a@example.com');
        $campaign = $this->campaign();
        $campaign->update(['status' => 'sent']);

        (new SendCampaignJob($campaign->id))->handle();

        Mail::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/SendCampaignTest.php`
Expected: FAIL with `Class "Extensions\EmailCampaigns\Jobs\SendCampaignJob" not found`.

- [ ] **Step 3: Create the mailable**

`extensions/email-campaigns/src/Mail/CampaignMail.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;

class CampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The subject is passed to the parent's own subject() rather than promoted
     * into a property. Illuminate\Mail\Mailable already declares an UNTYPED
     * public $subject, and a promoted `public string $subject` here is a fatal
     * error at class load: "Type of CampaignMail::$subject must be omitted to
     * match the parent definition". Calling subject() also populates that
     * inherited property, which is what Mail::assertSent can read back.
     */
    public function __construct(
        string $subject,
        public string $body,
        public string $storeName,
        public ?string $logo = null,
    ) {
        $this->subject($subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'email-campaigns::emails.shell',
            with: [
                'body' => $this->body,
                'storeName' => $this->storeName,
                'logo' => $this->logo,
            ],
        );
    }
}
```

- [ ] **Step 4: Create the job**

`extensions/email-campaigns/src/Jobs/SendCampaignJob.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Jobs;

use App\Models\Setting;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Extensions\EmailCampaigns\Services\CampaignRecipients;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Queued so a send to two hundred people does not hold the browser open.
 *
 * Recipients are chunked rather than loaded at once, and each send is wrapped
 * on its own: one refused address is logged and skipped, never allowed to end
 * the run. `status = failed` is therefore reserved for the JOB failing, which
 * means the mailer itself, not a recipient bouncing.
 */
class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = EmailCampaign::find($this->campaignId);

        // A campaign that has already gone out must never go out twice, even
        // if the job is retried by the queue.
        if (! $campaign || $campaign->status === 'sent') {
            return;
        }

        $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name');
        $logo = Setting::where('group', 'identity')->where('key', 'logo')->value('value');
        $logoUrl = $logo ? storage_url($logo) : null;

        $sent = 0;

        CampaignRecipients::for($campaign)->chunkById(100, function ($customers) use ($campaign, $storeName, $logoUrl, &$sent) {
            foreach ($customers as $customer) {
                try {
                    Mail::to($customer->email)->send(new CampaignMail(
                        CampaignRenderer::render($campaign->subject, $customer),
                        CampaignRenderer::render($campaign->body ?? '', $customer),
                        $storeName,
                        $logoUrl,
                    ));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('Campaign send failed for a recipient', [
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $campaign->update([
            'status' => 'sent',
            'recipients_total' => $sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        EmailCampaign::where('id', $this->campaignId)->update(['status' => 'failed']);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/EmailCampaigns/SendCampaignTest.php`
Expected: PASS, 5 tests.

- [ ] **Step 6: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/SendCampaignTest.php
git commit -m "feat(email): the send, chunked, and a bad address that cannot stop it"
```

---

### Task 6: Admin screens

**Files:**
- Modify: `extensions/email-campaigns/src/Http/Controllers/AdminEmailCampaignController.php` (replace the Task 1 stub)
- Create: `extensions/email-campaigns/src/Http/Requests/EmailCampaignRequest.php`
- Modify: `extensions/email-campaigns/routes/web.php`
- Create: `extensions/email-campaigns/resources/views/index.blade.php`
- Create: `extensions/email-campaigns/resources/views/form.blade.php`
- Create: `extensions/email-campaigns/resources/css/email-campaigns.css`
- Test: `tests/Feature/EmailCampaigns/AdminScreensTest.php`

**Interfaces:**
- Consumes: `CampaignRecipients::audienceTotal()` and `willReceive()` (Task 2), `AudienceRegistry::all()` (Task 2), `CampaignRenderer::tokens()` (Task 4), `SendCampaignJob` (Task 5).
- Produces: routes `admin.email-campaigns.index|create|store|edit|update|destroy|send|test|recipient-count`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminScreensTest extends TestCase
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

    private function customer(string $email, bool $newsletter = true): Customer
    {
        $group = CustomerGroup::firstOrCreate(['store_id' => 1, 'name' => 'Default']);

        return Customer::create([
            'store_id' => 1, 'customer_group_id' => $group->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => $email,
            'password' => bcrypt('secret'), 'newsletter' => $newsletter, 'is_active' => true,
        ]);
    }

    public function test_the_index_lists_campaigns(): void
    {
        EmailCampaign::create([
            'store_id' => 1, 'subject' => 'Reseller price list',
            'audience_type' => 'group', 'audience_params' => ['group_id' => 1], 'status' => 'sent',
        ]);

        $this->get(route('admin.email-campaigns.index'))
            ->assertOk()
            ->assertSee('Reseller price list');
    }

    public function test_the_compose_screen_renders(): void
    {
        $this->get(route('admin.email-campaigns.create'))
            ->assertOk()
            ->assertSee('All customers')
            ->assertSee('{unsubscribe_url}', false);
    }

    public function test_saving_a_draft_does_not_send(): void
    {
        Bus::fake();

        $this->post(route('admin.email-campaigns.store'), [
            'subject' => 'Draft one',
            'body' => '<p>Hi</p>',
            'audience_type' => 'all',
            'action' => 'draft',
        ])->assertRedirect();

        Bus::assertNotDispatched(SendCampaignJob::class);
        $this->assertSame('draft', EmailCampaign::first()->status);
    }

    public function test_sending_queues_the_job_and_marks_the_campaign_queued(): void
    {
        Bus::fake();

        $this->post(route('admin.email-campaigns.store'), [
            'subject' => 'Go out now',
            'body' => '<p>Hi</p>',
            'audience_type' => 'all',
            'action' => 'send',
        ])->assertRedirect();

        Bus::assertDispatched(SendCampaignJob::class);
        $this->assertSame('queued', EmailCampaign::first()->status);
    }

    public function test_the_recipient_count_endpoint_reports_both_figures(): void
    {
        $this->customer('in@example.com');
        $this->customer('out@example.com', false);

        $this->getJson(route('admin.email-campaigns.recipient-count', ['audience_type' => 'all']))
            ->assertOk()
            ->assertJson(['audience_total' => 2, 'will_receive' => 1]);
    }

    /** The test send goes to the admin, who is not a customer and has no flag to consult. */
    public function test_a_test_send_mails_the_logged_in_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($admin);

        $this->post(route('admin.email-campaigns.test'), [
            'subject' => 'Preview me',
            'body' => '<p>Hi {first_name}</p>',
        ])->assertRedirect();

        Mail::assertSent(\Extensions\EmailCampaigns\Mail\CampaignMail::class,
            fn ($m) => $m->hasTo('admin@example.com'));
    }

    public function test_a_campaign_requires_a_subject_and_an_audience(): void
    {
        $this->post(route('admin.email-campaigns.store'), ['body' => '<p>Hi</p>'])
            ->assertSessionHasErrors(['subject', 'audience_type']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/AdminScreensTest.php`
Expected: FAIL with `Route [admin.email-campaigns.create] not defined.`

- [ ] **Step 3: Create the form request**

`extensions/email-campaigns/src/Http/Requests/EmailCampaignRequest.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'audience_type' => ['required', Rule::in(['group', 'subscribers', 'all', 'selected'])],
            'group_id' => ['required_if:audience_type,group', 'nullable', 'integer', 'exists:customer_groups,id'],
            'customer_ids' => ['required_if:audience_type,selected', 'nullable', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'action' => ['required', Rule::in(['draft', 'send'])],
        ];
    }

    /** @return array<string, mixed>|null the audience_params column value */
    public function audienceParams(): ?array
    {
        return match ($this->input('audience_type')) {
            'group' => ['group_id' => (int) $this->input('group_id')],
            'selected' => ['customer_ids' => array_map('intval', $this->input('customer_ids', []))],
            default => null,
        };
    }
}
```

- [ ] **Step 4: Replace the controller**

`extensions/email-campaigns/src/Http/Controllers/AdminEmailCampaignController.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use App\Models\Setting;
use Extensions\EmailCampaigns\Audiences\AudienceRegistry;
use Extensions\EmailCampaigns\Http\Requests\EmailCampaignRequest;
use Extensions\EmailCampaigns\Jobs\SendCampaignJob;
use Extensions\EmailCampaigns\Mail\CampaignMail;
use Extensions\EmailCampaigns\Models\EmailCampaign;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Extensions\EmailCampaigns\Services\CampaignRecipients;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AdminEmailCampaignController extends Controller
{
    public function index()
    {
        return view('email-campaigns::index', [
            'campaigns' => EmailCampaign::latest()->paginate(20),
            'audienceLabels' => AudienceRegistry::all(),
        ]);
    }

    public function create(Request $request)
    {
        $template = $request->filled('from_template')
            ? EmailCampaignTemplate::find($request->input('from_template'))
            : null;

        return view('email-campaigns::form', [
            'campaign' => null,
            'prefill' => $template,
            'audiences' => AudienceRegistry::all(),
            'groups' => CustomerGroup::orderBy('name')->get(),
            'tokens' => CampaignRenderer::tokens(),
            'templates' => EmailCampaignTemplate::orderBy('name')->get(),
        ]);
    }

    public function store(EmailCampaignRequest $request)
    {
        $campaign = EmailCampaign::create([
            'store_id' => 1,
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'audience_type' => $request->input('audience_type'),
            'audience_params' => $request->audienceParams(),
            'status' => $request->input('action') === 'send' ? 'queued' : 'draft',
        ]);

        if ($request->input('action') === 'send') {
            SendCampaignJob::dispatch($campaign->id);

            return redirect()->route('admin.email-campaigns.index')
                ->with('success', 'Campaign queued for sending.');
        }

        return redirect()->route('admin.email-campaigns.edit', $campaign)
            ->with('success', 'Draft saved.');
    }

    public function edit(EmailCampaign $campaign)
    {
        return view('email-campaigns::form', [
            'campaign' => $campaign,
            'prefill' => null,
            'audiences' => AudienceRegistry::all(),
            'groups' => CustomerGroup::orderBy('name')->get(),
            'tokens' => CampaignRenderer::tokens(),
            'templates' => EmailCampaignTemplate::orderBy('name')->get(),
        ]);
    }

    public function update(EmailCampaignRequest $request, EmailCampaign $campaign)
    {
        // A campaign that has gone out is a record of what happened, not a draft.
        if ($campaign->status === 'sent') {
            return back()->with('error', 'This campaign has already been sent.');
        }

        $campaign->update([
            'subject' => $request->input('subject'),
            'body' => $request->input('body'),
            'audience_type' => $request->input('audience_type'),
            'audience_params' => $request->audienceParams(),
            'status' => $request->input('action') === 'send' ? 'queued' : 'draft',
        ]);

        if ($request->input('action') === 'send') {
            SendCampaignJob::dispatch($campaign->id);

            return redirect()->route('admin.email-campaigns.index')
                ->with('success', 'Campaign queued for sending.');
        }

        return back()->with('success', 'Draft saved.');
    }

    public function destroy(EmailCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.email-campaigns.index')->with('success', 'Campaign deleted.');
    }

    /** Live counts for the compose screen's audience picker. */
    public function recipientCount(Request $request)
    {
        $draft = new EmailCampaign([
            'audience_type' => $request->input('audience_type', 'all'),
            'audience_params' => match ($request->input('audience_type')) {
                'group' => ['group_id' => (int) $request->input('group_id')],
                'selected' => ['customer_ids' => array_map('intval', (array) $request->input('customer_ids', []))],
                default => null,
            },
        ]);

        return response()->json([
            'audience_total' => CampaignRecipients::audienceTotal($draft),
            'will_receive' => CampaignRecipients::willReceive($draft),
        ]);
    }

    /**
     * Mails the logged-in admin, who is not a customer and has no newsletter
     * flag to consult, so the consent filter does not apply here.
     */
    public function test(Request $request)
    {
        $request->validate(['subject' => ['required', 'string'], 'body' => ['nullable', 'string']]);

        $admin = Auth::user();
        $storeName = Setting::where('group', 'identity')->where('key', 'site_name')->value('value') ?? config('app.name');

        $sample = [
            'first_name' => $admin->name,
            'last_name' => '',
            'email' => $admin->email,
            'store_name' => $storeName,
            'unsubscribe_url' => '#',
        ];

        $logo = Setting::where('group', 'identity')->where('key', 'logo')->value('value');

        Mail::to($admin->email)->send(new CampaignMail(
            CampaignRenderer::renderWith($request->input('subject'), $sample),
            CampaignRenderer::renderWith($request->input('body') ?? '', $sample),
            $storeName,
            $logo ? storage_url($logo) : null,
        ));

        return back()->with('success', 'Test sent to ' . $admin->email . '.');
    }
}
```

- [ ] **Step 5: Add the routes**

Replace the admin group in `extensions/email-campaigns/routes/web.php` with:

```php
Route::middleware(['web', 'auth', 'admin.session.cap'])
    ->prefix($adminPrefix . '/extensions/email-campaigns')
    ->name('admin.email-campaigns.')
    ->group(function () {
        // Literals before the {campaign} wildcard so it cannot swallow them.
        Route::get('/recipient-count', [AdminEmailCampaignController::class, 'recipientCount'])->name('recipient-count');
        Route::post('/test', [AdminEmailCampaignController::class, 'test'])->name('test');

        Route::get('/', [AdminEmailCampaignController::class, 'index'])->name('index');
        Route::get('/create', [AdminEmailCampaignController::class, 'create'])->name('create');
        Route::post('/', [AdminEmailCampaignController::class, 'store'])->name('store');
        Route::get('/{campaign}/edit', [AdminEmailCampaignController::class, 'edit'])->name('edit')->whereNumber('campaign');
        Route::put('/{campaign}', [AdminEmailCampaignController::class, 'update'])->name('update')->whereNumber('campaign');
        Route::delete('/{campaign}', [AdminEmailCampaignController::class, 'destroy'])->name('destroy')->whereNumber('campaign');
    });
```

- [ ] **Step 6: Create the index view**

`extensions/email-campaigns/resources/views/index.blade.php`, following the Coupons index shape:

```blade
<x-admin.shell title="Email">
    <div class="v-head">
        <div class="v-head__body">
            <div class="v-head__trail">
                <a href="{{ route('admin.extensions.index') }}">Extensions</a>
                <span aria-hidden="true">/</span>
                <span>Email</span>
            </div>
            <h1 class="v-head__title">Email</h1>
        </div>
        <div class="v-head__actions">
            <a href="{{ route('admin.email-campaigns.create') }}" class="v-btn v-btn--primary">New campaign</a>
        </div>
    </div>

    <div class="v-settle">
        <div class="v-tablewrap">
            <table class="v-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Audience</th>
                        <th class="v-cell-num">Recipients</th>
                        <th>Status</th>
                        <th>Sent</th>
                        <th class="v-cell-act"><span class="v-sr">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td><a href="{{ route('admin.email-campaigns.edit', $campaign) }}" class="v-rowlink">{{ $campaign->subject }}</a></td>
                            <td class="v-cell-quiet">{{ $audienceLabels[$campaign->audience_type] ?? $campaign->audience_type }}</td>
                            <td class="v-cell-num v-mono">{{ $campaign->recipients_total }}</td>
                            <td><span class="v-badge">{{ ucfirst($campaign->status) }}</span></td>
                            <td class="v-cell-quiet">{{ $campaign->sent_at?->format('M j, Y g:ia') ?? '-' }}</td>
                            <td class="v-cell-act">
                                <a href="{{ route('admin.email-campaigns.edit', $campaign) }}" class="v-btn v-btn--ghost v-btn--sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="v-empty">
                                    <p class="v-empty__line">No campaigns yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $campaigns->links() }}
    </div>
</x-admin.shell>
```

- [ ] **Step 7: Create the compose view**

`extensions/email-campaigns/resources/views/form.blade.php`. Key requirements: a `<select name="audience_type">` listing `$audiences`, a group select shown only for `group`, a customer picker shown only for `selected`, the live counts fetched from `recipient-count`, a TinyMCE textarea named `body`, a token toolbar rendering `$tokens` as buttons that insert `{key}` at the cursor, and three submit buttons (`action=draft`, `action=send`, plus a separate test form posting to `admin.email-campaigns.test`).

TinyMCE init follows the blog form's pattern exactly (`extensions/blog/resources/views/blog/form.blade.php`, the `@push('scripts')` block), pointing `selector` at `#v-campaign-body`. Register a toolbar button per token using `editor.insertContent('{' + key + '}')`.

The `{unsubscribe_url}` token MUST appear in the toolbar. Its presence in the rendered page is what `test_the_compose_screen_renders` asserts.

- [ ] **Step 8: Create the stylesheet**

`extensions/email-campaigns/resources/css/email-campaigns.css` for the token toolbar and the recipient-count readout. No CSS in Blade.

`vite.config.js` needs no change: it already globs `extensions/{slug}/resources/css/*.css` and compiles each as its own entry (see the comment at line 26 and the loop below it). Dropping the file in the folder is the whole wiring. `extensions/courier-fulfillment/resources/css/fulfillment.css` is the precedent.

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan view:clear && npm run build && php artisan test tests/Feature/EmailCampaigns/AdminScreensTest.php`
Expected: PASS, 7 tests.

- [ ] **Step 10: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/AdminScreensTest.php
git commit -m "feat(email): compose, count who will actually get it, and send"
```

---

### Task 7: Saved templates

**Files:**
- Create: `extensions/email-campaigns/src/Http/Controllers/AdminEmailCampaignTemplateController.php`
- Modify: `extensions/email-campaigns/routes/web.php`
- Create: `extensions/email-campaigns/resources/views/templates/index.blade.php`
- Create: `extensions/email-campaigns/resources/views/templates/form.blade.php`
- Test: `tests/Feature/EmailCampaigns/SavedTemplateTest.php`

**Interfaces:**
- Consumes: `EmailCampaignTemplate` (Task 1), the compose screen's `from_template` query parameter (Task 6).
- Produces: routes `admin.email-campaigns.templates.index|create|store|edit|update|destroy`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\EmailCampaigns;

use App\Models\EmailTemplate;
use App\Models\Store;
use App\Models\User;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedTemplateTest extends TestCase
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

    public function test_a_template_can_be_saved_and_listed(): void
    {
        $this->post(route('admin.email-campaigns.templates.store'), [
            'name' => 'Monthly reseller price list',
            'subject' => 'Price list for {first_name}',
            'body' => '<p>Attached</p>',
        ])->assertRedirect();

        $this->get(route('admin.email-campaigns.templates.index'))
            ->assertOk()
            ->assertSee('Monthly reseller price list');
    }

    public function test_composing_from_a_template_prefills_subject_and_body(): void
    {
        $template = EmailCampaignTemplate::create([
            'store_id' => 1,
            'name' => 'Reseller list',
            'subject' => 'Price list',
            'body' => '<p>Attached</p>',
        ]);

        $this->get(route('admin.email-campaigns.create', ['from_template' => $template->id]))
            ->assertOk()
            ->assertSee('Price list')
            ->assertSee('Attached', false);
    }

    /**
     * Campaign templates and core's transactional email_templates are separate
     * tables on purpose: merging them would put "Order Confirmation" in the
     * campaign picker and a half-written promo in the order pipeline.
     */
    public function test_campaign_templates_are_not_core_email_templates(): void
    {
        EmailCampaignTemplate::create(['store_id' => 1, 'name' => 'Promo', 'subject' => 'Hi', 'body' => '<p>Hi</p>']);

        $this->assertSame(0, EmailTemplate::where('name', 'Promo')->count());
        $this->assertSame(1, EmailCampaignTemplate::count());
    }

    public function test_a_template_needs_a_name(): void
    {
        $this->post(route('admin.email-campaigns.templates.store'), ['subject' => 'Hi'])
            ->assertSessionHasErrors('name');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailCampaigns/SavedTemplateTest.php`
Expected: FAIL with `Route [admin.email-campaigns.templates.store] not defined.`

- [ ] **Step 3: Create the controller**

`extensions/email-campaigns/src/Http/Controllers/AdminEmailCampaignTemplateController.php`:

```php
<?php

namespace Extensions\EmailCampaigns\Http\Controllers;

use App\Http\Controllers\Controller;
use Extensions\EmailCampaigns\Models\EmailCampaignTemplate;
use Extensions\EmailCampaigns\Services\CampaignRenderer;
use Illuminate\Http\Request;

class AdminEmailCampaignTemplateController extends Controller
{
    public function index()
    {
        return view('email-campaigns::templates.index', [
            'templates' => EmailCampaignTemplate::orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('email-campaigns::templates.form', [
            'template' => null,
            'tokens' => CampaignRenderer::tokens(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['store_id'] = 1;

        EmailCampaignTemplate::create($data);

        return redirect()->route('admin.email-campaigns.templates.index')->with('success', 'Template saved.');
    }

    public function edit(EmailCampaignTemplate $template)
    {
        return view('email-campaigns::templates.form', [
            'template' => $template,
            'tokens' => CampaignRenderer::tokens(),
        ]);
    }

    public function update(Request $request, EmailCampaignTemplate $template)
    {
        $template->update($this->validated($request));

        return back()->with('success', 'Template saved.');
    }

    public function destroy(EmailCampaignTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.email-campaigns.templates.index')->with('success', 'Template deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

Inside the existing admin group in `extensions/email-campaigns/routes/web.php`, before the `{campaign}` wildcard routes:

```php
        Route::get('/templates', [AdminEmailCampaignTemplateController::class, 'index'])->name('templates.index');
        Route::get('/templates/create', [AdminEmailCampaignTemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [AdminEmailCampaignTemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [AdminEmailCampaignTemplateController::class, 'edit'])->name('templates.edit')->whereNumber('template');
        Route::put('/templates/{template}', [AdminEmailCampaignTemplateController::class, 'update'])->name('templates.update')->whereNumber('template');
        Route::delete('/templates/{template}', [AdminEmailCampaignTemplateController::class, 'destroy'])->name('templates.destroy')->whereNumber('template');
```

Add the import:

```php
use Extensions\EmailCampaigns\Http\Controllers\AdminEmailCampaignTemplateController;
```

- [ ] **Step 5: Create the two views**

`templates/index.blade.php` mirrors the campaign index: `x-admin.shell` titled "Email templates", breadcrumb Extensions / Email / Templates, a table of name and subject with Edit and Delete, a "New template" action, and a `v-empty` block reading "No saved templates yet."

`templates/form.blade.php` mirrors the compose screen minus the audience picker: a `name` input, a `subject` input, a TinyMCE `body` textarea with the same token toolbar, and the standard armed save bar.

Add a "Start from template" select to `form.blade.php` (Task 6) that links to `route('admin.email-campaigns.create', ['from_template' => $id])`, and a "Templates" button in the campaign index header.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan view:clear && php artisan test tests/Feature/EmailCampaigns/SavedTemplateTest.php`
Expected: PASS, 4 tests.

- [ ] **Step 7: Run the whole suite**

Run: `php artisan test`
Expected: PASS, no regressions.

- [ ] **Step 8: Commit**

```bash
git add extensions/email-campaigns tests/Feature/EmailCampaigns/SavedTemplateTest.php
git commit -m "feat(email): save the shape of a mail you send every month"
```

---

## Self-review notes

Checked against the spec on 2026-08-19.

**Spec coverage:** placement and manifest (Task 1), both tables (Task 1), four resolvers (Task 2), consent applied once after the resolver (Task 2), both recipient figures (Task 2), signed unsubscribe and separately signed resubscribe (Task 3), placeholder substitution reusing the `{key}` convention (Task 4), fixed table-based shell (Task 4), queued chunked send with per-recipient failure isolation (Task 5), `failed` reserved for job failure (Task 5), three admin screens (Tasks 6 and 7), test-send exempt from consent (Task 6), saved templates separate from `email_templates` (Task 7).

**Deliberately not built,** matching the spec's omissions: per-recipient result rows, scheduling, open and click tracking, A/B testing, block editor.

**Type consistency:** `CampaignRecipients::for()` returns `Builder` in Task 2 and is consumed as a query by `chunkById` in Task 5. `CampaignRenderer::render(string, Customer): string` is defined in Task 4 and called with that signature in Task 5. `SendCampaignJob::__construct(int $campaignId)` is defined in Task 5 and dispatched with an id in Task 6. `AudienceRegistry::all(): array<string,string>` is defined in Task 2 and consumed as `$audiences` and `$audienceLabels` in Task 6.

**Resolved during self-review:** an earlier draft left the Vite wiring conditional. It is not: `vite.config.js` already globs `extensions/{slug}/resources/css/*.css` and compiles each as its own entry, so the stylesheet needs no config change. Task 6 Step 8 now says so outright, and `vite.config.js` is dropped from that task's commit.
