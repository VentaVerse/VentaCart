<?php

namespace Themes\Xelga\Tests;

use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Extensions\Registrar;
use Themes\Xelga\Rendering\Storefront;

/**
 * Xelga with the fixture extension (tests/fixtures/extensions/sample-extension)
 * installed: a product to look at, documents to store, and pages to open.
 * Use beside XelgaTestHelpers and Tests\Support\UsesSampleExtension.
 */
trait XelgaExtensionHelpers
{
    protected Store $store;

    protected function setUpXelgaWithExtension(bool $install = true): void
    {
        $this->installXelga();
        Registrar::forgetLogged();
        if ($install) {
            $this->installSampleExtension();
        }
        $this->store = Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
        CustomerGroup::create(['store_id' => $this->store->id, 'name' => 'Retail', 'is_default' => true]);
        // Core's visibility scope steps aside for console runs; these tests are storefront requests.
        (new \ReflectionProperty(Application::class, 'isRunningInConsole'))->setValue($this->app, false);
        // Out of the console the token check runs again; these tests call the endpoints as the editor does.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->forgetXelgaState();
    }

    /** What a new request would start from: no memo of the catalog, the documents or the registered parts. */
    protected function forgetXelgaState(): void
    {
        Storefront::forgetRequestState();
        \Themes\Xelga\Catalog\Catalog::flush();
        Defaults::flush();
        // Outside debug the compiled file is keyed by versions only, and a
        // test changes files without changing a version.
        @unlink(app()->bootstrapPath('cache/xelga-catalog.php'));
    }

    protected function product(string $name = 'Linen Shirt'): Product
    {
        return Product::create([
            'store_id' => $this->store->id, 'name' => $name, 'slug' => Str::slug($name),
            'sku' => 'DEMO-' . Str::upper(Str::random(6)), 'price' => 1000, 'quantity' => 20,
            'track_stock' => true, 'is_active' => true,
        ]);
    }

    protected function page(string $url): TestResponse
    {
        $this->forgetXelgaState();

        return $this->get($url)->assertOk();
    }

    /** A sample card section as the editor stores one. */
    protected function card(array $settings = [], bool $disabled = false): array
    {
        return [
            'type' => 'sample-extension.card', 'label' => 'Seller', 'disabled' => $disabled,
            'settings' => $settings + ['heading' => 'Sold by Demo Store', 'show_rating' => false, 'size' => 70],
            'block_order' => ['card-line'],
            'blocks' => ['card-line' => ['type' => 'line', 'label' => 'Line', 'disabled' => false, 'settings' => ['text' => 'Ships tomorrow']]],
        ];
    }

    /** The shipped product template with these sections added after product-main. */
    protected function productWith(array $sections): array
    {
        $content = Defaults::content('product');
        $order = $content['order'];
        $at = array_search('product-main', array_map(fn ($id) => $content['sections'][$id]['type'] ?? null, $order), true);
        array_splice($order, $at === false ? count($order) : $at + 1, 0, array_keys($sections));
        $content['order'] = $order;
        $content['sections'] = [...$content['sections'], ...$sections];

        return $content;
    }

    /** Store a document as published, written straight to its row. */
    protected function publishedDocument(string $handle, array $content, ?array $draft = null): Document
    {
        $row = Document::where('handle', $handle)->first() ?? new Document([
            'handle' => $handle,
            'kind' => \Themes\Xelga\Documents\Handles::kind($handle),
            'page_type' => \Themes\Xelga\Documents\Handles::pageType($handle),
        ]);
        $row->forceFill(['published' => $content, 'published_version' => max(1, (int) $row->published_version), 'draft' => $draft])->save();

        return $row->fresh();
    }

    /** @return array<string, mixed> */
    protected function storedSection(string $handle, string $id, string $column = 'published'): ?array
    {
        $content = Document::where('handle', $handle)->value($column);
        $content = is_string($content) ? json_decode($content, true) : $content;

        return $content['sections'][$id] ?? null;
    }
}
