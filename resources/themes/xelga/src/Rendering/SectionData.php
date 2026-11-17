<?php

namespace Themes\Xelga\Rendering;

/**
 * A data provider for one section type: it receives the section's settings
 * and blocks (merged over their defaults) and returns the data its view
 * needs, read from core through core's own classes.
 */
interface SectionData
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array{id: string, type: string, label: string, disabled: bool, settings: array}>  $blocks
     * @return array<string, mixed>
     */
    public function resolve(array $settings, array $blocks, RenderContext $context): array;
}
