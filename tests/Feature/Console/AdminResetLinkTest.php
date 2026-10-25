<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * admin:reset-link lets whoever runs the server hand an admin a fresh
 * password reset link: emailed to them, and printed for the operator to
 * pass on when the store's mail is not set up.
 */
class AdminResetLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['username' => 'maria', 'email' => 'maria@example.com']);
    }

    public function test_it_emails_the_admin_a_reset_link_and_prints_it(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->artisan('admin:reset-link', ['user' => 'maria'])
            ->expectsOutputToContain('Emailed a password reset link to maria@example.com.')
            ->expectsOutputToContain('/reset-password/')
            ->assertSuccessful();

        Notification::assertSentTo($admin, ResetPassword::class);
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'maria@example.com')->count());
    }

    public function test_the_admin_can_be_named_by_email_too(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $this->artisan('admin:reset-link', ['user' => 'maria@example.com'])->assertSuccessful();

        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_no_email_only_prints_the_link(): void
    {
        Notification::fake();
        $this->admin();

        $this->artisan('admin:reset-link', ['user' => 'maria', '--no-email' => true])
            ->expectsOutputToContain('/reset-password/')
            ->doesntExpectOutputToContain('Emailed')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_printed_link_opens_the_reset_page(): void
    {
        $this->admin();

        Artisan::call('admin:reset-link', ['user' => 'maria', '--no-email' => true]);
        preg_match('#https?://\S+/reset-password/\S+#', Artisan::output(), $m);
        $this->assertNotEmpty($m, 'The command printed no reset link.');

        $url = parse_url($m[0]);
        $this->get($url['path'].'?'.$url['query'])->assertOk()->assertSee('maria@example.com', false);
    }

    public function test_an_unknown_admin_is_reported_and_nothing_is_sent(): void
    {
        Notification::fake();
        $this->admin();

        $this->artisan('admin:reset-link', ['user' => 'nobody'])
            ->expectsOutputToContain('No admin with the username or email "nobody".')
            ->assertFailed();

        Notification::assertNothingSent();
    }
}
