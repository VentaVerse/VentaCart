<?php

namespace Themes\Xelga\Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Themes\Xelga\Tests\XelgaTestHelpers;
use Themes\Xelga\Catalog\Catalog;
use Themes\Xelga\Catalog\CatalogLinter;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\StoreTemplates\StoreTemplates;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * "Start from a store": the eight shipped starting stores, applied to the
 * draft (never to the live store), with one-step undo. A store brings a
 * look and pages, never products.
 */
class XelgaStoreTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const STORES = ['gadgets', 'clothes', 'furniture', 'beauty', 'coffee', 'outdoor', 'music', 'parts'];

    private const HANDLES = ['settings', 'header', 'footer', 'home'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        StoreTemplates::flush();
        Store::firstOrCreate(['code' => 'demo'], ['name' => 'Demo Store']);
    }

    /** @param array<string, int>|null $versions */
    private function apply(string $slug, string $bring = 'all', ?array $versions = null, $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin())->postJson(route('admin.xelga.store-templates.apply', ['slug' => $slug]), [
            'bring' => $bring,
            'versions' => $versions ?? $this->versions(),
        ]);
    }

    private function undo(string $token, $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->admin())->postJson(route('admin.xelga.store-templates.undo'), ['token' => $token]);
    }

    /** The draft_version of every document a store replaces, as the editor would know them. @return array<string, int> */
    private function versions(): array
    {
        $out = [];
        foreach (self::HANDLES as $handle) {
            $out[$handle] = (int) (Document::where('handle', $handle)->value('draft_version') ?? 0);
        }

        return $out;
    }

    private function draft(string $handle): ?array
    {
        return Document::where('handle', $handle)->first()?->draft;
    }

    /** The rows exactly as stored. @return array<string, ?array> */
    private function rawRows(): array
    {
        $out = [];
        foreach (self::HANDLES as $handle) {
            $row = DB::table('xelga_documents')->where('handle', $handle)->first();
            $out[$handle] = $row === null ? null : [
                'draft' => $row->draft,
                'published' => $row->published,
                'published_version' => (int) $row->published_version,
                'draft_base_version' => $row->draft_base_version,
                'draft_updated_by' => $row->draft_updated_by,
                'draft_updated_at' => $row->draft_updated_at,
            ];
        }

        return $out;
    }

    /* --------------------------------------------------------------------
       The shipped stores
       -------------------------------------------------------------------- */

    public function test_the_eight_stores_ship_in_order_and_pass_the_linter(): void
    {
        $this->assertSame(self::STORES, StoreTemplates::slugs());
        $this->assertSame([], CatalogLinter::check());

        foreach (self::STORES as $slug) {
            $this->assertFileExists(StoreTemplates::dir($slug) . '/thumbnail.jpg');
            $this->assertNotSame('', StoreTemplates::get($slug)['line']);
        }
        $this->assertSame(
            ['Gadgets', 'Clothes', 'Furniture', 'Beauty', 'Coffee', 'Outdoor', 'Music', 'Parts'],
            array_column(StoreTemplates::summaries(), 'name'),
        );
    }

    public function test_every_store_saves_cleanly_as_a_merchant_draft(): void
    {
        // Core makes the main menu; a store usually adds a footer menu.
        \App\Models\NavMenu::firstOrCreate(['key' => 'footer'], ['name' => 'Footer menu']);
        foreach (self::STORES as $slug) {
            $response = $this->apply($slug)->assertOk();
            foreach (self::HANDLES as $handle) {
                $this->assertSame([], $response->json("data.adjusted.{$handle}"), "{$slug} {$handle} was corrected on save.");
            }
            $this->assertSame([], $response->json('data.warnings'), "{$slug} has a hard-to-read color scheme.");
        }
    }

    public function test_no_earlier_store_name_appears_anywhere_in_the_theme(): void
    {
        $root = Catalog::root();
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            fn (\SplFileInfo $file) => ! in_array($file->getFilename(), ['.git', 'node_modules'], true),
        ));
        // Split, so this file does not hold the names it looks for.
        $words = array_map(fn (array $parts) => implode('', $parts), [
            ['dra', 'gon'], ['sinu', 'lid'], ['si', 'lid'], ['ha', 'mog'], ['ta', 'la'],
            ['ami', 'han'], ['to', 'lex'], ['ben', 'ch'],
        ]);
        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }
            // Photos and fonts are bytes, not words; only their names are read.
            $binary = in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp', 'woff2', 'woff', 'ttf'], true);
            $text = $binary ? '' : (string) file_get_contents($file->getPathname());
            foreach ($words as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, substr($file->getPathname(), strlen($root)), 'A file is named after it.');
                $this->assertStringNotContainsStringIgnoringCase($word, $text, $file->getPathname());
            }
        }
    }

    public function test_a_fresh_install_starts_from_gadgets(): void
    {
        $boot = \Themes\Xelga\Http\BootPayload::build($this->admin());
        $settings = $boot['documents']['settings']['content'];

        $this->assertSame('gadgets', StoreTemplates::DEFAULT);
        $this->assertSame(['look' => 'gadgets', 'sections' => 'gadgets'], StoreTemplates::basedOn($settings));
        $this->assertSame(['look' => 'gadgets', 'sections' => 'gadgets'], (array) $settings['based_on']);
        $this->assertSame(self::STORES, array_column($boot['store_templates'], 'slug'));
        $this->assertSame('gadgets', $boot['store_templates'][0]['slug'], 'The picker lists the starting store first.');

        // The shipped defaults are the Gadgets store, with its photos in the media library.
        $gadgets = StoreTemplates::get('gadgets')['documents'];
        $this->assertEquals($gadgets['settings']['values'], $this->defaults('settings')['values']);
        $this->assertEquals($gadgets['settings']['schemes'], $this->defaults('settings')['schemes']);
        foreach (['header', 'footer', 'home'] as $handle) {
            $this->assertSame($gadgets[$handle]['order'], $this->defaults($handle)['order'], $handle);
        }
        $hero = $this->defaults('home')['sections']['hero-1']['blocks']['hero-1-b1']['settings'];
        $this->assertSame('media/xelga/gadgets/product-phone-pro-graphite.jpg', $hero['image']);
        $this->assertSame('media/xelga/gadgets/cat-phones.jpg', $hero['image_mobile'], 'Phones show the whole phone.');
        Storage::disk('public')->assertExists('media/xelga/gadgets/cat-phones.jpg');

        // An unknown look is read as the starting store.
        $this->assertSame(['look' => 'gadgets', 'sections' => 'gadgets'], StoreTemplates::basedOn(['based_on' => ['look' => 'no-such-store', 'sections' => 'no-such-store']]));

        $this->get('/')->assertOk()->assertSee('Phones, laptops and tablets, paid for at your door');
    }

    public function test_gadgets_fits_the_demo_catalog_and_saves_with_nothing_corrected(): void
    {
        \App\Models\NavMenu::firstOrCreate(['key' => 'footer'], ['name' => 'Footer menu']);
        // The records core's demo catalog seeds on a fresh install, by the same slugs.
        $categories = [];
        foreach (['Phones', 'Computers', 'Laptops', 'Monitors', 'Tablets', 'Accessories'] as $name) {
            $categories[$name] = Category::create(['store_id' => 1, 'name' => $name, 'slug' => strtolower($name), 'is_active' => true]);
        }
        $pro = Product::create(['store_id' => 1, 'name' => 'Aurora 15 Pro', 'slug' => 'aurora-15-pro', 'sku' => 'DEMO-001', 'price' => 1199, 'is_active' => true]);

        $response = $this->apply('gadgets')->assertOk();
        foreach (self::HANDLES as $handle) {
            $this->assertSame([], $response->json("data.adjusted.{$handle}"), "gadgets {$handle} was corrected on save.");
            $this->assertSame([], $response->json("data.missing.{$handle}") ?? [], "gadgets {$handle} names a record the demo catalog lacks.");
        }
        $this->assertSame([], $response->json('data.warnings'));
        $this->assertSame(1, Product::count(), 'A starting store brings no products.');

        // The merchant's store name shows, not the starting store's.
        $this->assertSame('', $this->draft('header')['sections']['header-1']['settings']['logo_text']);
        $this->assertSame('', $this->draft('footer')['sections']['footer-1']['settings']['legal']);

        // The spotlight, the tiles and the buttons point at the merchant's records.
        $home = $this->draft('home');
        $this->assertSame($pro->id, $home['sections']['spotlight-1']['settings']['product']);
        $this->assertSame($categories['Phones']->id, $home['sections']['categories-1']['blocks']['categories-1-b1']['settings']['category']);
        $this->assertSame(['type' => 'category', 'id' => $categories['Computers']->id], $home['sections']['desk-1']['settings']['button_link']);

        // Plain US spelling, and no claim the demo catalog cannot back.
        $text = (string) file_get_contents(StoreTemplates::dir('gadgets') . '/store.json');
        foreach (['colour', 'centre', 'aluminium', 'catalogue', 'since 2011', 'calibrated'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text);
        }
    }

    public function test_no_store_ships_sample_products_or_a_photo_it_does_not_use(): void
    {
        foreach (self::STORES as $slug) {
            $this->assertArrayNotHasKey('samples', StoreTemplates::get($slug), $slug);
        }

        $probe = StoreTemplates::dir('parts') . '/images/zz-probe-unused.jpg';
        copy(StoreTemplates::dir('parts') . '/thumbnail.jpg', $probe);
        try {
            $this->assertContains('store-templates/parts/images/zz-probe-unused.jpg is not used.', CatalogLinter::check());
        } finally {
            @unlink($probe);
        }
        $this->assertSame([], CatalogLinter::check());
    }

    /* --------------------------------------------------------------------
       Applying
       -------------------------------------------------------------------- */

    public function test_apply_fills_the_draft_only_and_the_live_store_is_unchanged(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Live heading'), 0)->assertOk();
        $this->publish($admin, ['home' => 1])->assertOk();
        $published = $this->rawRows()['home']['published'];
        $revisions = Revision::count();

        $response = $this->apply('coffee')->assertOk()->assertJsonPath('success', true);

        $this->assertSame(['look' => 'coffee', 'sections' => 'coffee'], $response->json('data.based_on'));
        $this->assertSame('sofia-condensed', $this->draft('settings')['values']['font_pair']);
        $this->assertSame('Bar wall', $this->draft('settings')['schemes'][0]['label']);
        $this->assertSame(StoreTemplates::get('coffee')['documents']['home']['order'], $this->draft('home')['order']);
        $this->assertNotNull($this->draft('header'));
        $this->assertNotNull($this->draft('footer'));

        // Nothing went live.
        $this->assertSame($published, $this->rawRows()['home']['published']);
        $this->assertSame(1, (int) Document::where('handle', 'home')->value('published_version'));
        $this->assertNull(Document::where('handle', 'settings')->value('published'));
        $this->assertSame($revisions, Revision::count());
        $this->asGuest();
        $this->get('/')->assertOk()->assertSee('Live heading')->assertDontSee('Highland coffee, roasted twice a week');
    }

    public function test_look_only_changes_only_the_look(): void
    {
        $admin = $this->admin();
        $settings = $this->defaults('settings');
        $settings['values'] += ['instagram_url' => 'https://example.com/demo-store', 'hours' => 'Mon to Sat, 9 AM to 6 PM', 'cart_type' => 'page', 'card_style' => 'panel'];
        $this->saveDraft($admin, 'settings', $settings, 0)->assertOk();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('My own heading'), 0)->assertOk();
        $before = $this->rawRows();

        $this->apply('beauty', 'look')->assertOk()
            ->assertJsonPath('data.based_on', ['look' => 'beauty', 'sections' => 'gadgets']);

        $values = $this->draft('settings')['values'];
        $beauty = StoreTemplates::get('beauty')['documents']['settings'];
        foreach (StoreTemplates::lookKeys() as $key) {
            $this->assertSame($beauty['values'][$key] ?? null, $values[$key] ?? null, "The look key {$key} is not the Beauty store's.");
        }
        $this->assertSame(array_column($beauty['schemes'], 'label'), array_column($this->draft('settings')['schemes'], 'label'));
        // The merchant's own settings stay.
        $this->assertSame('https://example.com/demo-store', $values['instagram_url']['href'] ?? $values['instagram_url']);
        $this->assertSame('Mon to Sat, 9 AM to 6 PM', $values['hours']);
        $this->assertSame('page', $values['cart_type']);
        // The sections are untouched.
        $after = $this->rawRows();
        foreach (['header', 'footer', 'home'] as $handle) {
            $this->assertSame($before[$handle], $after[$handle], "{$handle} changed.");
        }
        $this->assertSame('My own heading', $this->draft('home')['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading']);
    }

    public function test_a_stale_version_is_refused_with_409_and_nothing_changes(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'settings', $this->defaults('settings'), 0)->assertOk();
        $before = $this->rawRows();

        $this->apply('coffee', 'all', ['settings' => 0, 'header' => 0, 'footer' => 0, 'home' => 0])
            ->assertStatus(409)->assertJsonPath('code', 'conflict')->assertJsonPath('data.draft_version', 1);

        $this->assertSame($before, $this->rawRows());
        $this->assertSame([], Storage::disk('public')->allFiles('media/xelga/coffee'));
    }

    public function test_every_replaced_document_must_name_its_version(): void
    {
        $this->apply('coffee', 'all', ['settings' => 0])->assertStatus(422)->assertJsonPath('code', 'invalid');
        $this->apply('coffee', 'look', ['settings' => 0])->assertOk();
        $this->apply('no-such-store', 'look', ['settings' => 1])->assertNotFound();
        $this->actingAs($this->admin())->postJson(route('admin.xelga.store-templates.apply', ['slug' => 'coffee']), ['bring' => 'everything', 'versions' => ['settings' => 1]])
            ->assertStatus(422);
    }

    public function test_only_real_photo_files_are_copied(): void
    {
        $dir = StoreTemplates::dir('coffee') . '/images';
        $fake = "{$dir}/zz-probe-not-a-photo.jpg";
        $link = "{$dir}/zz-probe-link.png";
        file_put_contents($fake, '<?php echo "not a photo";');
        symlink("{$dir}/banner-cafe.jpg", $link);
        try {
            $this->apply('coffee')->assertOk();
        } finally {
            @unlink($fake);
            @unlink($link);
        }

        $disk = Storage::disk('public');
        $disk->assertExists('media/xelga/coffee/banner-cafe.jpg');
        $disk->assertMissing('media/xelga/coffee/zz-probe-not-a-photo.jpg');
        $disk->assertMissing('media/xelga/coffee/zz-probe-link.png');
    }

    public function test_photos_are_copied_once_and_reused(): void
    {
        $disk = Storage::disk('public');
        $this->apply('coffee')->assertOk();
        $photo = $this->draft('home')['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image'];
        $this->assertSame('media/xelga/coffee/slide-1-pourover.jpg', $photo);
        $disk->assertExists($photo);
        $count = count($disk->allFiles('media/xelga/coffee'));
        $this->assertSame(count(glob(StoreTemplates::dir('coffee') . '/images/*')), $count);

        // A merchant's file under the same name is never replaced.
        $disk->put($photo, 'the merchant\'s own photo');
        $this->apply('coffee', 'all', $this->versions())->assertOk();

        $this->assertSame('the merchant\'s own photo', $disk->get($photo));
        $this->assertSame($count, count($disk->allFiles('media/xelga/coffee')));
        $this->assertSame($photo, $this->draft('home')['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image']);
    }

    public function test_a_slug_the_store_lacks_leaves_the_section_empty(): void
    {
        $light = Category::create(['store_id' => 1, 'name' => 'Light roast', 'slug' => 'light-roast', 'is_active' => true]);

        $response = $this->apply('coffee')->assertOk();

        $tiles = $this->draft('home')['sections']['categories-1'];
        $this->assertSame($light->id, $tiles['blocks']['categories-1-b1']['settings']['category']);
        $this->assertNull($tiles['blocks']['categories-1-b2']['settings']['category'] ?? null);
        $this->assertSame([], $this->draft('home')['sections']['products-1']['settings']['products']);
        $missing = collect($response->json('data.missing.home'));
        $this->assertTrue($missing->contains(fn ($m) => $m['record'] === 'category' && $m['slug'] === 'medium-roast' && $m['block'] === 'categories-1-b2'));
        $this->assertFalse($missing->contains(fn ($m) => $m['slug'] === 'light-roast'));

        // The editor's preview keeps the sections in place, waiting for a
        // pick (the editor draws its empty state over them).
        $nonce = $this->openEditor($this->admin());
        $this->get('/?xelga_preview=' . $nonce)->assertOk()
            ->assertSee('Highland coffee, roasted twice a week')
            ->assertSee('data-block-id="categories-1-b2"', false)
            ->assertSee('data-section-id="products-1"', false);
    }

    /* --------------------------------------------------------------------
       Undo
       -------------------------------------------------------------------- */

    public function test_undo_puts_back_the_exact_drafts(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'settings', $this->defaults('settings'), 0)->assertOk();
        $this->saveDraft($admin, 'header', $this->defaults('header'), 0)->assertOk();
        $this->publish($admin, ['settings' => 1, 'header' => 1])->assertOk();
        $settings = $this->defaults('settings');
        $settings['values']['button_radius'] = 12;
        $this->saveDraft($admin, 'settings', $settings, 2)->assertOk();
        $before = $this->rawRows();
        $this->assertNull($before['home']);
        $this->assertNull($before['header']['draft']);

        $token = $this->apply('parts')->assertOk()->json('data.undo');
        $this->assertNotNull($this->draft('home'));

        $response = $this->undo($token)->assertOk()->assertJsonPath('data.store', 'parts');

        $after = $this->rawRows();
        $this->assertSame($before['settings'], $after['settings']);
        $this->assertSame($before['header'], $after['header']);
        $this->assertSame($before['footer'], $after['footer']);
        $this->assertNull($after['home'], 'The home page the apply made is gone again.');
        // Versions only move forward, so an editor holding the apply's versions is told.
        $this->assertSame((int) Document::where('handle', 'settings')->value('draft_version'), $response->json('data.documents.settings.draft_version'));
        $this->assertSame(0, $response->json('data.documents.home.draft_version'));

        // Undo works once.
        $this->undo($token)->assertNotFound();
    }

    public function test_each_admin_has_one_way_back_and_a_new_apply_replaces_it(): void
    {
        $admin = $this->admin();
        $first = $this->apply('coffee', 'look')->assertOk()->json('data.undo');
        $second = $this->apply('furniture', 'look')->assertOk()->json('data.undo');

        $saved = Cache::get('xelga:store-undo:user:' . $admin->id);
        $this->assertSame($second, $saved['token']);
        $this->assertNull(Cache::get('xelga:store-undo:' . $first));
        $this->assertNull(Cache::get('xelga:store-undo:' . $second));

        // The first apply's way back is gone; a made-up token of the right shape is refused too.
        $this->undo($first)->assertNotFound();
        $this->undo(str_repeat('a', 40))->assertNotFound();
        $this->assertSame('furniture', $this->draft('settings')['based_on']['look']);

        $this->undo($second)->assertOk();
        $this->assertSame('coffee', $this->draft('settings')['based_on']['look']);
        $this->assertNull(Cache::get('xelga:store-undo:user:' . $admin->id));
    }

    public function test_undo_checks_the_drafts_it_puts_back(): void
    {
        $admin = $this->admin();
        $this->saveDraft($admin, 'home', $this->homeWithHeading('Before'), 0)->assertOk();
        $token = $this->apply('coffee')->assertOk()->json('data.undo');
        $key = 'xelga:store-undo:user:' . $admin->id;
        $saved = Cache::get($key);

        // A value that does not fit its setting is corrected, as a save would.
        $tampered = $saved;
        $home = json_decode($tampered['before']['home']['draft'], true);
        $home['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading'] = ['not' => 'a string'];
        $tampered['before']['home']['draft'] = json_encode($home);
        Cache::put($key, $tampered, 600);
        $this->undo($token)->assertOk();
        $heading = $this->draft('home')['sections']['hero-1']['blocks']['hero-1-b1']['settings']['heading'];
        $this->assertIsString($heading);
        // Author and base version are put back as they were.
        $row = DB::table('xelga_documents')->where('handle', 'home')->first();
        $this->assertSame((int) $saved['before']['home']['draft_updated_by'], (int) $row->draft_updated_by);
        $this->assertSame($saved['before']['home']['draft_base_version'], $row->draft_base_version === null ? null : (int) $row->draft_base_version);

        // A structure no save would take is refused, and nothing is put back.
        $token = $this->apply('coffee', 'all', $this->versions())->assertOk()->json('data.undo');
        $saved = Cache::get($key);
        $home = json_decode($saved['before']['home']['draft'], true);
        $home['order'][] = 'zz-1';
        $home['sections']['zz-1'] = ['type' => '"><img src=x onerror=alert(1)>', 'settings' => [], 'blocks' => [], 'block_order' => []];
        $saved['before']['home']['draft'] = json_encode($home);
        Cache::put($key, $saved, 600);
        $applied = $this->rawRows();
        $this->undo($token)->assertStatus(422)->assertJsonPath('code', 'invalid');
        $this->assertSame($applied, $this->rawRows());
    }

    public function test_two_applies_meeting_in_the_database_locks_answer_409(): void
    {
        // The second read of the drafts is the locked one inside the transaction; it loses the race.
        $reads = 0;
        DB::listen(function ($query) use (&$reads) {
            if (str_starts_with($query->sql, 'select * from "xelga_documents" where "handle" in') && ++$reads === 2) {
                throw new QueryException('mysql', $query->sql, [], new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'));
            }
        });

        $this->apply('coffee')
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict')
            ->assertJsonPath('message', 'Someone else changed this just now. Try again.');
        $this->assertSame(2, $reads);
        $this->assertSame(0, Document::count());
        $this->assertNull(Cache::get('xelga:store-undo:user:' . $this->admin()->id));
    }

    public function test_undo_is_refused_after_a_later_change_and_for_someone_else(): void
    {
        $token = $this->apply('furniture', 'look')->assertOk()->json('data.undo');
        $this->undo($token, $this->themeEditor())->assertNotFound();

        $this->saveDraft($this->admin(), 'settings', $this->draft('settings'), (int) Document::where('handle', 'settings')->value('draft_version'))->assertOk();
        $this->undo($token)->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->assertSame('furniture', $this->draft('settings')['based_on']['look']);
    }

    /* --------------------------------------------------------------------
       Access
       -------------------------------------------------------------------- */

    public function test_the_theme_permission_is_required(): void
    {
        $token = $this->apply('coffee', 'look')->assertOk()->json('data.undo');
        $staff = $this->staff();

        $this->apply('furniture', 'look', null, $staff)->assertForbidden();
        $this->undo($token, $staff)->assertForbidden();
        $this->assertSame('coffee', $this->draft('settings')['based_on']['look']);

        $this->apply('furniture', 'look', null, $this->themeEditor())->assertOk();
    }

    public function test_the_csrf_token_is_required(): void
    {
        $token = $this->apply('coffee', 'look')->assertOk()->json('data.undo');
        $versions = $this->versions();

        // Tests skip the CSRF check unless the app runs outside the testing environment.
        $env = $this->app['env'];
        $this->app['env'] = 'local';
        try {
            $this->apply('furniture', 'look', $versions)->assertStatus(419);
            $this->undo($token)->assertStatus(419);
        } finally {
            $this->app['env'] = $env;
        }

        $this->assertSame('coffee', $this->draft('settings')['based_on']['look']);
    }
}
