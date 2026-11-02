<?php

namespace Tests\Feature\AdminV2;

use App\Services\ExtensionManager;
use Extensions\Theme\ColorGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\UsesSampleTheme;
use Tests\TestCase;

/**
 * The theme editor must be the whole theme. Every colour the storefront
 * obeys is editable, and nothing is left as a hardcoded literal that a merchant
 * cannot reach.
 *
 * The failure this guards against is silent. Add a token to theme.json and
 * forget the editor, and the storefront starts obeying a colour with no control
 * behind it; add a control for a token no theme declares, and the merchant
 * edits a value that changes nothing on the page. Both look fine in review and
 * are only discovered by someone trying to restyle their shop.
 */
class ThemeTokenCoverageTest extends TestCase
{
    use RefreshDatabase;
    use UsesSampleTheme;

    protected function setUp(): void
    {
        parent::setUp();
        app(ExtensionManager::class)->install('theme');
        app(ExtensionManager::class)->bootEnabled();
    }

    public function test_every_colour_the_theme_declares_has_a_control(): void
    {
        $missing = array_diff(ColorGroups::themeTokens('venta'), ColorGroups::tokens());

        $this->assertSame([], array_values($missing),
            'These colours exist in theme.json but cannot be edited: ' . implode(', ', $missing));
    }

    public function test_no_control_edits_a_colour_the_theme_does_not_have(): void
    {
        $ghosts = array_diff(ColorGroups::tokens(), ColorGroups::themeTokens('venta'));

        $this->assertSame([], array_values($ghosts),
            'These controls edit nothing: ' . implode(', ', $ghosts));
    }

    /** A token appearing in two groups renders two inputs posting the same key. */
    public function test_no_colour_is_offered_twice(): void
    {
        $tokens = ColorGroups::tokens();
        $dupes = array_keys(array_filter(array_count_values($tokens), fn ($n) => $n > 1));

        $this->assertSame([], $dupes, 'Offered in more than one group: ' . implode(', ', $dupes));
    }

    /** Two tokens that once had no control; they must still reach the page. */
    /**
     * A theme that groups a core colour under a heading of its own moves the
     * control there: one control per colour, in the theme's group, and
     * nothing left behind in core's.
     */
    public function test_a_theme_group_takes_a_core_colour_instead_of_repeating_it(): void
    {
        $this->makeSampleTheme(['color_groups' => [
            'Forms' => ['input_text' => 'Input text', 'input_bg' => 'Input background'],
            'Popups' => ['dropdown_bg' => 'Popup background', 'popup_text' => 'Popup text'],
            'Extras' => ['input_text' => 'Listed twice', 'extra_one' => 'Extra'],
        ]]);

        $groups = ColorGroups::all($this->sampleTheme);
        $tokens = ColorGroups::tokens($this->sampleTheme);

        $this->assertSame([], array_keys(array_filter(array_count_values($tokens), fn ($n) => $n > 1)));
        // The theme's own Forms joins core's: the theme's controls first, core's other field kept.
        $this->assertSame(['input_text', 'input_bg', 'input_border'], array_keys($groups['Forms']));
        $this->assertSame('Input background', $groups['Forms']['input_bg']);
        $this->assertSame(['dropdown_bg', 'popup_text'], array_keys($groups['Popups']));
        // Core's Dropdowns lost its only colour, so it is not shown at all.
        $this->assertArrayNotHasKey('Dropdowns', $groups);
        $this->assertSame(['extra_one'], array_keys($groups['Extras']));
        $this->assertArrayNotHasKey('input_bg', $groups['Page']);
    }

    /** Each core colour sits in its most specific group, not also in Page. */
    public function test_form_and_dropdown_colours_live_in_their_own_groups(): void
    {
        $groups = ColorGroups::all('venta');

        $this->assertSame(['input_bg', 'input_border'], array_keys($groups['Forms']));
        $this->assertSame(['dropdown_bg'], array_keys($groups['Dropdowns']));
        $this->assertSame([], array_intersect(['input_bg', 'input_border', 'dropdown_bg'], array_keys($groups['Page'])));
    }

    public function test_the_new_tokens_reach_the_storefront_as_css_variables(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('--color-warning:', false)
            ->assertSee('--color-overlay-btn-bg:', false);
    }
}
