<?php

namespace Themes\Xelga\Extensions;

/**
 * One extension's parts folder, as the registrar accepted it: the section
 * schemas, their views, the template defaults and the placements an
 * extension gives Xelga. Read only.
 */
final class ExtensionParts
{
    /** A page type, as core registers them (StorefrontPageType::register). */
    public const PAGE_TYPE = '/^[a-z][a-z0-9_]{0,39}$/';

    /**
     * @param  string  $name  the extension's name, e.g. 'marketplace'
     * @param  string  $title  from its extension.json, for the editor
     * @param  string  $version  from its extension.json, never from the caller
     * @param  string  $path  real path of the parts folder
     * @param  string  $namespace  the namespace of its provider, with a trailing backslash; its data classes live under it
     * @param  string|null  $stylesheet  its stylesheet as a build entry (relative to the app), or null
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $version,
        public readonly string $path,
        public readonly string $namespace,
        public readonly ?string $stylesheet,
    ) {
    }

    /** The view namespace of its section views: `xelga-{name}::sections.{name part of the type}`. */
    public function viewNamespace(): string
    {
        return 'xelga-' . $this->name;
    }

    /** @return list<string> */
    public function schemaFiles(): array
    {
        $files = array_values(array_filter(glob($this->path . '/schemas/sections/*.json') ?: [], fn (string $f) => is_file($f) && ! is_link($f)));
        sort($files);

        return $files;
    }

    /** @return list<string> its block schemas, `schemas/blocks/{type}.json` */
    public function blockFiles(): array
    {
        $files = array_values(array_filter(glob($this->path . '/schemas/blocks/*.json') ?: [], fn (string $f) => is_file($f) && ! is_link($f)));
        sort($files);

        return $files;
    }

    /**
     * The template defaults it ships, by page type.
     *
     * @return array<string, string> page type => file
     */
    public function templates(): array
    {
        $out = [];
        foreach (glob($this->path . '/templates/*.json') ?: [] as $file) {
            $type = basename($file, '.json');
            if (is_file($file) && ! is_link($file) && preg_match(self::PAGE_TYPE, $type) === 1) {
                $out[$type] = $file;
            }
        }
        ksort($out);

        return $out;
    }

    /** @return list<array<string, mixed>> the placements as the file has them; Placements checks them */
    public function placements(): array
    {
        $file = $this->path . '/placements.json';
        if (! is_file($file) || is_link($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        $list = is_array($data) ? ($data['placements'] ?? null) : null;

        return is_array($list) && array_is_list($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /** The newest file time and the file count, for the catalog's cache key in debug. */
    public function fileStamp(): string
    {
        $files = [...$this->schemaFiles(), ...$this->blockFiles(), ...array_values($this->templates()), $this->path . '/placements.json', $this->path . '/xelga.json'];
        $times = array_map('filemtime', array_filter($files, 'is_file'));

        return ($times === [] ? 0 : max($times)) . '.' . count($times);
    }
}
