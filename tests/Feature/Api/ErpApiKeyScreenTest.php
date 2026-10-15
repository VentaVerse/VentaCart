<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Creating, seeing and deleting keys from the ERP Sync page. */
class ErpApiKeyScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_the_page_lists_keys_by_prefix_and_never_the_secret(): void
    {
        [$key, $secret] = ApiKey::issue('Reporting', ['orders' => ['read']]);

        $this->get(route('admin.erp-sync.settings'))
            ->assertOk()
            ->assertSee('Reporting')
            ->assertSee($key->prefix)
            ->assertSee('Orders read')
            ->assertDontSee(substr($secret, 20), false);
    }

    public function test_a_new_key_is_shown_once_and_only_once(): void
    {
        $this->post(route('admin.erp-sync.keys.store'), [
            'label' => 'Warehouse',
            'abilities' => ['catalogue' => ['read'], 'fulfillment' => ['read', 'write']],
        ])->assertRedirect();

        $secret = session('new_api_key');
        $this->assertNotNull($secret);
        $this->assertStringStartsWith('vnt_', $secret);

        $key = ApiKey::where('label', 'Warehouse')->first();
        $this->assertSame(['catalogue' => ['read'], 'fulfillment' => ['read', 'write']], $key->abilities);

        // The flash survives exactly one request, and that request is the
        // page you land on: it must show the secret there.
        $this->get(route('admin.erp-sync.settings'))->assertOk()->assertSee($secret, false);

        // Come back a second time and it is gone for good, because only a
        // fingerprint of it was ever stored.
        $this->get(route('admin.erp-sync.settings'))->assertOk()->assertDontSee($secret, false);
    }

    /** A key permitted nothing would look like a working key and do nothing. */
    public function test_a_key_with_no_permissions_is_refused(): void
    {
        $this->post(route('admin.erp-sync.keys.store'), ['label' => 'Empty'])
            ->assertRedirect();

        $this->assertSame('Choose at least one permission for this key.', session('error'));
        $this->assertSame(0, ApiKey::count());
    }

    public function test_a_key_can_be_deleted(): void
    {
        [$key] = ApiKey::issue('Old integration', ['orders' => ['read']]);

        $this->delete(route('admin.erp-sync.keys.destroy', $key))->assertRedirect();

        $this->assertSame(0, ApiKey::count());
    }
}
