<?php

namespace Themes\Xelga\Rendering;

use Themes\Xelga\Catalog\Catalog;

/**
 * Draws a document's sections in order. Settings are merged over the
 * schema's defaults, so a setting added in a theme update needs no data
 * change. Each section is wrapped in comment markers, and one that fails
 * is logged and left out, so a bad section never takes the page down.
 */
final class SectionRenderer
{
    /** @var list<array> what the editor preview needs to know about the drawn sections */
    private array $pageData = [];

    public function render(array $content, RenderContext $context): string
    {
        $html = '';
        $sections = (array) ($content['sections'] ?? []);

        foreach ((array) ($content['order'] ?? []) as $id) {
            $section = $sections[$id] ?? null;
            if (! is_string($id) || ! is_array($section)) {
                continue;
            }
            $html .= $this->section($id, $section, $context);
        }

        return $html;
    }

    /** @return list<array> */
    public function pageData(): array
    {
        return $this->pageData;
    }

    private function section(string $id, array $section, RenderContext $context): string
    {
        $type = (string) ($section['type'] ?? '');
        $schema = Catalog::section($type);
        $disabled = (bool) ($section['disabled'] ?? false);

        if ($schema === null) {
            return $context->preview
                ? $this->wrap($id, view('xelga::snippets.section-missing', ['id' => $id, 'type' => $type])->render())
                : '';
        }
        if ($disabled && ! $context->preview) {
            return '';
        }

        $settings = array_merge($schema->defaults(), (array) ($section['settings'] ?? []));
        $blocks = [];
        foreach ((array) ($section['block_order'] ?? []) as $blockId) {
            $block = $section['blocks'][$blockId] ?? null;
            if (! is_string($blockId) || ! is_array($block) || $schema->blockType((string) ($block['type'] ?? '')) === null) {
                continue;
            }
            $blockDisabled = (bool) ($block['disabled'] ?? false);
            if ($blockDisabled && ! $context->preview) {
                continue;
            }
            $blocks[] = [
                'id' => $blockId,
                'type' => (string) $block['type'],
                'label' => (string) ($block['label'] ?? ''),
                'disabled' => $blockDisabled,
                // A published page saved before a setting moved down to the blocks still draws as it was.
                'settings' => array_merge($schema->blockDefaults((string) $block['type']), $schema->carryDown((string) $block['type'], (array) ($section['settings'] ?? []), (array) ($block['settings'] ?? []))),
            ];
        }

        $label = (string) (($section['label'] ?? '') !== '' ? $section['label'] : $schema->label);
        $this->pageData[] = [
            'id' => $id,
            'type' => $type,
            'group' => $context->group,
            'label' => $label,
            'disabled' => $disabled,
            'blocks' => array_map(fn (array $b) => ['id' => $b['id'], 'type' => $b['type'], 'label' => $b['label'] !== '' ? $b['label'] : (string) ($schema->blockType($b['type'])['label'] ?? $b['type']), 'disabled' => $b['disabled']], $blocks),
        ];

        try {
            $data = [];
            $provider = $schema->dataClass();
            if ($provider !== null && class_exists($provider) && is_subclass_of($provider, SectionData::class)) {
                $data = app($provider)->resolve($settings, $blocks, $context);
            }

            $html = view($schema->view(), [
                'section' => ['id' => $id, 'type' => $type, 'label' => $label, 'disabled' => $disabled, 'class' => $schema->cssClass()],
                'settings' => $settings,
                'blocks' => $blocks,
                'data' => $data,
                'context' => $context,
            ])->render();
        } catch (\Throwable $e) {
            report($e);
            $html = $context->preview
                ? view('xelga::snippets.section-error', ['id' => $id, 'label' => $label])->render()
                : '';
        }

        return $this->wrap($id, $html);
    }

    private function wrap(string $id, string $html): string
    {
        return "<!--xelga:{$id}-->" . $html . "<!--/xelga:{$id}-->";
    }
}
