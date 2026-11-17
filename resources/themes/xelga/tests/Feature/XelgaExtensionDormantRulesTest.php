<?php

namespace Themes\Xelga\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleExtension;
use Tests\TestCase;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Tests\XelgaExtensionHelpers;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';
require_once __DIR__ . '/../XelgaExtensionHelpers.php';

/**
 * While an extension is away its sections and blocks can be kept, never
 * added: nothing can check where they go. Whatever a stored document
 * holds, an extension section or block is drawn only where its schema
 * allows it, within its limits.
 */
class XelgaExtensionDormantRulesTest extends TestCase
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

    /** A document with these sections added at the end. */
    private function with(string $handle, array $sections, ?array $content = null): array
    {
        $content ??= Defaults::content($handle);
        $content['order'] = [...$content['order'], ...array_keys($sections)];
        $content['sections'] = [...$content['sections'], ...$sections];

        return $content;
    }

    /** The shipped product template with seller blocks after the title. */
    private function withSellers(array $ids): array
    {
        $content = Defaults::content('product');
        $order = $content['sections'][self::MAIN]['block_order'];
        array_splice($order, array_search('product-main-1-title', $order, true) + 1, 0, $ids);
        $content['sections'][self::MAIN]['block_order'] = $order;
        foreach ($ids as $id) {
            $content['sections'][self::MAIN]['blocks'][$id] = ['type' => 'sample-extension.seller', 'label' => 'Seller', 'disabled' => false, 'settings' => ['label' => "Sold by {$id}"]];
        }

        return $content;
    }

    private function save(string $handle, array $content, int $version = 0)
    {
        $this->forgetXelgaState();

        return $this->saveDraft($this->themeEditor(), $handle, $content, $version);
    }

    public function test_a_new_dormant_section_is_refused(): void
    {
        $this->switchOffSampleExtension();

        $response = $this->save('product', $this->productWith(['card-1' => $this->card()]))->assertStatus(422);

        $this->assertStringContainsString("This section's extension is not available.", $response->getContent());
    }

    public function test_a_section_of_an_extension_the_store_never_had_is_refused(): void
    {
        $planted = ['type' => 'marketplace.seller-card', 'label' => 'Seller', 'disabled' => false, 'settings' => [], 'block_order' => [], 'blocks' => []];

        $this->save('product', $this->productWith(['planted-1' => $planted]))->assertStatus(422);
        $this->assertNull(Document::where('handle', 'product')->first());
    }

    public function test_a_stored_dormant_section_stays_but_gets_no_new_blocks(): void
    {
        $this->publishedDocument('product', $this->productWith(['card-1' => $this->card()]));
        $this->switchOffSampleExtension();

        $content = $this->productWith(['card-1' => $this->card(['heading' => 'Changed while away is kept as sent'])]);
        $this->save('product', $content)->assertOk();

        $content['sections']['card-1']['block_order'][] = 'card-line-2';
        $content['sections']['card-1']['blocks']['card-line-2'] = ['type' => 'line', 'label' => 'Line', 'disabled' => false, 'settings' => ['text' => 'Planted']];
        $this->save('product', $content, 1)->assertStatus(422);

        $retyped = $this->productWith(['card-1' => ['type' => 'sample-extension.other'] + $this->card()]);
        $this->save('product', $retyped, 1)->assertStatus(422);
    }

    public function test_extension_sections_are_refused_in_the_header_and_footer(): void
    {
        foreach (['header', 'footer'] as $handle) {
            $this->save($handle, $this->with($handle, ['card-x' => $this->card()]))->assertStatus(422);
        }

        // Also while the extension is away, and also when a stored header already has one.
        $this->publishedDocument('header', $this->with('header', ['card-x' => $this->card()]));
        $this->switchOffSampleExtension();
        $this->save('header', $this->with('header', ['card-x' => $this->card()]))->assertStatus(422);
    }

    public function test_a_new_dormant_block_is_refused_and_a_stored_one_stays(): void
    {
        $this->switchOffSampleExtension();
        $this->save('product', $this->withSellers(['seller-1']))->assertStatus(422);

        $this->switchOnSampleExtension();
        $this->publishedDocument('product', $this->withSellers(['seller-1']));
        $this->switchOffSampleExtension();
        $this->save('product', $this->withSellers(['seller-1']))->assertOk();
        $this->save('product', $this->withSellers(['seller-1', 'seller-2']), 1)->assertStatus(422);
    }

    public function test_a_revision_restore_may_bring_back_its_dormant_sections(): void
    {
        $saved = $this->save('product', $this->productWith(['card-1' => $this->card()]))->assertOk()->json('data.document.draft_version');
        $this->forgetXelgaState();
        $this->publish($this->admin(), ['product' => (int) $saved])->assertOk();
        $this->save('product', Defaults::content('product'), (int) $saved + 1)->assertOk();
        $this->forgetXelgaState();
        $draft = Document::where('handle', 'product')->first();
        $this->publish($this->admin(), ['product' => (int) $draft->draft_version])->assertOk();
        $this->switchOffSampleExtension();
        $this->forgetXelgaState();

        $revision = \Themes\Xelga\Documents\Revision::where('document_id', $draft->id)->where('version', 1)->firstOrFail();
        $base = (int) Document::where('handle', 'product')->value('draft_version');
        app(\Themes\Xelga\Documents\Publisher::class)->restore($revision, $base, $this->admin());

        $this->assertSame('sample-extension.card', $this->storedSection('product', 'card-1', 'draft')['type']);
    }

    public function test_an_extension_section_stored_in_the_header_is_not_drawn(): void
    {
        $this->publishedDocument('header', $this->with('header', ['card-x' => $this->card(['heading' => 'Planted in header'])]));

        $this->page('/')->assertDontSee('Planted in header');
        $this->page('/product/linen-shirt')->assertDontSee('Planted in header');
    }

    public function test_an_extension_section_stored_where_its_schema_does_not_allow_it_is_not_drawn(): void
    {
        $home = $this->with('home', [
            'main-x' => ['type' => 'sample-extension.main', 'label' => 'Main', 'disabled' => false, 'settings' => ['show_name' => true], 'block_order' => [], 'blocks' => []],
            'card-x' => $this->card(['heading' => 'Planted on home']),
        ]);
        $this->publishedDocument('home', $home);

        $this->page('/')->assertDontSee('data-sample-main', false)->assertDontSee('Planted on home');
    }

    public function test_extension_sections_and_blocks_past_their_limits_are_not_drawn(): void
    {
        $content = $this->productWith(['card-1' => $this->card(['heading' => 'First card']), 'card-2' => $this->card(['heading' => 'Second card'])]);
        $sellers = $this->withSellers(['seller-1', 'seller-2', 'seller-3']);
        $content['sections'][self::MAIN] = $sellers['sections'][self::MAIN];
        $this->publishedDocument('product', $content);

        $html = $this->page('/product/linen-shirt')->assertSee('First card')->assertDontSee('Second card')->getContent();

        $this->assertSame(1, substr_count($html, 'data-sample-seller'), 'One seller block: its type allows one.');
        $this->assertStringContainsString('Sold by seller-1', $html);
    }
}
