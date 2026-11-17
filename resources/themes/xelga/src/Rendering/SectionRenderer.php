<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\HtmlString;
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
                ? $this->wrap($id, view('xelga::snippets.section-missing', ['id' => $id, 'type' => $type] + self::owner($type))->render())
                : '';
        }
        if ($disabled && ! $context->preview) {
            return '';
        }
        // Stored while nothing could check it (its extension was away, or a
        // placement put it there): cleaned now, so no view sees an unchecked value.
        if (($section['unchecked'] ?? false) === true) {
            $section = $this->checked($schema, $section);
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
            if (($block['unchecked'] ?? false) === true) {
                $block['settings'] = (new \Themes\Xelga\Settings\Sanitizer($this->sanitizeContext()))
                    ->cleanMap($schema->blockSettings((string) $block['type']), (array) ($block['settings'] ?? []), "blocks.{$blockId}");
            }
            $entry = [
                'id' => $blockId,
                'type' => (string) $block['type'],
                'label' => (string) ($block['label'] ?? ''),
                'disabled' => $blockDisabled,
                // A published page saved before a setting moved down to the blocks still draws as it was.
                'settings' => array_merge($schema->blockDefaults((string) $block['type']), $schema->carryDown((string) $block['type'], (array) ($section['settings'] ?? []), (array) ($block['settings'] ?? []))),
            ];
            $spec = $schema->blockType((string) $block['type']);
            if (is_string($spec['source'] ?? null)) {
                $entry['source'] = $spec['source'];
                $entry['html'] = $this->extensionBlock($spec, $entry, $id, $context);
            }
            $blocks[] = $entry;
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

            $html = ($schema->source() === null ? view($schema->view()) : view()->file((string) $schema->viewFile()))->with([
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

    /**
     * Who a missing extension section belongs to, for the editor's note.
     *
     * @return array{extension: ?string, absence: ?string}
     */
    public static function owner(string $type): array
    {
        $name = \Themes\Xelga\Documents\Shape::extensionOf($type);
        if ($name === null) {
            return ['extension' => null, 'absence' => null];
        }
        $extensions = \Themes\Xelga\Extensions\Extensions::current();

        return ['extension' => $extensions->titleOf($name), 'absence' => $extensions->absence($name)];
    }

    /**
     * An extension block drawn by its own view, inside the theme's block
     * wrapper (which carries the ids the editor selects it by). A block that
     * fails is logged and left out; the section still draws.
     *
     * @param  array{id: string, type: string, label: string, disabled: bool, settings: array}  $block
     */
    private function extensionBlock(array $spec, array $block, string $sectionId, RenderContext $context): HtmlString
    {
        $part = \Themes\Xelga\Extensions\Extensions::current()->get((string) $spec['source']);
        $file = $part === null ? null : $part->path . '/views/blocks/' . $block['type'] . '.blade.php';
        if ($file === null || ! is_file($file) || is_link($file)) {
            return new HtmlString('');
        }

        try {
            $data = [];
            $class = $spec['data_class'] ?? null;
            if (is_string($class) && class_exists($class) && is_subclass_of($class, SectionData::class)) {
                $data = app($class)->resolve($block['settings'], [], $context);
            }
            $inner = view()->file($file, [
                'block' => $block,
                'settings' => $block['settings'],
                'data' => $data,
                'context' => $context,
                'section' => ['id' => $sectionId],
            ])->render();
        } catch (\Throwable $e) {
            report($e);
            if (! $context->preview) {
                return new HtmlString('');
            }
            $inner = '';
        }

        return new HtmlString(view('xelga::snippets.extension-block', ['block' => $block, 'class' => (string) $spec['class'], 'inner' => new HtmlString($inner)])->render());
    }

    /** A section's settings and blocks run through the sanitizer, as a save would. */
    private function checked(\Themes\Xelga\Catalog\SectionSchema $schema, array $section): array
    {
        $sanitizer = new \Themes\Xelga\Settings\Sanitizer($this->sanitizeContext());
        $stored = (array) ($section['settings'] ?? []);
        $section['settings'] = $sanitizer->cleanMap($schema->settings(), $stored, 'settings');
        foreach ((array) ($section['blocks'] ?? []) as $blockId => $block) {
            $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
            if ($schema->blockType($type) === null) {
                unset($section['blocks'][$blockId]);
                continue;
            }
            $section['blocks'][$blockId]['settings'] = $sanitizer->cleanMap(
                $schema->blockSettings($type),
                $schema->carryDown($type, $stored, (array) ($block['settings'] ?? [])),
                "blocks.{$blockId}",
            );
        }

        return $section;
    }

    private ?\Themes\Xelga\Settings\SanitizeContext $sanitizeContext = null;

    private function sanitizeContext(): \Themes\Xelga\Settings\SanitizeContext
    {
        if ($this->sanitizeContext === null) {
            $settings = app(DocumentRepository::class)->content(\Themes\Xelga\Documents\Handles::SETTINGS);
            $ids = array_values(array_filter(array_column((array) ($settings['schemes'] ?? []), 'id'), 'is_string'));
            $this->sanitizeContext = \Themes\Xelga\Settings\SanitizeContext::forStore($ids !== [] ? $ids : ['scheme-1']);
        }

        return $this->sanitizeContext;
    }

    private function wrap(string $id, string $html): string
    {
        return "<!--xelga:{$id}-->" . $html . "<!--/xelga:{$id}-->";
    }
}
