<?php

namespace Tests\Feature\AdminV2;

use App\Services\ExtensionManager;
use Extensions\Theme\ColorGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner ruling: the theme editor must BE the theme. Every colour the storefront
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

    /** Stage 2 added these two; they are the reason the sweep found anything. */
    public function test_the_new_tokens_reach_the_storefront_as_css_variables(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('--color-warning:', false)
            ->assertSee('--color-overlay-btn-bg:', false);
    }
}
