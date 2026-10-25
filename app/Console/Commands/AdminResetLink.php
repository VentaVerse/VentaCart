<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * A password reset link for an admin, made by whoever runs the server.
 *
 * For an admin locked out of their account, for example on a hosted store
 * where they cannot reach the server themselves. The link is emailed to the
 * admin and printed, so the operator can pass it on when the store's mail is
 * not set up. It is the same link the "Forgot password?" page sends, and
 * expires the same way.
 */
class AdminResetLink extends Command
{
    protected $signature = 'admin:reset-link
        {user : The admin\'s username or email}
        {--no-email : Print the link without emailing it}';

    protected $description = 'Make a password reset link for an admin, email it to them and print it';

    public function handle(): int
    {
        $who = (string) $this->argument('user');
        $user = User::where('username', $who)->orWhere('email', $who)->first();

        if (! $user) {
            $this->error("No admin with the username or email \"{$who}\".");

            return self::FAILURE;
        }

        // Made directly rather than through the "Forgot password?" throttle:
        // the operator asking is not the anonymous visitor it guards against.
        // Making one replaces any link the admin was sent before.
        $token = Password::broker('users')->createToken($user);
        $link = route('password.reset', ['token' => $token, 'email' => $user->email]);

        if (! $this->option('no-email')) {
            try {
                $user->sendPasswordResetNotification($token);
                $this->info("Emailed a password reset link to {$user->email}.");
            } catch (\Throwable $e) {
                $this->warn("Could not email {$user->email}: {$e->getMessage()}");
                $this->warn('Send them the link below yourself.');
            }
        }

        if (! $user->is_active) {
            $this->warn("{$user->username} is switched off, so they cannot sign in until an admin switches them on again.");
        }

        $minutes = (int) config('auth.passwords.users.expire', 60);
        $this->line('');
        $this->line($link);
        $this->line('');
        $this->line("The link works once and expires in {$minutes} minutes.");

        return self::SUCCESS;
    }
}
