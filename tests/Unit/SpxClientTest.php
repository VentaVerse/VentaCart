<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\ExtensionManager;
use Extensions\CourierFulfillment\Couriers\Spx\SpxApiException;
use Extensions\CourierFulfillment\Couriers\Spx\SpxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Task T1 (fulfillment-workflow-v2 + SPX plan): SpxClient's HTTP core.
 *
 * The load-bearing guarantee (docs/spx/integration-brief.md §2's
 * "Implementation note for Laravel"): the exact bytes SpxClient signs are
 * the exact bytes it sends — no independent re-serialization between
 * signing and Http::withBody(), which is precisely the class of bug that
 * produces SPX's `1007 CheckSignVerificationFailedCode` in production.
 *
 * All credentials below are obviously fake (never used against a real SPX
 * endpoint — Http::fake() intercepts every call in this file).
 *
 * install() alone only upserts the extension's registry row; it's
 * bootEnabled() that actually calls registerAutoloader() (see
 * ExtensionManager::bootEnabled()), so both are needed here for this file
 * to pass standalone (`--filter=Spx`), not just as part of the full Unit
 * suite where an earlier test happens to leak the process-global
 * autoloader registration.
 */
class SpxClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('courier-fulfillment');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function configureCredentials(): void
    {
        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'uat']);
        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
    }

    public function test_is_configured_and_has_account_credentials_reflect_stored_settings(): void
    {
        $client = new SpxClient();
        $this->assertFalse($client->isConfigured());
        $this->assertFalse($client->hasAccountCredentials());

        Setting::create(['group' => 'courier', 'key' => 'spx_app_id', 'value' => '424242']);
        Setting::create(['group' => 'courier', 'key' => 'spx_secret_key', 'value' => Crypt::encryptString('fake-app-secret-not-real')]);
        $this->assertTrue($client->isConfigured());
        $this->assertFalse($client->hasAccountCredentials());

        Setting::create(['group' => 'courier', 'key' => 'spx_user_id', 'value' => '999999']);
        Setting::create(['group' => 'courier', 'key' => 'spx_user_secret', 'value' => Crypt::encryptString('fake-user-secret-not-real')]);
        $this->assertTrue($client->hasAccountCredentials());
    }

    public function test_base_url_switches_on_spx_mode_per_integration_brief(): void
    {
        $client = new SpxClient();
        $this->assertSame('https://test-stable.spx.ph/', $client->baseUrl());

        Setting::create(['group' => 'courier', 'key' => 'spx_mode', 'value' => 'production']);
        $this->assertSame('https://spx.ph/', $client->baseUrl());
    }

    public function test_post_throws_when_not_configured_and_never_sends_a_request(): void
    {
        Http::fake();
        $client = new SpxClient();

        $this->expectException(\RuntimeException::class);
        $client->post('/open/api/v1/account/verify', ['user_id' => 1, 'user_secret' => 'x']);

        Http::assertNothingSent();
    }

    /**
     * THE load-bearing test: the raw HTTP body byte-for-byte equals the
     * exact string that was signed, and the check-sign header recomputes to
     * the same value from those same bytes — proving post() never lets the
     * HTTP client re-serialize the body independently of the signature.
     */
    public function test_post_signs_and_sends_the_exact_same_encoded_bytes(): void
    {
        $this->configureCredentials();

        Http::fake([
            '*' => Http::response(['ret_code' => 0, 'message' => 'success', 'data' => ['match_result' => true]], 200),
        ]);

        $client = new SpxClient();
        $body = ['user_id' => 999999, 'user_secret' => 'fake-user-secret-not-real'];
        $data = $client->post('/open/api/v1/account/verify', $body);

        $this->assertSame(['match_result' => true], $data);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($body) {
            $expectedPayload = json_encode($body);

            // The raw wire body is EXACTLY the string that was (or should
            // have been) signed — no re-encoding in between.
            if ($request->body() !== $expectedPayload) {
                return false;
            }

            $appId = (int) $request->header('app-id')[0];
            $timestamp = (int) $request->header('timestamp')[0];
            $randomNum = (int) $request->header('random-num')[0];
            $checkSign = $request->header('check-sign')[0];

            $expectedSign = SpxClient::sign($appId, $timestamp, $randomNum, $expectedPayload, 'fake-app-secret-not-real');

            return $appId === 424242
                && $checkSign === $expectedSign
                && $request->url() === 'https://test-stable.spx.ph/open/api/v1/account/verify'
                && str_contains($request->header('Content-Type')[0] ?? '', 'application/json');
        });
    }

    public function test_post_unwraps_the_envelope_and_returns_only_data_on_success(): void
    {
        $this->configureCredentials();

        Http::fake([
            '*' => Http::response(['ret_code' => 0, 'message' => 'success.', 'data' => ['foo' => 'bar']], 200),
        ]);

        $data = (new SpxClient())->post('/open/api/v1/order/get_pickup_time', ['user_id' => 1, 'user_secret' => 'x', 'service_type' => 1]);

        $this->assertSame(['foo' => 'bar'], $data);
    }

    public function test_post_throws_a_typed_spx_api_exception_carrying_the_ret_code_on_failure(): void
    {
        $this->configureCredentials();

        Http::fake([
            '*' => Http::response(['ret_code' => 1007, 'message' => 'CheckSignVerificationFailedCode', 'data' => null], 200),
        ]);

        try {
            (new SpxClient())->post('/open/api/v1/account/verify', ['user_id' => 1, 'user_secret' => 'x']);
            $this->fail('Expected SpxApiException was not thrown.');
        } catch (SpxApiException $e) {
            $this->assertSame(1007, $e->retCode);
            $this->assertSame('CheckSignVerificationFailedCode', $e->getMessage());
        }
    }

    public function test_post_throws_a_runtime_exception_on_a_non_envelope_response(): void
    {
        $this->configureCredentials();

        Http::fake(['*' => Http::response('not json at all', 502)]);

        $this->expectException(\RuntimeException::class);
        (new SpxClient())->post('/open/api/v1/account/verify', ['user_id' => 1, 'user_secret' => 'x']);
    }
}
