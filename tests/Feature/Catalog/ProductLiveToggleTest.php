<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Live toggle has to reach the server.
 *
 * It is teleported into the page header, which is rendered above and outside
 * the form. A control moved out of its form belongs to no form at all, and
 * the browser leaves it out of the submission entirely. The Save beside it
 * survives that trip because it carries form="", and the toggle must too.
 *
 * The symptom this guards against is the worst kind: the save succeeds, the
 * page returns with a success notice, and the product is still live, because
 * is_active never arrived and the controller had nothing to write.
 */
class ProductLiveToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Store::firstOrCreate(['code' => 'test'], ['name' => 'Test Store']);
        $this->actingAs(User::factory()->create());
    }

    private function product(): Product
    {
        return Product::create([
            'store_id' => 1, 'name' => 'Strap', 'slug' => 'strap',
            'price' => 1000, 'is_active' => true,
        ]);
    }

    /** Every control that leaves the form must name the form it left. */
    public function test_the_teleported_live_toggle_is_wired_back_to_its_form(): void
    {
        $html = $this->get(route('admin.products.edit', $this->product()))
            ->assertOk()
            ->getContent();

        preg_match('/<form[^>]*id="(vform-[^"]+|[^"]*)"[^>]*action="[^"]*products/', $html, $f);
        $formId = $f[1] ?? null;
        $this->assertNotNull($formId, 'The product form should carry an id for teleported controls to reference.');

        // The page teleports more than one block into the header (the Save is
        // another), so take the one that actually carries the toggle.
        preg_match_all('/<template x-teleport="\.v-head">(.*?)<\/template>/s', $html, $blocks);
        $teleported = '';
        foreach ($blocks[1] as $block) {
            if (str_contains($block, 'is_active')) {
                $teleported = $block;
            }
        }
        $this->assertNotSame('', $teleported,
            'The Live toggle is expected to be teleported into the header.');

        preg_match_all('/<input[^>]*name="is_active"[^>]*>/', $teleported, $inputs);
        $this->assertNotEmpty($inputs[0], 'Expected the is_active inputs inside the teleported block.');

        foreach ($inputs[0] as $input) {
            $this->assertMatchesRegularExpression(
                '/\bform="' . preg_quote($formId, '/') . '"/', $input,
                "A teleported is_active control is not wired to the form, so the browser will not submit it: {$input}"
            );
        }
    }

    /** The server half, so a fix to the markup is not mistaken for a fix here. */
    public function test_posting_the_toggle_off_deactivates_the_product(): void
    {
        $product = $this->product();

        $this->put(route('admin.products.update', $product), [
            'store_id' => 1, 'name' => 'Strap', 'price' => 1000, 'is_active' => 0,
        ]);

        $this->assertFalse((bool) $product->fresh()->is_active);
    }
}
