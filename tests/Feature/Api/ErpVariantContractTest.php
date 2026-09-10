<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What the ERP can say about one variant.
 *
 * Two things it could not say before. Whether a variant is sellable was
 * writable but only half readable: the read endpoint returned it and both
 * write responses did not, so an ERP that disabled a colourway got back a
 * payload that could not confirm it had happened. And a variant had no
 * picture at all, in either direction, on a column that has existed since the
 * table was created and that nothing had ever written to.
 *
 * Hosts are literal IP addresses on purpose, exactly as in the image push
 * tests: SafeUrlFetcher validates literal IPs without a DNS lookup, so
 * nothing here touches the network.
 */
class ErpVariantContractTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO = 'http://203.0.113.10/blue.jpg';

    private const BROKEN = 'http://203.0.113.10/missing.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Store::create(['name' => 'Test Store', 'code' => 'test']);
        app(\App\Services\ExtensionManager::class)->install('erp-sync');
        app(\App\Services\ExtensionManager::class)->bootEnabled();
        Storage::fake('public');

        $this->apiKey('erp-test-token');

        Http::preventStrayRequests();
        Http::fake([
            '*/blue.jpg' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
            '*/missing.jpg' => Http::response('not found', 404),
        ]);
    }

    private function push(array $variants, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withToken('erp-test-token')->postJson('/api/v1/products', array_merge([
            'name' => 'Ernie Ball Strap',
            'sku' => 'EB-STRAP',
            'price' => 1200,
            'variants' => $variants,
        ], $overrides));
    }

    private function variant(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'EB-STRAP-BLUE',
            'name' => 'Blue',
            'option_name' => 'Colour',
            'price' => 1200,
        ], $overrides);
    }

    public function test_the_erp_can_retire_one_variant_without_deleting_it(): void
    {
        $this->push([
            $this->variant(['sku' => 'EB-STRAP-BLUE', 'name' => 'Blue', 'status' => false]),
            $this->variant(['sku' => 'EB-STRAP-RED', 'name' => 'Red', 'status' => true]),
        ])->assertStatus(201);

        $this->assertFalse(ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->is_active);
        $this->assertTrue(ProductVariant::where('sku', 'EB-STRAP-RED')->first()->is_active);
    }

    public function test_every_endpoint_answers_with_the_same_variant_shape(): void
    {
        $created = $this->push([$this->variant(['status' => false])])->assertStatus(201);

        $read = $this->withToken('erp-test-token')->getJson('/api/v1/products/EB-STRAP')->assertOk();

        $updated = $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['status' => false])]])
            ->assertOk();

        foreach ([[$created, 'create'], [$read, 'read'], [$updated, 'update']] as [$response, $which]) {
            $variant = $response->json('data.variants.0') ?? $response->json('variants.0');
            $this->assertIsArray($variant, "the {$which} response carried no variant");
            $this->assertArrayHasKey('is_active', $variant, "the {$which} response omits is_active");
            $this->assertArrayHasKey('image', $variant, "the {$which} response omits image");
            $this->assertFalse($variant['is_active'], "the {$which} response disagrees about is_active");
        }
    }

    public function test_a_pushed_photo_is_fetched_and_stored(): void
    {
        $this->push([$this->variant(['image' => self::PHOTO])])->assertStatus(201);

        $path = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->image;

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * The one field the ERP does not own. A variant photo is merchandising,
     * chosen in the admin, and syncVariantsFromErp rebuilds every variant from
     * scratch, so without carrying it forward every stock push would wipe it.
     */
    public function test_a_photo_set_in_the_admin_survives_a_push_that_does_not_mention_it(): void
    {
        $this->push([$this->variant()])->assertStatus(201);

        $variant = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first();
        $variant->update(['image' => 'products/variants/chosen-by-hand.jpg']);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['quantity' => 40])]])
            ->assertOk();

        $this->assertSame(
            'products/variants/chosen-by-hand.jpg',
            ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->image
        );
    }

    public function test_a_photo_the_erp_sends_beats_the_one_already_there(): void
    {
        $this->push([$this->variant()])->assertStatus(201);
        ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->update(['image' => 'products/variants/old.jpg']);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['image' => self::PHOTO])]])
            ->assertOk();

        $this->assertNotSame('products/variants/old.jpg', ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->image);
    }

    /**
     * Deliberately unlike the product's own images, where one failed fetch
     * rejects the whole push. A colourway photo is not worth failing a stock
     * and price sync over.
     */
    public function test_a_photo_that_cannot_be_fetched_does_not_fail_the_sync(): void
    {
        $this->push([$this->variant(['quantity' => 12, 'image' => self::BROKEN])])->assertStatus(201);

        $variant = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first();

        $this->assertSame(12, $variant->quantity);
        $this->assertNull($variant->image);
    }

    /**
     * The ERP path had the same delete-and-recreate as the admin one, so a
     * nightly stock sync churned every variant id and cut every order line's
     * link to the colourway it sold.
     */
    public function test_a_push_keeps_the_variant_it_is_updating(): void
    {
        $this->push([$this->variant()])->assertStatus(201);

        $before = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first();
        $before->update(['legacy_oc_id' => 991]);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['quantity' => 77])]])
            ->assertOk();

        $after = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first();

        $this->assertSame($before->id, $after->id);
        $this->assertSame(991, $after->legacy_oc_id);
        $this->assertSame(77, $after->quantity);
        $this->assertSame(1, $after->values()->count(), 'the option value must not stack on every push');
    }

    public function test_a_variant_the_erp_stopped_sending_is_removed(): void
    {
        $this->push([
            $this->variant(['sku' => 'EB-STRAP-BLUE', 'name' => 'Blue']),
            $this->variant(['sku' => 'EB-STRAP-RED', 'name' => 'Red']),
        ])->assertStatus(201);

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['sku' => 'EB-STRAP-BLUE', 'name' => 'Blue'])]])
            ->assertOk();

        $this->assertNull(ProductVariant::where('sku', 'EB-STRAP-RED')->first());
        $this->assertNotNull(ProductVariant::where('sku', 'EB-STRAP-BLUE')->first());
    }

    public function test_handing_back_our_own_path_is_not_a_re_download(): void
    {
        $this->push([$this->variant(['image' => self::PHOTO])])->assertStatus(201);

        $path = ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->image;

        $this->withToken('erp-test-token')
            ->putJson('/api/v1/products/EB-STRAP', ['variants' => [$this->variant(['image' => $path])]])
            ->assertOk();

        $this->assertSame($path, ProductVariant::where('sku', 'EB-STRAP-BLUE')->first()->image);
        $this->assertCount(1, Storage::disk('public')->allFiles('products/variants'));
    }
}
