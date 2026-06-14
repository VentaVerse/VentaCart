<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\EnforceAdminSessionCap;
use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Self-contained: builds only the users/sessions tables, so it doesn't depend on
 * the full migration suite (currently sqlite-incompatible via unrelated PH-decouple
 * migrations). Exercises the real middleware and controller code.
 */
class AdminSessionPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']); // exercise the DB-driver prune path

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->rememberToken();
            $t->timestamp('web_login_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::dropIfExists('sessions');
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->text('payload');
            $t->integer('last_activity');
        });
    }

    private function admin(array $attrs = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Admin', 'email' => 'a'.Str::random(5).'@venta.test',
            'password' => Hash::make('password'),
        ], $attrs));
    }

    /** Run the cap middleware against a freshly-built request for $user. */
    private function runCap(User $user)
    {
        Auth::guard('web')->setUser($user);
        $session = app('session')->driver();
        $session->setId('sess-'.Str::random(10));
        $session->start();
        $request = Request::create('/_x');
        $request->setLaravelSession($session);

        return (new EnforceAdminSessionCap())->handle($request, fn () => response('ok'));
    }

    public function test_cap_logs_out_after_window(): void
    {
        $cap = (int) config('auth.session_policy.admin.absolute_cap_minutes');
        $user = $this->admin(['web_login_at' => now()->subMinutes($cap + 1)]);

        $response = $this->runCap($user);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString(route('login', absolute: false), $response->headers->get('Location'));
        $this->assertTrue(Auth::guard('web')->guest());
    }

    public function test_within_cap_passes_through(): void
    {
        $user = $this->admin(['web_login_at' => now()->subMinutes(60)]);

        $response = $this->runCap($user);

        $this->assertSame('ok', $response->getContent());
        $this->assertTrue(Auth::guard('web')->check());
    }

    public function test_missing_timestamp_is_not_capped(): void
    {
        $user = $this->admin(['web_login_at' => null]);

        $this->assertSame('ok', $this->runCap($user)->getContent());
    }

    public function test_login_prunes_other_sessions_rotates_token_and_stamps(): void
    {
        $user = $this->admin(['remember_token' => 'OLDTOKEN', 'web_login_at' => null]);
        Auth::guard('web')->setUser($user);

        $session = app('session')->driver();
        $session->setId('current-session');
        $session->start();
        $request = Request::create('/_login', 'POST', ['remember' => true]);
        $request->setLaravelSession($session);

        // A leftover session on another device for this admin.
        DB::table('sessions')->insert([
            'id' => 'other-device', 'user_id' => $user->id, 'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);
        // A different admin's session must NOT be touched.
        DB::table('sessions')->insert([
            'id' => 'someone-else', 'user_id' => $user->id + 999, 'payload' => 'x',
            'last_activity' => now()->timestamp,
        ]);

        // Invoke the real (protected) controller logic.
        $controller = new AuthenticatedSessionController();
        Closure::bind(fn ($req) => $this->enforceSingleSession($req), $controller, AuthenticatedSessionController::class)($request);

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);   // kicked
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);       // untouched

        $user->refresh();
        $this->assertNotSame('OLDTOKEN', $user->remember_token);              // rotated
        $this->assertNotNull($user->web_login_at);                           // stamped
    }

    public function test_remember_durations_are_configured(): void
    {
        $this->assertSame(1440, (int) config('auth.session_policy.admin.remember_minutes'));
        $this->assertSame(1440, (int) config('auth.session_policy.admin.absolute_cap_minutes'));
        $this->assertSame(7200, (int) config('auth.session_policy.customer.remember_minutes'));
    }
}
