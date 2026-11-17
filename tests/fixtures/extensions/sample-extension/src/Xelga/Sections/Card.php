<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Xelga\Sections;

use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/** The sample card's data: its link, and the thing the page is about when the page has one. Only Xelga loads this class. */
class Card implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        return [
            'link' => LinkValue::resolve($settings['link']),
            'thing' => $context->get('thing'),
        ];
    }
}
