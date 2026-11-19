<?php

namespace Themes\Xelga\Tests\Feature;

use Tests\TestCase;

/**
 * A product card shows one photo at a time. Its second photo sits on top of
 * the first, hidden, and takes over on hover; nothing may show both at once
 * or leave the card without a photo.
 */
class XelgaCardPhotosTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/css/components/card.css');
    }

    public function test_a_sold_out_card_fades_its_photos_without_showing_the_hidden_one(): void
    {
        $css = $this->css();

        // Opacity on both photos would override the second photo's hidden
        // state and stack them; a filter fades whichever photo is showing.
        $this->assertMatchesRegularExpression('/\.card\.is-soldout \.card__img \{\s*filter: opacity\(\.55\);\s*\}/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.card\.is-soldout \.card__img \{[^}]*(?<![-(])opacity:/', $css);
        $this->assertMatchesRegularExpression('/\.card__img--alt \{\s*opacity: 0;\s*\}/', $css);
    }

    public function test_the_first_photo_gives_way_on_hover_only_where_a_second_one_shows(): void
    {
        $css = $this->css();

        $this->assertStringContainsString('.card:hover .card__img:first-child:has(~ .card__img--alt) {', $css);
        // A list row hides its second photo, so its first stays on hover.
        $this->assertMatchesRegularExpression('/html \.s\[data-variant="list"\] \.card:hover \.card__img:first-child:has\(~ \.card__img--alt\) \{\s*opacity: 1;\s*\}/', $css);
    }
}
