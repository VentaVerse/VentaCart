<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * An extension's block joins a theme section that takes extension blocks
 * (product information does) and that the block names in its targets.
 * It is kept, checked and drawn like the theme's own blocks.
 */
class XelgaExtensionBlocksTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleExtension;
    use XelgaExtensionHelpers;
    use XelgaTestHelpers;

    private const MAIN = 'product-main-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpXelgaWithExtension();
        $this->product('Linen Shirt');
    }

    private function seller(array $settings = []): array
    {
        return ['type' => 'sample-extension.seller', 'label' => 'Seller', 'disabled' => false, 'settings' => $settings + ['label' => 'Sold by']];
    }

    /** The shipped product template with the seller block placed after the block with this id. */
    private function withSeller(string $after = 'product-main-1-title', array $settings = []): array
    {
        $content = Defaults::content('product');
        $order = $content['sections'][self::MAIN]['block_order'];
        array_splice($order, array_search($after, $order, true) + 1, 0, ['seller-1']);
        $content['sections'][self::MAIN]['block_order'] = $order;
        $content['sections'][self::MAIN]['blocks']['seller-1'] = $this->seller($settings);

        return $content;
    }

    private function saveAndPublish(array $content, int $version = 0): int
    {
        $this->forgetXelgaState();
        $saved = $this->saveDraft($this->admin(), 'product', $content, $version)->assertOk()->json('data.document.draft_version');
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['product' => (int) $saved])->assertOk();

        return (int) $saved;
    }

    public function test_product_information_takes_the_block_and_other_sections_do_not(): void
    {
        $main = Catalog::section('product-main');
        $this->assertSame('sample-extension', $main->blockType('sample-extension.seller')['source'] ?? null);
        $this->assertSame(13, $main->blockMax(), 'Room for the extension block on top of the theme\'s twelve.');
        $this->assertNull(Catalog::section('product-details')->blockType('sample-extension.seller'));
        $this->assertNull(Catalog::section('hero')->blockType('sample-extension.seller'));
        $this->assertSame([], CatalogLinter::checkExtension('sample-extension'));
    }

    public function test_an_added_block_is_kept_where_it_was_dragged_and_drawn_there(): void
    {
        $this->saveAndPublish($this->withSeller('product-main-1-title', ['label' => 'Made by']));

        $stored = $this->storedSection('product', self::MAIN);
        $this->assertSame('sample-extension', $stored['blocks']['seller-1']['source']);
        $order = $stored['block_order'];
        $this->assertSame('seller-1', $order[array_search('product-main-1-title', $order, true) + 1]);

        $html = $this->page('/product/linen-shirt')
            ->assertSee('class="xblock b-sample-extension--seller" data-block-id="seller-1" data-block-type="sample-extension.seller"', false)
            ->assertSee('Made by Demo Store, for Linen Shirt')
            ->getContent();
        $title = strpos($html, 'class="pinfo__title"');
        $seller = strpos($html, 'data-sample-seller');
        $price = strpos($html, 'data-block-type="price"');
        $this->assertTrue($title < $seller && $seller < $price, 'Drawn where the merchant put it.');

        // Dragged to the end of the list: the new order is kept.
        $content = $this->withSeller('product-main-1-share');
        $this->saveAndPublish($content, 2);
        $this->assertSame('seller-1', last($this->storedSection('product', self::MAIN)['block_order']));
    }

    public function test_a_removed_block_is_gone(): void
    {
        $this->saveAndPublish($this->withSeller());

        // As the editor removes it: from what it loaded, which records the placements given.
        $this->forgetXelgaState();
        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))->json('data');
        $content = json_decode(json_encode($state['content']), true);
        $content['sections'][self::MAIN]['block_order'] = array_values(array_diff($content['sections'][self::MAIN]['block_order'], ['seller-1']));
        unset($content['sections'][self::MAIN]['blocks']['seller-1']);
        $this->saveAndPublish($content, (int) $state['draft_version']);

        $this->assertArrayNotHasKey('seller-1', $this->storedSection('product', self::MAIN)['blocks']);
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-seller', false);
    }

    public function test_switched_off_the_block_is_kept_across_save_and_publish_and_drawn_for_nobody(): void
    {
        $this->publishedDocument('product', $this->withSeller('product-main-1-title', ['label' => 'Made by']));
        $this->switchOffSampleExtension();

        $state = $this->actingAs($this->admin())->getJson(route('admin.xelga.documents.show', ['handle' => 'product']))->json('data');
        $this->saveAndPublish($state['content'], (int) $state['draft_version']);

        $stored = $this->storedSection('product', self::MAIN);
        $this->assertSame(['label' => 'Made by'], $stored['blocks']['seller-1']['settings']);
        $this->assertTrue($stored['blocks']['seller-1']['unchecked']);
        $this->assertSame('seller-1', $stored['block_order'][array_search('product-main-1-title', $stored['block_order'], true) + 1]);
        $this->page('/product/linen-shirt')->assertDontSee('data-sample-seller', false)->assertSee('class="pinfo__title"', false);

        $this->switchOnSampleExtension();
        $this->page('/product/linen-shirt')->assertSee('Made by Demo Store, for Linen Shirt');
    }

    public function test_values_stored_unchecked_are_cleaned_before_the_view(): void
    {
        $content = $this->withSeller('product-main-1-title', ['label' => str_repeat('L', 200), 'link' => ['type' => 'url', 'href' => 'javascript:alert(1)']]);
        $content['sections'][self::MAIN]['blocks']['seller-1']['unchecked'] = true;
        $this->publishedDocument('product', $content);

        $html = $this->page('/product/linen-shirt')->assertSee('data-sample-seller', false)->getContent();

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString(str_repeat('L', 41), $html);
    }

    public function test_the_block_cannot_join_a_section_that_does_not_take_it(): void
    {
        $content = Defaults::content('product');
        $content['sections']['product-details-1']['block_order'][] = 'seller-1';
        $content['sections']['product-details-1']['blocks']['seller-1'] = $this->seller();
        $this->forgetXelgaState();

        $this->saveDraft($this->admin(), 'product', $content, 0)->assertStatus(422)
            ->assertJsonPath('errors', fn ($errors) => isset($errors['content.sections.product-details-1.blocks.seller-1.type']));
    }

    public function test_a_block_whose_targets_leave_a_section_out_cannot_join_it(): void
    {
        $file = base_path('tests/fixtures/extensions/sample-extension/resources/xelga/schemas/blocks/sample-extension.badge.json');
        file_put_contents($file, json_encode(['type' => 'sample-extension.badge', 'label' => 'Badge', 'targets' => ['product-details'], 'settings' => []]));
        try {
            $this->forgetXelgaState();
            $this->assertNull(Catalog::section('product-main')->blockType('sample-extension.badge'));
            $this->assertNotNull(Catalog::all()['blocks']['sample-extension.badge'] ?? null);
            $this->assertContains('sample-extension: block sample-extension.badge: targets product-details, which does not take extension blocks.', CatalogLinter::checkExtension('sample-extension'));

            $content = Defaults::content('product');
            $content['sections'][self::MAIN]['block_order'][] = 'badge-1';
            $content['sections'][self::MAIN]['blocks']['badge-1'] = ['type' => 'sample-extension.badge', 'label' => 'Badge', 'disabled' => false, 'settings' => []];
            $this->forgetXelgaState();
            $this->saveDraft($this->admin(), 'product', $content, 0)->assertStatus(422);
        } finally {
            unlink($file);
            $this->forgetXelgaState();
        }
    }
}
