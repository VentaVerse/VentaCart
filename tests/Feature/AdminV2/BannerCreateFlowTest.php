<?php

namespace Tests\Feature\AdminV2;

use App\Models\User;
use App\Services\ExtensionManager;
use Extensions\Banners\Models\BannerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Making a banner used to take four pages in a detached browser tab.
 *
 * The layout editor's link said "New banner"; the page said "New banner group",
 * had no image field, and asked for a URL slug and an autoplay speed in
 * milliseconds before a merchant could touch a picture. Then: save the group,
 * open its list, create a slide, open the layer editor. OpenCart's three-page
 * banner flow is the thing this platform set out to beat, and this was one page
 * longer than it.
 */
class BannerCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('banners');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    public function test_one_form_makes_a_banner_that_actually_shows_something(): void
    {
        Storage::fake('public');

        $this->post(route('admin.banners.store'), [
            'name' => 'Cart Promo',
            'image' => UploadedFile::fake()->image('promo.jpg', 1200, 400),
            'link' => '/category/guitars',
            'height' => 500, 'height_mobile' => 400, 'speed' => 5000,
        ])->assertRedirect();

        $group = BannerGroup::where('name', 'Cart Promo')->first();

        $this->assertNotNull($group, 'The banner group was not created.');
        $this->assertCount(1, $group->slides, 'A banner was created with nothing in it.');
        $this->assertSame('/category/guitars', $group->slides->first()->link);
        Storage::disk('public')->assertExists($group->slides->first()->bg_image);
    }

    /** The slug is derived. Nobody came here to invent a URL. */
    public function test_the_reference_is_made_from_the_name(): void
    {
        $this->post(route('admin.banners.store'), [
            'name' => 'Cart Promo',
            'height' => 500, 'height_mobile' => 400, 'speed' => 5000,
        ])->assertRedirect();

        $this->assertSame('cart-promo', BannerGroup::where('name', 'Cart Promo')->value('slug'));
    }

    public function test_a_merchant_who_supplies_a_reference_keeps_it(): void
    {
        $this->post(route('admin.banners.store'), [
            'name' => 'Cart Promo', 'slug' => 'my-own-handle',
            'height' => 500, 'height_mobile' => 400, 'speed' => 5000,
        ])->assertRedirect();

        $this->assertSame('my-own-handle', BannerGroup::where('name', 'Cart Promo')->value('slug'));
    }

    /** The layout editor's link and the page it opens must agree. */
    public function test_the_link_in_the_layout_editor_says_what_the_page_says(): void
    {
        $schema = \App\Support\Widgets::schema('banners.group');

        $this->assertSame('New banner group', $schema[0]['create_label']);
        $this->get(route('admin.banners.create'))->assertOk()->assertSee('New banner group', false);
    }

    /** Without a multipart form the file never arrives and the banner is empty. */
    public function test_the_create_form_can_carry_a_file(): void
    {
        $this->get(route('admin.banners.create'))
            ->assertOk()
            ->assertSee('multipart/form-data', false)
            ->assertSee('name="image"', false);
    }
}
