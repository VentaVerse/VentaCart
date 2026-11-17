<?php

namespace Themes\Xelga\Rendering;

use Illuminate\Support\Facades\Schema;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Preview\PreviewMode;

/**
 * The documents one request draws with, loaded in one query and kept for
 * the request: the draft in the editor's preview, the published content
 * for everyone else, the shipped defaults for anything never published.
 */
final class DocumentRepository
{
    /** @var array<string, ?Document> */
    private array $rows = [];

    private bool $tableChecked = false;

    private bool $hasTable = false;

    /** @param list<string> $handles */
    public function load(array $handles): void
    {
        $missing = array_values(array_diff(array_unique($handles), array_keys($this->rows)));
        if ($missing === []) {
            return;
        }
        foreach ($missing as $handle) {
            $this->rows[$handle] = null;
        }
        if (! $this->tableExists()) {
            return;
        }
        foreach (Document::query()->whereIn('handle', $missing)->get() as $row) {
            $this->rows[$row->handle] = $row;
        }
    }

    /** The content to draw for this handle on this request. */
    public function content(string $handle): array
    {
        $this->load([...Handles::SHARED, $handle]);
        $row = $this->rows[$handle] ?? null;

        if (PreviewMode::active() && $row?->draft !== null) {
            return \Themes\Xelga\Extensions\Placements::overlay($handle, $row->draft);
        }

        // Extension placements apply as the page is read: nothing is written.
        return \Themes\Xelga\Extensions\Placements::overlay($handle, $row?->published ?? Defaults::content($handle));
    }

    /** The version of the published theme settings, for the tokens stylesheet address. */
    public function settingsVersion(): int
    {
        $this->load(Handles::SHARED);

        return (int) ($this->rows[Handles::SETTINGS]?->published_version ?? 0);
    }

    /** Keep a row another query already read (an assigned custom template), so it is not read twice. */
    public function remember(Document $row): void
    {
        $this->rows[$row->handle] = $row;
    }

    /** Whether the theme's tables exist (false before the theme is installed). */
    public function ready(): bool
    {
        return $this->tableExists();
    }

    public function forget(): void
    {
        $this->rows = [];
    }

    private function tableExists(): bool
    {
        if (! $this->tableChecked) {
            $this->tableChecked = true;
            try {
                $this->hasTable = Schema::hasTable('xelga_documents');
            } catch (\Throwable) {
                $this->hasTable = false;
            }
        }

        return $this->hasTable;
    }
}
