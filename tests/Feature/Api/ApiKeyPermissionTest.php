<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A key reaches only what it was given.
 *
 * The point of the ticks is that a key can be handed to something that only
 * needs to read orders without also handing it the ability to book parcels
 * with real couriers or delete products.
 */
class ApiKeyPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        // The fulfillment routes only exist once its extension is installed,
        // and a route that does not exist answers 405, not 403.
        app(\App\Services\ExtensionManager::class)->install('courier-fulfillment');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
    }

    public function test_a_read_only_key_may_read_orders(): void
    {
        $this->apiKey('read-only-key', ['orders' => ['read']]);

        $this->withToken('read-only-key')->getJson('/api/v1/orders')->assertOk();
    }

    public function test_a_read_only_key_may_not_write(): void
    {
        $this->apiKey('read-only-key', ['orders' => ['read']]);

        $this->withToken('read-only-key')
            ->putJson('/api/v1/orders/1/status', ['status' => 'x'])
            ->assertStatus(403)
            ->assertJsonFragment(['error' => 'This key is not permitted to write orders.']);
    }

    /** Areas are separate: orders does not carry catalogue with it. */
    public function test_an_area_does_not_grant_another(): void
    {
        $this->apiKey('orders-key', ['orders' => ['read', 'write']]);

        $this->withToken('orders-key')->getJson('/api/v1/products')->assertStatus(403);
        $this->withToken('orders-key')->getJson('/api/v1/orders')->assertOk();
    }

    /** The reason fulfillment is its own area rather than part of orders. */
    public function test_reading_orders_does_not_permit_booking_a_parcel(): void
    {
        $this->apiKey('orders-key', ['orders' => ['read', 'write']]);

        $this->withToken('orders-key')
            ->postJson('/api/v1/orders/1/book-courier', [])
            ->assertStatus(403)
            ->assertJsonFragment(['error' => 'This key is not permitted to write fulfillment.']);
    }

    public function test_an_unknown_key_is_refused(): void
    {
        $this->apiKey('good-key');

        $this->withToken('not-a-key')->getJson('/api/v1/orders')->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    /** Deleting a key locks it out at once. */
    public function test_a_deleted_key_stops_working(): void
    {
        $key = $this->apiKey('doomed-key');
        $this->withToken('doomed-key')->getJson('/api/v1/orders')->assertOk();

        $key->delete();

        $this->withToken('doomed-key')->getJson('/api/v1/orders')->assertStatus(401);
    }

    /** A key nobody uses should be visibly safe to delete. */
    public function test_using_a_key_records_when(): void
    {
        $key = $this->apiKey('used-key');
        $this->assertNull($key->last_used_at);

        $this->withToken('used-key')->getJson('/api/v1/orders')->assertOk();

        $this->assertNotNull($key->fresh()->last_used_at);
    }

    /** The secret is never recoverable, by anyone, including Venta. */
    public function test_the_secret_is_not_stored(): void
    {
        [$key, $secret] = ApiKey::issue('Reporting', ['orders' => ['read']]);

        $this->assertStringStartsWith('vnt_', $secret);
        $this->assertNotSame($secret, $key->key_hash);
        $this->assertStringNotContainsString(substr($secret, 12), json_encode($key->toArray()));
        $this->assertSame($key->id, ApiKey::findBySecret($secret)?->id);
        $this->assertNull(ApiKey::findBySecret($secret . 'x'));
    }

    /** A typo in the abilities cannot grant anything. */
    public function test_unknown_areas_and_modes_are_discarded(): void
    {
        [$key] = ApiKey::issue('Odd', ['orders' => ['read', 'delete'], 'nonsense' => ['read']]);

        $this->assertSame(['orders' => ['read']], $key->abilities);
    }
}
