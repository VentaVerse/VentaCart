<?php

namespace Tests\Fixtures\Extensions\SampleExtension\Xelga\Blocks;

use Themes\Xelga\Rendering\RenderContext;
use Themes\Xelga\Rendering\SectionData;
use Themes\Xelga\Settings\LinkValue;

/** The seller block's data: the product of the page it is on, and its link. Only Xelga loads this class. */
class Seller implements SectionData
{
    public function resolve(array $settings, array $blocks, RenderContext $context): array
    {
        return [
            'product' => (string) ($context->get('product')?->name ?? ''),
            'link' => LinkValue::resolve($settings['link']),
        ];
    }
}
