<?php

namespace Themes\Xelga\Tests\Feature;

use App\Support\Media\MediaMover;
use App\Support\Media\MediaReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\Revision;
use Themes\Xelga\Rendering\DocumentRepository;
use Themes\Xelga\Rendering\Storefront;
use Themes\Xelga\Tests\XelgaTestHelpers;

require_once __DIR__ . '/../XelgaTestHelpers.php';

/**
 * Theme images and the store's media library: an image a theme page or the
 * theme settings show is reported as used, so it cannot be deleted from
 * under the storefront, and moving it in the library points the theme's
 * pages, drafts and history at its new place.
 */
class XelgaMediaReferencesTest extends TestCase
{
    use RefreshDatabase;
    use XelgaTestHelpers;

    private const PHOTO = 'media/banners/demo-001.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installXelga();
        Storage::disk('public')->put(self::PHOTO, 'demo image');
        Storage::disk('public')->makeDirectory('media/banners/sale');
    }

    /** The shipped home page with the first banner photo set to $path. */
    private function homeWithPhoto(string $path, string $heading = 'Demo heading'): array
    {
        $home = $this->homeWithHeading($heading);
        $home['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image'] = $path;

        return $home;
    }

    private function publishHome(array $content): void
    {
        $admin = $this->admin();
        $version = (int) (Document::where('handle', 'home')->value('draft_version') ?? 0);
        $this->saveDraft($admin, 'home', $content, $version)->assertOk();
        $this->publish($admin, ['home' => $version + 1])->assertOk();
    }

    private function homeDraft(array $content): void
    {
        $version = (int) Document::where('handle', 'home')->value('draft_version');
        $this->saveDraft($this->admin(), 'home', $content, $version)->assertOk();
    }

    /** Every string anywhere in a decoded document. */
    private function strings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value) ? array_merge([], ...array_map(fn ($v) => $this->strings($v), array_values($value))) : [];
    }

    public function test_an_image_on_a_published_theme_page_counts_as_used(): void
    {
        $this->assertSame([], MediaReferences::usage([self::PHOTO])[self::PHOTO]);

        $this->publishHome($this->homeWithPhoto(self::PHOTO));

        $this->assertSame(['theme pages' => 1], MediaReferences::usage([self::PHOTO])[self::PHOTO]);
        $this->assertSame(1, MediaReferences::inUse([self::PHOTO])[self::PHOTO]);
    }

    public function test_an_image_in_theme_settings_counts_as_used(): void
    {
        $settings = $this->defaults(Handles::SETTINGS);
        $settings['values']['background_photo'] = self::PHOTO;
        $document = Document::firstOrNew(['handle' => Handles::SETTINGS]);
        $document->kind = Handles::kind(Handles::SETTINGS);
        $document->published = $settings;
        $document->save();

        $this->assertSame(['theme pages' => 1], MediaReferences::usage([self::PHOTO])[self::PHOTO]);
    }

    public function test_an_image_only_in_a_draft_counts_as_used_by_theme_drafts(): void
    {
        $this->homeDraft($this->homeWithPhoto(self::PHOTO));

        $this->assertSame(['theme drafts' => 1], MediaReferences::usage([self::PHOTO])[self::PHOTO]);
    }

    public function test_an_image_only_in_theme_history_does_not_count_as_used(): void
    {
        $this->publishHome($this->homeWithPhoto(self::PHOTO));
        $this->publishHome($this->homeWithPhoto(''));

        $this->assertTrue(Revision::query()->get()->contains(fn (Revision $r) => in_array(self::PHOTO, $this->strings($r->content), true)));
        $this->assertSame([], MediaReferences::usage([self::PHOTO])[self::PHOTO]);
    }

    public function test_moving_an_image_repoints_theme_pages_drafts_and_history(): void
    {
        $moved = 'media/banners/sale/demo-001.jpg';
        $this->publishHome($this->homeWithPhoto(self::PHOTO));
        $this->homeDraft($this->homeWithPhoto(self::PHOTO, 'Draft heading'));

        $result = MediaMover::move([self::PHOTO], 'media/banners/sale');

        $this->assertSame([self::PHOTO => $moved], $result['moved']);
        $this->assertSame(1, $result['updated']['theme pages']);
        $this->assertSame(1, $result['updated']['theme drafts']);
        $this->assertGreaterThanOrEqual(1, $result['updated']['theme history']);

        $home = Document::where('handle', 'home')->firstOrFail();
        $this->assertSame($moved, $home->published['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image']);
        $this->assertSame($moved, $home->draft['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image']);
        foreach (Revision::query()->get() as $revision) {
            $this->assertNotContains(self::PHOTO, $this->strings($revision->content));
        }
        $this->assertTrue(Revision::query()->get()->contains(fn (Revision $r) => in_array($moved, $this->strings($r->content), true)));
        $this->assertSame(['theme pages' => 1, 'theme drafts' => 1], MediaReferences::usage([$moved])[$moved]);
    }

    public function test_a_moved_image_shows_its_new_path_on_the_next_render(): void
    {
        $moved = 'media/banners/summer-sale.jpg';
        $this->publishHome($this->homeWithPhoto(self::PHOTO));
        $this->asGuest();
        Storefront::forgetRequestState();

        $this->get('/')->assertOk()->assertSee('/storage/' . self::PHOTO, false);

        MediaMover::rename(self::PHOTO, 'summer-sale');

        // A page drawn in the same process after the move (a queued job, a
        // long-running worker) reads the moved path, not the document the
        // last page kept.
        $content = app(DocumentRepository::class)->content('home');
        $this->assertSame($moved, $content['sections']['hero-1']['blocks']['hero-1-b1']['settings']['image']);

        $this->get('/')->assertOk()
            ->assertSee('/storage/' . $moved, false)
            ->assertDontSee('/storage/' . self::PHOTO, false);
    }

    public function test_the_editor_page_includes_the_media_library(): void
    {
        $html = $this->actingAs($this->admin())->get(route('admin.xelga.editor'))->assertOk()->getContent();

        $this->assertStringContainsString('id="media-library-config"', $html);
        $head = substr($html, 0, (int) strpos($html, '</head>'));
        $this->assertStringContainsString('id="media-library-config"', $head);

        $boot = json_decode(html_entity_decode((string) preg_replace('#^.*<script type="application/json" id="xelga-boot">(.*?)</script>.*$#s', '$1', $html)), true);
        $this->assertIsArray($boot);
        $this->assertArrayNotHasKey('media', $boot);
        $this->assertArrayNotHasKey('media_browse', $boot['routes']);
        $this->assertArrayNotHasKey('media_upload', $boot['routes']);
        $this->assertArrayNotHasKey('media_view', $boot['user']['can']);
        $this->assertArrayNotHasKey('media_upload', $boot['user']['can']);
    }
}
