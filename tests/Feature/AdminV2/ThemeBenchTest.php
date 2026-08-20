<?php

namespace Tests\Feature\AdminV2;

use App\Models\Setting;
use App\Models\User;
use App\Services\ExtensionManager;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bench: the storefront in a frame with the controls beside it.
 *
 * The question this exists to answer is the one the owner actually asked,
 * standing on /account/addresses: "the text is pale grey, how do I change that
 * in the editor?" Answering it meant reading the blade, the CSS and the
 * settings table. A merchant has none of those.
 */
class ThemeBenchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
    }

    private function url(): string
    {
        return route('admin.theme.preview');
    }

    public function test_the_bench_is_behind_admin_auth(): void
    {
        $this->get($this->url())->assertRedirect();
    }

    public function test_it_renders_with_the_frame_and_the_controls(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get($this->url())
            ->assertOk()
            ->assertSee('v-bench__frame', false)
            ->assertSee('__preview=1', false)
            ->assertSee('Whole store', false);
    }

    /** Both scopes save in one submit, because one editing session crosses them. */
    public function test_one_save_carries_the_store_and_a_page_type(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.preview.save'), [
            'payload' => json_encode([
                'global' => ['text_muted' => '#445566'],
                'pages'  => ['account' => ['text_muted' => '#112233']],
            ]),
        ])->assertRedirect();

        $theme = app(ThemeService::class);
        $theme->clearCache();

        $this->assertSame('#445566', Setting::where('group', 'colors')->where('key', 'text_muted')->value('value'));
        $this->assertSame(['text_muted' => '#112233'], $theme->pageOverrides('account', 'colors'));
    }

    /** A token no theme declares must not become a setting because it was posted. */
    public function test_an_unknown_token_is_ignored(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.preview.save'), [
            'payload' => json_encode(['global' => ['not_a_token' => '#000000']]),
        ])->assertRedirect();

        $this->assertNull(Setting::where('group', 'colors')->where('key', 'not_a_token')->value('value'));
    }

    public function test_an_unknown_page_type_is_ignored_rather_than_fatal(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('admin.theme.preview.save'), [
            'payload' => json_encode(['pages' => ['not-a-page' => ['primary' => '#000000']]]),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_a_malformed_payload_does_not_wipe_the_theme(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::updateOrCreate(['group' => 'colors', 'key' => 'primary'], ['value' => '#0E8A43']);

        $this->post(route('admin.theme.preview.save'), ['payload' => 'not json'])->assertRedirect();

        $this->assertSame('#0E8A43', Setting::where('group', 'colors')->where('key', 'primary')->value('value'));
    }
}
