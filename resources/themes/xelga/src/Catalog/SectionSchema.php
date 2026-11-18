<?php

namespace Themes\Xelga\Catalog;

/**
 * One section type as the catalog compiled it: its settings, blocks,
 * variants, limits and the templates it may be added to.
 */
final class SectionSchema
{
    /** The groups of tiles the section picker shows the theme's own sections in, in order. */
    public const PICKER_GROUPS = ['banners', 'products', 'categories', 'text'];

    /** The group of a section that names none: text and trust. */
    public const DEFAULT_PICKER_GROUP = 'text';

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

    /**
     * The group of tiles the section picker shows the section in: the
     * schema's own, else text and trust. An extension's sections are
     * grouped under the extension, so they have none.
     */
    public function pickerGroup(): ?string
    {
        if ($this->source() !== null) {
            return null;
        }
        $group = $this->raw['picker_group'] ?? null;

        return in_array($group, self::PICKER_GROUPS, true) ? $group : self::DEFAULT_PICKER_GROUP;
    }

    /**
     * Where the section picker shows the section within its group: lower
     * first, the most used types leading. Null when the schema gives no
     * order, or for an extension's section; those follow in catalog order.
     */
    public function pickerOrder(): ?int
    {
        $order = $this->raw['picker_order'] ?? null;

        return $this->source() === null && is_int($order) && $order > 0 ? $order : null;
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
     * Whether the section is fixed in its band: the Header in the header
     * band, the Footer in the footer band. A fixed section is always there
     * and cannot be removed, moved or swapped, but may be hidden. Only the
     * theme's own header and footer sections can be fixed.
     */
    public function fixed(): bool
    {
        return $this->group !== 'template' && $this->source() === null && ($this->raw['fixed'] ?? false) === true;
    }

    /**
     * Whether the section is locked on its document: fixed in its band, or
     * a page's main section (its page content), which can never be hidden.
     */
    public function locked(): bool
    {
        return $this->fixed() || ($this->raw['main'] ?? false) === true;
    }

    /**
     * The narrowest column, in twelfths, this section reads well in: the
     * variant's own `span` when it declares one (some variants cannot
     * shrink as far as the section), else the section's `span.min`. A
     * section that declares no span is full width only.
     */
    public function minSpan(?string $variant = null): int
    {
        $spans = $this->spans();

        return $variant !== null && isset($spans['variants'][$variant]) ? $spans['variants'][$variant] : $spans['min'];
    }

    /** @return array{min: int, variants: array<string, int>} the section's span and each variant that sets its own */
    public function spans(): array
    {
        $span = $this->raw['span'] ?? null;
        $variants = [];
        foreach ((array) ($this->raw['variants'] ?? []) as $spec) {
            if (is_array($spec) && is_string($spec['value'] ?? null) && is_int($spec['span'] ?? null)) {
                $variants[$spec['value']] = $spec['span'];
            }
        }

        return ['min' => is_array($span) && is_int($span['min'] ?? null) ? $span['min'] : 12, 'variants' => $variants];
    }

    /**
     * The shared groups (the header band, the footer band) the section may
     * also go in, besides the page types its `templates` lists. Only the
     * theme's own sections can: the header and the footer are drawn on
     * every page, checkout and account included, where an extension's
     * section never goes.
     *
     * @return list<string>
     */
    public function sharedGroups(): array
    {
        if ($this->source() !== null) {
            return [];
        }
        $groups = (array) ($this->raw['shared_groups'] ?? []);

        return array_values(array_intersect(\Themes\Xelga\Documents\Handles::GROUPS, $groups));
    }

    /** Whether the section keeps a one-column row of its own, never a column or a side. */
    public function ownRow(): bool
    {
        return $this->ownFlag('own_row');
    }

    /**
     * Whether `limit` counts per area on a page with areas (Top, Content,
     * Bottom), instead of per page.
     */
    public function limitPerArea(): bool
    {
        return $this->ownFlag('limit_per_area');
    }

    /**
     * Whether the section belongs beside the page content: on a page with
     * areas, only in Content or a side, never in Top or Bottom.
     */
    public function contentOnly(): bool
    {
        return $this->ownFlag('content_only');
    }

    /**
     * Whether a section of this type may sit in the document with this
     * handle: a group section in its own group, a section in a shared
     * group it lists, a template section only in the page types it lists.
     */
    public function allowedIn(string $handle, string $kind, ?string $pageType): bool
    {
        if ($kind === 'group') {
            return $this->group === $handle || in_array($handle, $this->sharedGroups(), true);
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

    /** A placement key only the theme's own sections may set. */
    private function ownFlag(string $key): bool
    {
        return $this->source() === null && ($this->raw[$key] ?? false) === true;
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
