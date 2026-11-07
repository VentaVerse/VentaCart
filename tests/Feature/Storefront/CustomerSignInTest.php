<?php

namespace Tests\Feature\Storefront;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Shopper sign-in is rate limited per email and address and per address,
 * and a failed sign-in reads the same whether the email is unknown, has a
 * password, or belongs to an account brought over without one. The offer
 * to email a set-a-password link depends only on whether the store has any
 * such accounts, never on the email typed.
 */
class CustomerSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
    }

    public function test_a_correct_password_signs_in(): void
    {
        $this->shopper('juan@example.test', 'secret-pass');

        $this->post(route('customer.login.submit'), ['email' => 'juan@example.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('account.dashboard'));

        $this->assertAuthenticated('customer');
    }

    public function test_the_sixth_attempt_a_minute_for_one_email_is_refused(): void
    {
        $this->shopper('juan@example.test', 'secret-pass');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.login.submit'), ['email' => 'juan@example.test', 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        }

        $this->post(route('customer.login.submit'), ['email' => 'JUAN@example.test', 'password' => 'secret-pass'])
            ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please wait a minute and try again.']);

        $this->assertGuest('customer');
    }

    public function test_one_address_trying_many_emails_is_refused(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post(route('customer.login.submit'), ['email' => "shopper{$i}@example.test", 'password' => 'wrong'])
                ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        }

        $this->post(route('customer.login.submit'), ['email' => 'another@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Too many sign-in attempts. Please wait a minute and try again.']);
    }

    public function test_another_address_is_not_held_back(): void
    {
        $this->shopper('juan@example.test', 'secret-pass');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.login.submit'), ['email' => 'juan@example.test', 'password' => 'wrong']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->post(route('customer.login.submit'), ['email' => 'juan@example.test', 'password' => 'secret-pass'])
            ->assertRedirect(route('account.dashboard'));
    }

    public function test_a_passwordless_account_reads_the_same_as_an_unknown_email_or_a_wrong_password(): void
    {
        $this->shopper('maria@example.test', null);
        $this->shopper('juan@example.test', 'secret-pass');

        $answers = [];
        foreach (['maria@example.test', 'nobody@example.test', 'juan@example.test'] as $email) {
            $this->post(route('customer.login.submit'), ['email' => $email, 'password' => 'guess'])
                ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

            $answers[$email] = session('errors')->getBag('default')->toArray();
            $this->flushSession();
        }

        $this->assertCount(1, array_unique(array_map('serialize', $answers)), 'Every failed sign-in carries the same errors.');
        $this->assertArrayHasKey('needs_reset', $answers['nobody@example.test'], 'The set-a-password offer is made whatever email was typed.');
        $this->assertGuest('customer');
    }

    public function test_the_sign_in_page_offers_the_set_password_link_after_a_failure(): void
    {
        $this->shopper('maria@example.test', null);

        $this->from(route('customer.login'))
            ->followingRedirects()
            ->post(route('customer.login.submit'), ['email' => 'nobody@example.test', 'password' => 'guess'])
            ->assertOk()
            ->assertSee('These credentials do not match our records.')
            ->assertSee('Set a password');
    }

    public function test_a_store_without_passwordless_accounts_makes_no_set_password_offer(): void
    {
        $this->shopper('juan@example.test', 'secret-pass');

        $this->post(route('customer.login.submit'), ['email' => 'nobody@example.test', 'password' => 'guess'])
            ->assertSessionHasErrors('email')
            ->assertSessionDoesntHaveErrors('needs_reset');
    }

    public function test_the_set_password_link_answers_the_same_for_every_email(): void
    {
        Mail::fake();
        Notification::fake();
        $maria = $this->shopper('maria@example.test', null);
        $this->shopper('juan@example.test', 'secret-pass');

        $answers = [];
        foreach (['maria@example.test', 'nobody@example.test', 'juan@example.test'] as $email) {
            $answers[] = $this->postJson(route('customer.password.migration'), ['email' => $email])
                ->assertOk()
                ->json();
        }

        $this->assertCount(1, array_unique(array_map('serialize', $answers)));
        $this->assertTrue($answers[0]['success']);
        $this->assertSame(['maria@example.test'], DB::table('customer_password_reset_tokens')->pluck('email')->all(), 'Only the account without a password is sent a link.');
    }

    public function test_the_set_password_link_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('customer.password.migration'), ['email' => 'maria@example.test'])->assertOk();
        }

        $this->postJson(route('customer.password.migration'), ['email' => 'maria@example.test'])
            ->assertStatus(429)
            ->assertJson(['success' => false]);
    }

    public function test_a_list_for_an_email_is_refused_without_an_error_page(): void
    {
        $this->post(route('customer.login.submit'), ['email' => ['a@example.test'], 'password' => 'x'])
            ->assertSessionHasErrors('email');
    }

    private function shopper(string $email, ?string $password): Customer
    {
        $customer = Customer::create([
            'store_id' => (int) config('app.store_id'), 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => $email, 'password' => 'placeholder', 'is_active' => true,
        ]);

        if ($password === null) {
            Customer::whereKey($customer->id)->update(['password' => null]);
        } else {
            $customer->update(['password' => $password]);
        }

        return $customer->fresh();
    }
}
