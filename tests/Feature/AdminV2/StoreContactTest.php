<?php

namespace Tests\Feature\AdminV2;

use App\Mail\TemplateMail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\CustomerOrderCancellation;
use App\Services\ThemeService;
use App\Support\MailSender;
use App\Support\StoreIdentity;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The store's contact details (email, phone, address) live on the store
 * record and are edited in Settings > General. The store email is also the
 * address every store email is sent from, under the site name, and the
 * address the store's own alerts are delivered to.
 */
class StoreContactTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_17_100000_move_mail_sender_onto_store_contact.php';

    private function store(): Store
    {
        return Store::findOrFail(config('app.store_id'));
    }

    private function setStoreEmail(?string $email): void
    {
        $this->store()->update(['email' => $email]);
        StoreIdentity::flush();
    }

    private function setSiteName(string $name): void
    {
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'site_name'], ['value' => $name]);
        app(ThemeService::class)->clearCache();
        StoreIdentity::flush();
    }

    private function saveGeneral(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->from(route('admin.settings.section', 'general'))
            ->post(route('admin.settings.identity.update'), ['site_name' => 'Demo Store'] + $fields);
    }

    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    // ------------------------------------------------------- General page

    public function test_general_saves_the_contact_details_on_the_store_record(): void
    {
        $this->actingAs(User::factory()->create());

        $this->saveGeneral([
            'email' => '  hello@example.com ',
            'phone' => ' +63 912 345 6789 ',
            'address' => "123 Demo Street\nMakati City, Metro Manila",
        ])->assertRedirect(route('admin.settings.section', 'general'))->assertSessionHasNoErrors();

        $store = $this->store()->fresh();
        $this->assertSame('hello@example.com', $store->email);
        $this->assertSame('+63 912 345 6789', $store->phone);
        $this->assertSame("123 Demo Street\nMakati City, Metro Manila", $store->address);

        // Contact is the store record; nothing is copied into settings.
        $this->assertSame(0, Setting::whereIn('key', ['email', 'phone', 'address'])->count());

        $this->get(route('admin.settings.section', 'general'))
            ->assertOk()
            ->assertSee('Store contact')
            ->assertSee('value="hello@example.com"', false)
            ->assertSee('value="+63 912 345 6789"', false)
            ->assertSee('Makati City, Metro Manila');
    }

    public function test_clearing_a_field_empties_it(): void
    {
        $this->actingAs(User::factory()->create());
        $this->store()->update(['email' => 'hello@example.com', 'phone' => '0912', 'address' => 'Somewhere']);

        $this->saveGeneral(['email' => '', 'phone' => '   ', 'address' => ''])->assertSessionHasNoErrors();

        $store = $this->store()->fresh();
        $this->assertNull($store->email);
        $this->assertNull($store->phone);
        $this->assertNull($store->address);
    }

    public function test_a_save_without_the_contact_fields_leaves_them_alone(): void
    {
        $this->actingAs(User::factory()->create());
        $this->store()->update(['email' => 'hello@example.com', 'phone' => '0912', 'address' => 'Somewhere']);

        $this->saveGeneral([])->assertSessionHasNoErrors();

        $store = $this->store()->fresh();
        $this->assertSame('hello@example.com', $store->email);
        $this->assertSame('0912', $store->phone);
        $this->assertSame('Somewhere', $store->address);
    }

    public function test_invalid_contact_details_are_rejected_on_their_fields_and_nothing_is_written(): void
    {
        $this->actingAs(User::factory()->create());
        $this->store()->update(['email' => 'hello@example.com']);

        $this->saveGeneral([
            'email' => 'not-an-email',
            'phone' => str_repeat('9', 41),
            'address' => str_repeat('a', 501),
        ])->assertRedirect(route('admin.settings.section', 'general'))
          ->assertSessionHasErrors(['email', 'phone', 'address']);

        $this->assertSame('hello@example.com', $this->store()->fresh()->email);

        // The page shows each error on its own field.
        $this->saveGeneral(['email' => 'not-an-email']);
        $this->get(route('admin.settings.section', 'general'))
            ->assertOk()
            ->assertSee('v-field__error', false)
            ->assertSee('value="not-an-email"', false);
    }

    public function test_an_overlong_email_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->saveGeneral(['email' => str_repeat('a', 250).'@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_store_identity_reads_the_contact_details_from_the_store_record(): void
    {
        $this->store()->update(['email' => 'hello@example.com', 'phone' => '0912 345 6789', 'address' => "123 Demo Street\nMakati City"]);
        StoreIdentity::flush();

        $this->assertSame('hello@example.com', StoreIdentity::email());
        $this->assertSame('0912 345 6789', StoreIdentity::phone());
        $this->assertSame("123 Demo Street\nMakati City", StoreIdentity::address());

        // Memoized until flushed.
        $this->store()->update(['email' => 'other@example.com', 'phone' => '  ', 'address' => '']);
        $this->assertSame('hello@example.com', StoreIdentity::email());

        StoreIdentity::flush();
        $this->assertSame('other@example.com', StoreIdentity::email());
        $this->assertNull(StoreIdentity::phone());
        $this->assertNull(StoreIdentity::address());
    }

    public function test_store_identity_contact_is_empty_without_a_store_record(): void
    {
        config(['app.store_id' => 999]);
        StoreIdentity::flush();

        $this->assertNull(StoreIdentity::email());
        $this->assertNull(StoreIdentity::phone());
        $this->assertNull(StoreIdentity::address());
    }

    // ------------------------------------------------- Notifications page

    public function test_notifications_no_longer_shows_the_sender_and_points_to_general(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.settings.section', 'mail'))
            ->assertOk()
            ->assertDontSee('groups[mail][from_address]', false)
            ->assertDontSee('groups[mail][from_name]', false)
            ->assertDontSee('From address')
            ->assertDontSee('From name')
            ->assertDontSee('from_address', false)
            ->assertSee('Emails are sent from the store email in')
            ->assertSee('href="'.route('admin.settings.section', 'general').'"', false);
    }

    // ------------------------------------------------------------- Sender

    public function test_the_sender_is_the_store_email_and_site_name(): void
    {
        config(['mail.from.address' => 'env@example.test', 'mail.from.name' => 'Env Name']);
        $this->setSiteName('Demo Store');
        $this->setStoreEmail('hello@example.com');

        MailSender::apply();

        $this->assertSame('hello@example.com', config('mail.from.address'));
        $this->assertSame('Demo Store', config('mail.from.name'));
    }

    public function test_the_sender_is_applied_when_the_application_boots(): void
    {
        $this->setSiteName('Demo Store');
        $this->setStoreEmail('hello@example.com');
        config(['mail.from.address' => 'env@example.test', 'mail.from.name' => 'Env Name']);

        // Boot the application's provider again against this database.
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $this->assertSame('hello@example.com', config('mail.from.address'));
        $this->assertSame('Demo Store', config('mail.from.name'));
    }

    public function test_an_empty_store_email_leaves_the_env_default(): void
    {
        config(['mail.from.address' => 'env@example.test', 'mail.from.name' => 'Env Name']);
        $this->setSiteName('Demo Store');
        $this->setStoreEmail(null);

        MailSender::apply();

        $this->assertSame('env@example.test', config('mail.from.address'));
        $this->assertSame('Env Name', config('mail.from.name'));
    }

    public function test_the_test_email_is_sent_from_the_store_email_and_site_name(): void
    {
        $this->actingAs(User::factory()->create());
        $this->setSiteName('Demo Store');
        $this->setStoreEmail('hello@example.com');
        config(['mail.default' => 'array', 'mail.from.address' => 'env@example.test', 'mail.from.name' => 'Env Name']);

        $this->postJson(route('admin.settings.test-mail'), ['to' => 'juan@example.com'])
            ->assertOk()->assertJson(['ok' => true]);

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $from = $messages[0]->getOriginalMessage()->getFrom()[0];
        $this->assertSame('hello@example.com', $from->getAddress());
        $this->assertSame('Demo Store', $from->getName());
    }

    // ---------------------------------------------------------- Migration

    public function test_the_migration_copies_an_existing_from_address_and_drops_both_settings(): void
    {
        $this->store()->update(['email' => null]);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_address'], ['value' => ' orders@example.com ']);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_name'], ['value' => 'Old Name']);

        $this->migration()->up();

        $this->assertSame('orders@example.com', $this->store()->fresh()->email);
        $this->assertSame(0, Setting::where('group', 'mail')->whereIn('key', ['from_address', 'from_name'])->count());

        // Running again is harmless.
        $this->migration()->up();
        $this->assertSame('orders@example.com', $this->store()->fresh()->email);
        $this->assertSame(0, Setting::where('group', 'mail')->whereIn('key', ['from_address', 'from_name'])->count());
    }

    public function test_the_migration_never_overwrites_a_store_email_already_set(): void
    {
        $this->store()->update(['email' => 'hello@example.com']);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_address'], ['value' => 'orders@example.com']);

        $this->migration()->up();

        $this->assertSame('hello@example.com', $this->store()->fresh()->email);
        $this->assertNull(Setting::where('group', 'mail')->where('key', 'from_address')->value('value'));
    }

    public function test_the_migration_skips_an_invalid_or_blank_from_address(): void
    {
        $this->store()->update(['email' => null]);
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_address'], ['value' => 'not an email']);

        $this->migration()->up();

        $this->assertNull($this->store()->fresh()->email);
        $this->assertSame(0, Setting::where('group', 'mail')->where('key', 'from_address')->count());

        // Nothing to copy at all, as on a fresh install.
        $this->migration()->up();
        $this->assertNull($this->store()->fresh()->email);
    }

    public function test_the_migration_rolls_back_to_the_store_email_and_site_name(): void
    {
        $this->store()->update(['email' => 'hello@example.com']);
        Setting::updateOrCreate(['group' => 'identity', 'key' => 'site_name'], ['value' => 'Demo Store']);

        $this->migration()->down();

        $this->assertSame('hello@example.com', Setting::where('group', 'mail')->where('key', 'from_address')->value('value'));
        $this->assertSame('Demo Store', Setting::where('group', 'mail')->where('key', 'from_name')->value('value'));
    }

    // ------------------------------------------------------------ Alerts

    public function test_new_order_and_cancellation_alerts_go_to_the_store_email(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        $this->setStoreEmail('hello@example.com');

        $pending = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1, 'deducts_stock' => true, 'customer_can_cancel' => true]);
        $canceled = OrderStatus::create(['store_id' => 1, 'name' => 'Canceled', 'sort_order' => 7, 'deducts_stock' => false]);
        $pending->update(['customer_cancel_status_id' => $canceled->id]);

        $customer = Customer::create(['store_id' => 1, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'password' => bcrypt('secret')]);
        $order = Order::create([
            'store_id' => 1, 'customer_id' => $customer->id, 'order_status_id' => $pending->id,
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@example.com', 'phone' => '1',
            'shipping_first_name' => 'Juan', 'shipping_last_name' => 'Dela Cruz', 'shipping_address_1' => 'x',
            'shipping_city' => 'Makati City', 'shipping_postcode' => '1200', 'subtotal' => 100, 'total' => 100,
        ]);

        CheckoutService::sendAdminOrderNotification($order);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => $m->hasTo('hello@example.com')
            && str_starts_with($m->renderedSubject, 'New Order #'.$order->id));

        CustomerOrderCancellation::cancel($order, $customer);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $m) => $m->hasTo('hello@example.com')
            && str_contains($m->renderedSubject, 'cancelled by Juan Dela Cruz'));
    }

    public function test_no_store_alert_is_sent_without_a_store_email(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        Mail::fake();
        $this->setStoreEmail(null);
        // A leftover sender setting is not an alert address any more.
        Setting::updateOrCreate(['group' => 'mail', 'key' => 'from_address'], ['value' => 'orders@example.com']);

        $status = OrderStatus::create(['store_id' => 1, 'name' => 'Pending', 'is_default' => true, 'sort_order' => 1]);
        $order = Order::create([
            'store_id' => 1, 'order_status_id' => $status->id,
            'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.com', 'phone' => '1',
            'shipping_first_name' => 'Maria', 'shipping_last_name' => 'Santos', 'shipping_address_1' => 'x',
            'shipping_city' => 'Makati City', 'shipping_postcode' => '1200', 'subtotal' => 100, 'total' => 100,
        ]);

        CheckoutService::sendAdminOrderNotification($order);

        Mail::assertNothingSent();
    }
}
