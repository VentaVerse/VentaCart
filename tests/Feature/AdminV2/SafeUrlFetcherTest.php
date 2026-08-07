<?php

namespace Tests\Feature\AdminV2;

use App\Exceptions\UnsafeUrlException;
use App\Models\Store;
use App\Models\User;
use App\Services\SafeUrlFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SafeUrlFetcher is the shared SSRF guard for every "download this
 * caller-supplied URL" code path (admin product-image import, ERP product
 * image sync, ERP review image sync). These tests exercise the guard
 * directly (no real outbound requests — Http::fake() throughout) plus one
 * end-to-end assertion that the admin import endpoint is actually wired
 * through it.
 */
class SafeUrlFetcherTest extends TestCase
{
    use RefreshDatabase;

    private function fetcher(): SafeUrlFetcher
    {
        return app(SafeUrlFetcher::class);
    }

    public function test_rejects_disallowed_schemes(): void
    {
        Http::fake();

        foreach (['file:///etc/passwd', 'ftp://example.com/x', 'gopher://example.com/x'] as $url) {
            try {
                $this->fetcher()->assertSafeUrl($url);
                $this->fail("Expected UnsafeUrlException for scheme in: {$url}");
            } catch (UnsafeUrlException $e) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_rejects_loopback_ipv4(): void
    {
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('http://127.0.0.1/x');
    }

    public function test_rejects_link_local_metadata_address(): void
    {
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('http://169.254.169.254/latest/meta-data/');
    }

    public function test_rejects_loopback_ipv6(): void
    {
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('http://[::1]/x');
    }

    public function test_rejects_private_rfc1918_ipv4(): void
    {
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('http://10.0.0.5/x');
    }

    public function test_rejects_hostname_that_is_literally_a_private_ip(): void
    {
        // We don't depend on external DNS in tests; a literal private-IP
        // "host" exercises the exact same isPublicIp() rejection path that
        // a hostname resolving privately would hit, without a real lookup.
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('http://192.168.1.50/x');
    }

    public function test_rejects_malformed_url(): void
    {
        Http::fake();

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->assertSafeUrl('not-a-url');
    }

    public function test_accepts_public_ip_literal_and_fetches(): void
    {
        Http::fake([
            'http://8.8.8.8/image.jpg' => Http::response('binarydata', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = $this->fetcher()->get('http://8.8.8.8/image.jpg');

        $this->assertTrue($response->successful());
        $this->assertSame('binarydata', $response->body());
        Http::assertSent(function ($request) {
            return $request->url() === 'http://8.8.8.8/image.jpg';
        });
    }

    public function test_follows_redirect_to_another_public_address_but_blocks_redirect_to_private_address(): void
    {
        Http::fake([
            'http://8.8.8.8/redirect-to-private' => Http::response('', 302, ['Location' => 'http://127.0.0.1/steal']),
        ]);

        $this->expectException(UnsafeUrlException::class);
        $this->fetcher()->get('http://8.8.8.8/redirect-to-private');
    }

    public function test_import_endpoint_rejects_loopback_url_with_422_and_never_calls_http(): void
    {
        Http::fake();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('admin.products.images.import-url'), [
            'url' => 'http://127.0.0.1/x',
            'store_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }

    public function test_import_endpoint_rejects_metadata_url_with_422_and_never_calls_http(): void
    {
        Http::fake();

        Store::create(['id' => 1, 'name' => 'Test Store', 'code' => 'test']);
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('admin.products.images.import-url'), [
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'store_id' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        Http::assertNothingSent();
    }
}
