<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Customise looks editor follows the decoupling: riding themes get
 * it, live or not, writes landing in the right look; a theme with an
 * editor of its own is sent there instead.
 */
class CustomiseEditThemeTest extends TestCase
{
    use RefreshDatabase;

    private function boot(string $activate): void
    {
        $m = app(ExtensionManager::class);
        foreach (['theme', 'banners', ThemeService::registryName('plaza')] as $name) {
            $m->install($name);
        }
        $m->bootEnabled();
        app(ThemeService::class)->activateTheme($activate);
        app(ThemeService::class)->clearCache();
        $this->actingAs(User::factory()->create());
    }

    public function test_a_decoupled_live_theme_is_sent_to_its_own_editor(): void
    {
        $this->boot('plaza');

        // Plaza is not rideable, so with no riding target named the editor
        // lands on Venta; asking for Plaza explicitly can never reach it.
        $this->get(route('admin.theme.customise'))->assertOk()->assertSee('Theme: Venta');
        $this->get(route('admin.theme.customise', ['theme' => 'plaza']))->assertOk()->assertSee('Theme: Venta');
    }

    public function test_customising_a_riding_theme_that_is_not_live_edits_its_stash(): void
    {
        // Plaza first (so venta's look is stashed when plaza goes live).
        $this->boot('venta');
        app(ThemeService::class)->activateTheme('plaza');
        app(ThemeService::class)->clearCache();
        $plazaPrimary = (string) Setting::where('group', 'colors')->where('key', 'primary')->value('value');
        $stashPrimary = (string) Setting::where('group', 'colors__theme_venta')->where('key', 'primary')->value('value');

        // The page shows Venta's stashed look, not the live Plaza one, and
        // hides the presets section while editing a non-live theme.
        $html = $this->get(route('admin.theme.customise', ['theme' => 'venta']))->assertOk()->getContent();
        $this->assertStringContainsString('Theme: Venta', $html);
        $this->assertStringContainsString($stashPrimary, $html);
        $this->assertStringNotContainsString('>Presets<', $html);

        // Saving writes the stash; the live look does not move.
        $this->post(route('admin.theme.settings.update'), ['theme' => 'venta', 'groups' => ['colors' => ['primary' => '#654321']]])->assertRedirect();
        $this->assertSame('#654321', Setting::where('group', 'colors__theme_venta')->where('key', 'primary')->value('value'));
        $this->assertSame($plazaPrimary, (string) Setting::where('group', 'colors')->where('key', 'primary')->value('value'));

        // Going live, Venta wears the edit.
        app(ThemeService::class)->activateTheme('venta');
        app(ThemeService::class)->clearCache();
        $this->assertSame('#654321', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertStringContainsString('--color-primary: #654321;', $this->get('/')->assertOk()->getContent());
    }

    public function test_the_live_riding_theme_is_edited_exactly_as_before(): void
    {
        $this->boot('venta');
        $this->get(route('admin.theme.customise'))->assertOk()->assertSee('Presets');
        $this->post(route('admin.theme.settings.update'), ['groups' => ['colors' => ['primary' => '#111222']]])->assertRedirect();
        $this->assertSame('#111222', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
    }
}
