<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\StoreTemplates\StoreTemplates;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * A page's blocks leave it only when the merchant removes them. Every way
 * the server writes or answers a page (opening it, saving the draft,
 * copying a section, publishing, restoring a revision, applying and undoing
 * a starting store, making a special layout from it) keeps each block of
 * the product information section, the Add to cart block among them.
 */
class XelgaBlocksKeptTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        StoreTemplates::flush();
        Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    public function test_every_write_path_keeps_the_product_information_blocks(): void
    {
        $admin = $this->admin();
        $shipped = $this->mainBlocks(Defaults::shipped('product'));
        $this->assertCount(12, $shipped);
        $this->assertContains('quantity-and-buy', array_values($shipped));

        // Opening the page: the editor gets every block the shipped page has.
        $opened = $this->actingAs($admin)->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))
            ->assertOk()->json('data.content');
        $this->assertSame($shipped, $this->mainBlocks($opened));

        // A settings change, saved as the editor sends it.
        $opened['sections']['product-main-1']['settings']['zoom'] = 'hover';
        $saved = $this->saveDraft($admin, 'product', $opened, 0)->assertOk()->json('data.document');
        $this->assertSame($shipped, $this->mainBlocks($saved['content']));
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->draft));

        // A copy of another section, made on the server.
        $this->actingAs($admin)->postJson(route('admin.xelga.documents.duplicate', ['handle' => 'product']), [
            'section' => 'related-1', 'block' => null, 'draft_version' => 1,
        ])->assertOk();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->draft));

        // Publishing every page with a draft at once.
        $this->publish($admin, ['product' => 2])->assertOk();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->published));
        $first = Revision::where('document_id', $this->row('product')->id)->orderBy('version')->firstOrFail();
        $this->assertSame($shipped, $this->mainBlocks($first->content));

        // A starting store with its home page, then undone: the product page is not one it brings.
        $versions = Document::query()->pluck('draft_version', 'handle')->all();
        $apply = $this->actingAs($admin)->postJson(route('admin.xelga.store-templates.apply', ['slug' => StoreTemplates::DEFAULT]), [
            'bring' => 'all',
            'versions' => array_map('intval', array_intersect_key($versions, array_flip(['settings', 'header', 'footer', 'home'])) + array_fill_keys(['settings', 'header', 'footer', 'home'], 0)),
        ])->assertOk();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->published));
        $this->publish($admin, array_map('intval', Document::query()->whereNotNull('draft')->pluck('draft_version', 'handle')->all()))->assertOk();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->published));
        $this->actingAs($admin)->postJson(route('admin.xelga.store-templates.undo'), ['token' => $apply->json('data.undo')]);
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->published));

        // Restoring the first revision into the draft.
        $this->actingAs($admin)->postJson(route('admin.xelga.revisions.restore', ['handle' => 'product', 'revision' => $first->id]), [
            'draft_version' => (int) $this->row('product')->draft_version,
        ])->assertOk();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product')->draft));

        // A special layout made from the page.
        $this->actingAs($admin)->postJson(route('admin.xelga.templates.store'), ['page_type' => 'product', 'label' => 'Gift guide'])
            ->assertCreated();
        $this->assertSame($shipped, $this->mainBlocks($this->row('product.gift-guide')->draft));
    }

    private function row(string $handle): Document
    {
        return Document::where('handle', $handle)->firstOrFail();
    }

    /** @return array<string, string> block id => type, in order */
    private function mainBlocks(mixed $content): array
    {
        $content = json_decode(json_encode($content), true);
        foreach ((array) ($content['sections'] ?? []) as $section) {
            if (($section['type'] ?? null) === 'product-main') {
                $out = [];
                foreach ((array) ($section['block_order'] ?? []) as $id) {
                    $out[$id] = (string) ($section['blocks'][$id]['type'] ?? '');
                }

                return $out;
            }
        }

        return [];
    }
}
