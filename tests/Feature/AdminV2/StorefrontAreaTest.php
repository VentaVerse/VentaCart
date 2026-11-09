<?php

namespace Tests\Feature\AdminV2;

use App\Models\FooterColumn;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use App\Support\AdminNav;
use App\Support\AdminPermissions;
use App\Support\Breadcrumbs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * Storefront: one sidebar area for how the store looks and what sits where.
 * Themes chooses the theme, Layout places things on pages, Header & footer
 * holds what is on every page, Banners holds the banner groups. No page in
 * it links into another sidebar area.
 */
class StorefrontAreaTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleTheme;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['theme', 'banners'] as $name) {
            app(ExtensionManager::class)->install($name);
        }
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    private function group(string $label): ?array
    {
        return collect(AdminNav::groups())->firstWhere('label', $label);
    }

    public function test_the_sidebar_has_a_storefront_area_and_content_keeps_only_content(): void
    {
        $this->assertSame(['themes', 'layout', 'header-footer', 'banners'], array_column($this->group('Storefront')['items'], 'key'));
        $this->assertNotContains('layout', array_column($this->group('Content')['items'], 'key'));
        $this->assertNotContains('menus', array_column($this->group('Content')['items'], 'key'));
        $this->assertNotContains('themes', array_column($this->group('Extensions')['items'], 'key'));
    }

    public function test_an_extension_page_lights_its_storefront_entry_and_trails_from_there(): void
    {
        $this->assertSame(['group' => 'Storefront', 'item' => 'banners'], AdminNav::activeItemKey('/admin/extensions/banners'));
        $this->assertSame(['group' => 'Storefront', 'item' => 'themes'], AdminNav::activeItemKey('/admin/extensions/theme'));
        $this->assertSame(['Storefront', 'Banners'], array_column(Breadcrumbs::forPath('/admin/extensions/banners'), 'label'));
    }

    public function test_every_tab_opens(): void
    {
        foreach (['announcement' => 'Announcement bar', 'top-bar' => 'Top bar links', 'main-menu' => 'Add an item', 'footer' => 'Footer columns'] as $tab => $see) {
            $this->get(route('admin.header-footer', ['tab' => $tab]))->assertOk()->assertSee($see)->assertSee('Storefront');
        }
        $this->get(route('admin.header-footer', ['tab' => 'nonsense']))->assertOk()->assertSee('Announcement bar');
    }

    public function test_the_announcement_saves_and_refuses_a_script_link(): void
    {
        $this->post(route('admin.header-footer.announcement'), [
            'announcement_enabled' => '1', 'announcement_text' => 'Free delivery', 'announcement_link' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('announcement_link');

        $this->post(route('admin.header-footer.announcement'), [
            'announcement_enabled' => '1', 'announcement_text' => 'Free delivery', 'announcement_link' => '/delivery',
        ])->assertRedirect(route('admin.header-footer', ['tab' => 'announcement']));

        $this->assertSame('Free delivery', Setting::where('group', 'header')->where('key', 'announcement_text')->value('value'));
        $this->assertSame('/delivery', Setting::where('group', 'header')->where('key', 'announcement_link')->value('value'));
    }

    public function test_the_top_bar_keeps_only_safe_links_and_leaves_the_announcement_alone(): void
    {
        Setting::create(['group' => 'header', 'key' => 'announcement_text', 'value' => 'Kept']);

        $this->post(route('admin.header-footer.top-bar'), [
            'topbar_links' => json_encode([['label' => 'Help', 'url' => '/help'], ['label' => 'Bad', 'url' => 'javascript:alert(1)']]),
        ])->assertRedirect(route('admin.header-footer', ['tab' => 'top-bar']));

        $this->assertSame([['label' => 'Help', 'url' => '/help']], json_decode(Setting::where('group', 'header')->where('key', 'topbar_links')->value('value'), true));
        $this->assertSame('Kept', Setting::where('group', 'header')->where('key', 'announcement_text')->value('value'));
    }

    public function test_footer_columns_are_added_ordered_edited_and_deleted(): void
    {
        $first = FooterColumn::create(['title' => 'Help', 'type' => 'links', 'config' => ['links' => []], 'sort_order' => 0, 'is_enabled' => true]);

        $this->post(route('admin.footer.store'), ['title' => 'We accept', 'type' => 'payment_icons'])->assertRedirect();
        $second = FooterColumn::where('title', 'We accept')->sole();

        $this->post(route('admin.footer.arrange'), ['order' => [$second->id, $first->id]])
            ->assertRedirect(route('admin.header-footer', ['tab' => 'footer']));
        $this->assertSame([$second->id, $first->id], FooterColumn::orderBy('sort_order')->pluck('id')->all());

        $this->put(route('admin.footer.update', $first), [
            'title' => 'Help', 'is_enabled' => '1',
            'config' => ['links' => [['label' => 'Returns', 'url' => '/returns'], ['label' => 'Bad', 'url' => 'javascript:alert(1)']]],
        ])->assertRedirect(route('admin.header-footer', ['tab' => 'footer']));
        $this->assertSame([['label' => 'Returns', 'url' => '/returns']], $first->fresh()->config['links']);

        $this->put(route('admin.footer.update', $second), [
            'title' => 'We accept', 'config' => ['icons' => [['type' => 'image', 'value' => '']]],
            'icon_uploads' => [UploadedFile::fake()->create('shell.php', 4, 'text/x-php')],
        ])->assertSessionHasErrors('icon_uploads.0');

        $this->delete(route('admin.footer.destroy', $second))->assertRedirect();
        $this->assertNull($second->fresh());
    }

    public function test_old_addresses_land_on_the_storefront_pages(): void
    {
        $this->get('/admin/content/layout?page=home')->assertRedirect(route('admin.layout').'?page=home');
        $this->get('/admin/content/menus')->assertRedirect(route('admin.menus'));
        $this->get(route('admin.menus'))->assertRedirect(route('admin.header-footer', ['tab' => 'main-menu']));
        $this->get('/admin/extensions/theme/customise?tab=footer')->assertRedirect(route('admin.header-footer').'?tab=footer');
    }

    public function test_the_theme_directory_offers_no_door_into_layout_or_header_and_footer(): void
    {
        $html = $this->get(route('admin.theme.index'))->assertOk()->getContent();
        // The cards, not the sidebar (which links every Storefront page).
        $html = substr($html, (int) strpos($html, '<div class="v-themes">'));

        $this->assertStringNotContainsString(route('admin.layout'), $html);
        $this->assertStringNotContainsString(route('admin.header-footer'), $html);
        $this->assertStringNotContainsString('Customise', $html);
    }

    public function test_a_live_theme_with_storage_of_its_own_edits_these_in_its_own_editor(): void
    {
        $this->installSampleTheme(activate: true, manifest: ['storage' => 'own']);
        app(ThemeService::class)->clearCache();

        $this->get(route('admin.header-footer'))->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.header-footer.announcement'), ['announcement_text' => 'x'])->assertRedirect(route('admin.dashboard'));
        $this->assertNull(Setting::where('group', 'header')->where('key', 'announcement_text')->value('value'));
    }

    public function test_header_and_footer_follow_the_menus_permission(): void
    {
        $permissions = app(AdminPermissions::class);

        $this->assertSame('menus.view', $permissions->requirementFor('admin.header-footer', 'GET'));
        $this->assertSame('menus.edit', $permissions->requirementFor('admin.header-footer.announcement', 'POST'));
        $this->assertSame('menus.edit', $permissions->requirementFor('admin.footer.arrange', 'POST'));
        $this->assertSame('menus.delete', $permissions->requirementFor('admin.footer.destroy', 'DELETE'));
    }

    public function test_a_module_card_says_where_the_module_lives(): void
    {
        $this->assertSame('Storefront > Banners', AdminNav::homeOf('banners'));
        $this->get(route('admin.extensions.modules'))->assertOk()->assertSee('Storefront &gt; Banners', false);
    }

    /** A link stored before the checks existed is not printed either. */
    public function test_an_unsafe_stored_link_never_reaches_the_storefront(): void
    {
        $this->withoutVite();
        Setting::create(['group' => 'header', 'key' => 'announcement_enabled', 'value' => '1']);
        Setting::create(['group' => 'header', 'key' => 'announcement_text', 'value' => 'Sale']);
        Setting::create(['group' => 'header', 'key' => 'announcement_link', 'value' => 'javascript:alert(1)']);
        Setting::create(['group' => 'header', 'key' => 'topbar_links', 'value' => json_encode([['label' => 'Old', 'url' => 'javascript:alert(2)']])]);
        FooterColumn::create(['title' => 'Help', 'type' => 'links', 'config' => ['links' => [['label' => 'Bad', 'url' => 'javascript:alert(3)']]], 'sort_order' => 0, 'is_enabled' => true]);
        app(ThemeService::class)->clearCache();

        $this->assertStringNotContainsString('javascript:', $this->get('/')->assertOk()->getContent());
    }
}
