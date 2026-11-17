<?php

namespace Themes\Xelga\Catalog;

/**
 * One section type as the catalog compiled it: its settings, blocks,
 * variants, limits and the templates it may be added to.
 */
final class SectionSchema
{
    public readonly string $type;

    public readonly string $label;

    public readonly string $group;

    public function __construct(public readonly array $raw)
    {
        $this->type = (string) $raw['type'];
        $this->label = (string) ($raw['label'] ?? $raw['type']);
        $this->group = (string) ($raw['group'] ?? 'template');
    }

    /** The extension the type comes from, or null for the theme's own. */
    public function source(): ?string
    {
        $source = $this->raw['source'] ?? null;

        return is_string($source) && $source !== '' ? $source : null;
    }

    /** The theme's view that draws one of its own sections. */
    public function view(): string
    {
        return 'xelga::sections.' . $this->type;
    }

    /**
     * The file that draws an extension's section, `views/sections/{type}.blade.php`
     * in its parts folder, or null while the extension is not registered.
     */
    public function viewFile(): ?string
    {
        $part = $this->source() === null ? null : \Themes\Xelga\Extensions\Extensions::current()->get($this->source());

        return $part === null ? null : $part->path . '/views/sections/' . $this->type . '.blade.php';
    }

    /** Its data provider: named after the type for the theme's own, declared by an extension for its own. */
    public function dataClass(): ?string
    {
        if ($this->source() !== null) {
            $class = $this->raw['data_class'] ?? null;

            return is_string($class) && $class !== '' ? $class : null;
        }

        return 'Themes\\Xelga\\Sections\\' . \Illuminate\Support\Str::studly($this->type);
    }

    /** The section's CSS class. */
    public function cssClass(): string
    {
        return (string) ($this->raw['class'] ?? 's-' . $this->type);
    }

    /** @return array<string, SettingSchema> */
    public function settings(): array
    {
        return self::keyed($this->raw['settings'] ?? []);
    }

    /** @return array<string, mixed> every setting's default */
    public function defaults(): array
    {
        return array_map(fn (SettingSchema $s) => $s->default, $this->settings());
    }

    public function blockType(string $type): ?array
    {
        foreach ($this->raw['blocks']['types'] ?? [] as $spec) {
            if (($spec['type'] ?? null) === $type) {
                return $spec;
            }
        }

        return null;
    }

    /** @return array<string, SettingSchema> */
    public function blockSettings(string $type): array
    {
        return self::keyed($this->blockType($type)['settings'] ?? []);
    }

    /** @return array<string, mixed> */
    public function blockDefaults(string $type): array
    {
        return array_map(fn (SettingSchema $s) => $s->default, $this->blockSettings($type));
    }

    /**
     * A block's own settings with values carried down from its section: a
     * setting that moved from the section to its blocks keeps the value a
     * section saved before the move. Only keys the block type has, the
     * section no longer has and the block does not set itself are carried.
     *
     * @param  array<string, mixed>  $sectionSettings  as stored, before cleaning
     * @param  array<string, mixed>  $blockSettings  as stored
     * @return array<string, mixed>
     */
    public function carryDown(string $blockType, array $sectionSettings, array $blockSettings): array
    {
        $own = $this->settings();
        foreach (array_keys($this->blockSettings($blockType)) as $key) {
            if (! isset($own[$key]) && array_key_exists($key, $sectionSettings) && ! array_key_exists($key, $blockSettings)) {
                $blockSettings[$key] = $sectionSettings[$key];
            }
        }

        return $blockSettings;
    }

    public function blockMin(): int
    {
        return (int) ($this->raw['blocks']['min'] ?? 0);
    }

    public function blockMax(): int
    {
        return (int) ($this->raw['blocks']['max'] ?? 0);
    }

    public function limit(): ?int
    {
        $limit = $this->raw['limit'] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Whether a section of this type may sit in the document with this
     * handle: a group section only in its own group, a template section
     * only in the page types it lists.
     */
    public function allowedIn(string $handle, string $kind, ?string $pageType): bool
    {
        if ($kind === 'group') {
            return $this->group === $handle;
        }
        if ($kind !== 'template' || $this->group !== 'template') {
            return false;
        }
        $templates = (array) ($this->raw['templates'] ?? []);
        if (in_array('*', $templates, true)) {
            return true;
        }
        if (\Themes\Xelga\Documents\Handles::isExtensionTemplate($handle) && in_array('*extension', $templates, true)) {
            return true;
        }
        $isCustom = str_contains($handle, '.');

        return $isCustom ? in_array('*custom', $templates, true) && in_array($pageType, $templates, true) : in_array($pageType, $templates, true);
    }

    /** @return array<string, SettingSchema> */
    private static function keyed(array $settings): array
    {
        $out = [];
        foreach ($settings as $spec) {
            $out[(string) $spec['key']] = new SettingSchema($spec);
        }

        return $out;
    }
}
