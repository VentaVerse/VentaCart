<?php

namespace Tests\Feature\Http;

use App\Support\TrustedProxies;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Forwarded headers are believed only from the proxies TRUSTED_PROXIES
 * names. A store reached directly must not let a client choose its own
 * address, or every limit keyed on the address can be stepped around.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/ip', fn () => response()->json([
            'ip' => request()->ip(),
            'secure' => request()->isSecure(),
        ]));
        Route::middleware('throttle:2,1')->get('/_test/limited', fn () => 'ok');
    }

    private function client(string $remote, array $headers = [])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remote])->withHeaders($headers);
    }

    public function test_nothing_is_trusted_by_default(): void
    {
        $this->assertNull(config('trustedproxy.proxies'));

        $this->client('192.0.2.10', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/_test/ip')
            ->assertExactJson(['ip' => '192.0.2.10', 'secure' => false]);
    }

    public function test_a_forged_address_does_not_dodge_a_rate_limit(): void
    {
        foreach (['198.51.100.1', '198.51.100.2'] as $forged) {
            $this->client('192.0.2.10', ['X-Forwarded-For' => $forged])->get('/_test/limited')->assertOk();
        }

        $this->client('192.0.2.10', ['X-Forwarded-For' => '198.51.100.3'])->get('/_test/limited')->assertStatus(429);
    }

    public function test_a_named_proxy_is_believed_and_nobody_else(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.2']]);

        $this->client('10.0.0.2', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/_test/ip')
            ->assertExactJson(['ip' => '203.0.113.9', 'secure' => true]);

        $this->client('192.0.2.10', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/_test/ip')
            ->assertExactJson(['ip' => '192.0.2.10', 'secure' => false]);
    }

    public function test_private_ranges_trusts_a_proxy_on_the_same_network(): void
    {
        config(['trustedproxy.proxies' => TrustedProxies::parse('private_ranges')]);

        $this->client('172.18.0.1', ['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->getJson('http://localhost/_test/ip')
            ->assertExactJson(['ip' => '203.0.113.9', 'secure' => true]);

        // A client on the internet sending the same headers is still itself.
        $this->client('198.51.100.7', ['X-Forwarded-For' => '10.0.0.1'])
            ->getJson('http://localhost/_test/ip')
            ->assertJsonPath('ip', '198.51.100.7');
    }

    public function test_the_setting_is_read_into_the_middlewares_shape(): void
    {
        $this->assertNull(TrustedProxies::parse(null));
        $this->assertNull(TrustedProxies::parse(''));
        $this->assertNull(TrustedProxies::parse('  '));
        $this->assertNull(TrustedProxies::parse('none'));
        $this->assertSame('*', TrustedProxies::parse('*'));
        $this->assertSame(['PRIVATE_SUBNETS'], TrustedProxies::parse('private_ranges'));
        $this->assertSame(['10.0.0.2', '10.0.1.0/24', 'PRIVATE_SUBNETS'], TrustedProxies::parse(' 10.0.0.2, 10.0.1.0/24 ,,PRIVATE_SUBNETS'));
    }
}
