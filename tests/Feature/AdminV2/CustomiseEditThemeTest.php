<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * The Customise looks editor follows the decoupling: riding themes get
 * it, live or not, writes landing in the right look; a theme with an
 * editor of its own is sent there instead.
 */
class CustomiseEditThemeTest extends TestCase
{
    use RefreshDatabase, UsesSampleTheme;

    private function boot(string $activate): void
    {
        $m = app(ExtensionManager::class);
        foreach (['theme', 'banners'] as $name) {
            $m->install($name);
        }
        $this->installSampleTheme();
        app(ExtensionManager::class)->bootEnabled();
        app(ThemeService::class)->activateTheme($activate);
        app(ThemeService::class)->clearCache();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_a_decoupled_live_theme_is_sent_to_its_own_editor(): void
    {
        $this->boot('sample');

        // Sample is not rideable, so with no riding target named the editor
        // lands on Venta; asking for Sample explicitly can never reach it.
        $this->get(route('admin.theme.customise'))->assertOk()->assertSee('Theme: Venta');
        $this->get(route('admin.theme.customise', ['theme' => 'sample']))->assertOk()->assertSee('Theme: Venta');
    }

    public function test_the_page_edits_the_header_and_footer_only(): void
    {
        $this->boot('venta');

        $html = $this->get(route('admin.theme.customise'))->assertOk()->getContent();
        $this->assertStringContainsString('Announcement bar', $html);
        $this->assertStringContainsString('Footer columns', $html);
        foreach (['Presets', 'Colors', 'Fonts', 'Theme options', 'Live preview'] as $gone) {
            $this->assertStringNotContainsString('>' . $gone . '<', $html);
        }
    }

    public function test_saving_writes_the_header_and_nothing_else(): void
    {
        $this->boot('venta');

        $this->post(route('admin.theme.settings.update'), ['groups' => [
            'header' => [
                'announcement_enabled' => '1',
                'announcement_text' => 'Free delivery this week',
                'announcement_link' => '/delivery',
                'topbar_links' => json_encode([['label' => 'Help', 'url' => '/help'], ['label' => '', 'url' => '/blank'], 'junk']),
            ],
            'colors' => ['primary' => '#111222'],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Free delivery this week', Setting::where('group', 'header')->where('key', 'announcement_text')->value('value'));
        $this->assertSame([['label' => 'Help', 'url' => '/help']], json_decode(Setting::where('group', 'header')->where('key', 'topbar_links')->value('value'), true));
        $this->assertNull(Setting::where('group', 'colors')->where('key', 'primary')->value('value'), 'The look is the theme\'s, not the merchant\'s.');
    }

    public function test_a_header_link_that_is_not_a_path_or_web_address_is_refused_and_never_printed(): void
    {
        $this->boot('venta');

        $this->post(route('admin.theme.settings.update'), ['groups' => ['header' => [
            'announcement_enabled' => '1', 'announcement_text' => 'Sale', 'announcement_link' => 'javascript:alert(1)',
        ]]])->assertSessionHasErrors('groups.header.announcement_link');

        $this->post(route('admin.theme.settings.update'), ['groups' => ['header' => [
            'announcement_enabled' => '1', 'announcement_text' => 'Sale', 'announcement_link' => '/sale',
            'topbar_links' => json_encode([['label' => 'Bad', 'url' => 'javascript:alert(1)'], ['label' => 'Help', 'url' => '/help']]),
        ]]])->assertSessionHasNoErrors();
        $this->assertSame([['label' => 'Help', 'url' => '/help']], json_decode(Setting::where('group', 'header')->where('key', 'topbar_links')->value('value'), true));

        // A value stored before the check existed is not printed either.
        Setting::where('group', 'header')->where('key', 'announcement_link')->update(['value' => 'javascript:alert(2)']);
        Setting::where('group', 'header')->where('key', 'topbar_links')->update(['value' => json_encode([['label' => 'Old', 'url' => 'javascript:alert(3)']])]);
        app(\App\Services\ThemeService::class)->clearCache();

        $this->assertStringNotContainsString('javascript:', $this->get('/')->assertOk()->getContent());
    }
}
