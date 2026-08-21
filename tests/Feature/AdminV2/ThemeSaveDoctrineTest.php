<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One save carries every settings tab.
 *
 * The theme editor was the only surface in the admin that opted out of the
 * house save: seven separate forms, each with its own Save at the bottom left
 * of an 1830px page, while every sibling screen puts Save in the header. It
 * then needed a bespoke leave-guard modal to survive its own split, and left
 * Save permanently live with nothing to save.
 *
 * updateSettings() already carried every group in one request and said so in
 * its own comment. The split was only ever in the markup.
 */
class ThemeSaveDoctrineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
        $this->actingAs(User::factory()->create());
    }

    /** One doctrine form spans the settings tabs; record editors keep their own. */
    public function test_the_settings_tabs_share_a_single_form(): void
    {
        $html = $this->get(route('admin.theme.customise'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'action="' . route('admin.theme.settings.update') . '"'),
            'The settings tabs post from more than one form.');
    }

    /** The Save arms on a change instead of standing permanently live. */
    public function test_the_save_waits_for_something_to_save(): void
    {
        // The rendered behaviour, not the prop name: the Save is hidden until
        // the form is dirty. It used to stand live on page load with nothing
        // to save, on all nine surfaces in this area.
        $this->get(route('admin.theme.customise'))
            ->assertOk()
            ->assertSee('x-show="dirty || saving"', false);
    }

    /** The modal existed only to survive the split. */
    public function test_the_leave_guard_is_gone(): void
    {
        $this->get(route('admin.theme.customise'))
            ->assertOk()
            ->assertDontSee('Save this section first?', false)
            ->assertDontSee('Discard and move on', false);
    }

    /** The thing that matters: one submit still carries several groups. */
    public function test_one_submit_saves_colours_and_fonts_together(): void
    {
        $this->post(route('admin.theme.settings.update'), [
            'groups' => [
                'colors' => ['primary' => '#123456'],
                'fonts' => ['heading' => 'Archivo'],
            ],
        ])->assertRedirect();

        app(ThemeService::class)->clearCache();

        $this->assertSame('#123456', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
        $this->assertSame('Archivo', Setting::where('group', 'fonts')->where('key', 'heading')->value('value'));
    }

    /** A tab nobody has heard of renders the default, never a blank page. */
    public function test_an_unknown_tab_falls_back_rather_than_rendering_nothing(): void
    {
        $this->get(route('admin.theme.customise', ['tab' => 'nonsense']))
            ->assertOk()
            ->assertSee('Presets', false);
    }

    public function test_the_old_tab_key_still_works(): void
    {
        $this->get(route('admin.theme.customise', ['tab' => 'theme-settings']))
            ->assertOk()
            ->assertSee('Theme options', false);
    }

    /** The editor that reported success and changed nothing is gone. */
    public function test_the_dead_section_editor_is_unreachable(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.theme.sections.edit'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.theme.sections.update'));
    }
}
