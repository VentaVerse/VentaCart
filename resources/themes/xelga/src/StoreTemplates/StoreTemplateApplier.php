<?php

namespace Themes\Xelga\StoreTemplates;

use App\Models\User;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Themes\Xelga\Documents\ConflictException;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\DocumentPresenter;
use Themes\Xelga\Documents\DocumentWriter;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Documents\MissingDocumentException;
use Themes\Xelga\Documents\ShapeException;

/**
 * Applies a starting store to the draft, and takes it off again.
 *
 * Applying writes drafts only, through DocumentWriter like any editor save,
 * with the draft_version the editor last saw: nothing goes live until
 * Publish, and a draft someone else saved meanwhile stops the whole apply.
 * "Look only" replaces the look groups of the theme settings (colors,
 * fonts, buttons, product cards, layout and spacing); "Look and
 * home page" also replaces the header, footer and home page.
 *
 * Undo puts back the exact drafts from before, once, for the admin who
 * applied, as long as nobody changed those drafts since. Each admin has one
 * way back: a second apply replaces the first one's.
 *
 * A starting store brings a look and pages, never products: categories and
 * products it names resolve against the merchant's own catalog, and a slug
 * the catalog lacks leaves that setting empty.
 */
final class StoreTemplateApplier
{
    use DetectsConcurrencyErrors;

    public const BRING = ['all', 'look'];

    /** How long the way back is kept: about one editing session. */
    public const UNDO_SECONDS = 7200;

    private const UNDO_KEY = 'xelga:store-undo:user:';

    private const BUSY = 'Someone else changed this just now. Try again.';

    public function __construct(
        private readonly DocumentWriter $writer,
    ) {
    }

    /**
     * @param  array<string, int>  $versions  handle => the draft_version the editor last saw
     * @return array{documents: array<string, array>, adjusted: array<string, list<array>>, warnings: list<array>, missing: array<string, list<array>>, undo: string, based_on: array}
     *
     * @throws MissingDocumentException|ShapeException|ConflictException
     */
    public function apply(string $slug, string $bring, array $versions, User $user): array
    {
        $store = StoreTemplates::get($slug);
        if ($store === null) {
            throw new MissingDocumentException('That starting store is not part of the theme.');
        }
        $handles = $bring === 'all' ? [Handles::SETTINGS, ...StoreTemplates::SECTION_HANDLES] : [Handles::SETTINGS];
        $errors = [];
        foreach ($handles as $handle) {
            if (! array_key_exists($handle, $versions)) {
                $errors["versions.{$handle}"] = ['The editor must name the version of every page it replaces.'];
            }
        }
        if ($errors !== []) {
            throw new ShapeException($errors);
        }
        $versions = array_intersect_key($versions, array_flip($handles));

        // A stale editor is refused before any photo is copied.
        $this->checkVersions($this->rows($handles, false), $versions);
        $this->copyPhotos($slug);

        $result = $this->guarded(fn () => DB::transaction(function () use ($store, $slug, $bring, $handles, $versions, $user) {
            $rows = $this->rows($handles, true);
            $this->checkVersions($rows, $versions);

            $out = ['documents' => [], 'adjusted' => [], 'warnings' => [], 'missing' => []];
            $settingsRow = $rows->get(Handles::SETTINGS);
            $current = self::decode($settingsRow?->draft) ?? self::decode($settingsRow?->published) ?? Defaults::content(Handles::SETTINGS);
            $sectionsFrom = $bring === 'all' ? $slug : StoreTemplates::basedOn($current)['sections'];

            $saves = [Handles::SETTINGS => $this->settings($store['documents']['settings'], $current, $slug, $sectionsFrom)];
            if ($bring === 'all') {
                $portable = new Portable($slug);
                foreach (StoreTemplates::SECTION_HANDLES as $handle) {
                    $resolved = $portable->resolve((array) ($store['documents'][$handle] ?? ['schema' => 1, 'order' => [], 'sections' => []]));
                    $saves[$handle] = $resolved['content'];
                    $out['missing'][$handle] = $resolved['missing'];
                }
            }

            // Settings first: the sections are checked against its color schemes.
            foreach ($saves as $handle => $content) {
                $saved = $this->writer->saveDraft($handle, $content, (int) $versions[$handle], $user);
                $out['documents'][$handle] = $saved['document'];
                $out['adjusted'][$handle] = $saved['adjusted'];
                array_push($out['warnings'], ...$saved['warnings']);
            }

            $out['before'] = $rows;

            return $out;
        }));

        // One way back per admin, replaced by each apply.
        $token = Str::random(40);
        Cache::put(self::undoKey($user), [
            'token' => $token,
            'user_id' => (int) $user->id,
            'store' => $slug,
            'before' => $this->snapshot($handles, $result['before']),
            'after' => array_map(fn (Document $d) => (int) $d->draft_version, $result['documents']),
        ], self::UNDO_SECONDS);

        $this->audit('store-template', $user, $slug, array_map(fn (Document $d) => ['draft_version' => (int) $d->draft_version], $result['documents']), ['bring' => $bring]);

        return [
            'documents' => array_map(fn (Document $d) => DocumentPresenter::state($d->handle, $d), $result['documents']),
            'adjusted' => $result['adjusted'],
            'warnings' => $result['warnings'],
            'missing' => $result['missing'],
            'undo' => $token,
            'based_on' => StoreTemplates::basedOn($result['documents'][Handles::SETTINGS]->draft),
        ];
    }

    /**
     * Puts back the drafts from before an apply.
     *
     * Each draft goes back through the same checks as a save, keeping its
     * version history, base version and author as they were.
     *
     * @return array{documents: array<string, array>, store: string}
     *
     * @throws MissingDocumentException|ConflictException|ShapeException
     */
    public function undo(string $token, User $user): array
    {
        $saved = Cache::get(self::undoKey($user));
        if (! is_array($saved) || ! is_string($saved['token'] ?? null) || ! hash_equals($saved['token'], $token)
            || (int) ($saved['user_id'] ?? 0) !== (int) $user->id) {
            throw new MissingDocumentException('There is nothing to undo. The draft stays as it is.');
        }

        $handles = array_keys((array) $saved['before']);
        $documents = $this->guarded(fn () => DB::transaction(function () use ($saved, $handles) {
            $rows = $this->rows($handles, true);
            foreach ($handles as $handle) {
                $row = $rows->get($handle);
                if ((int) ($row?->draft_version ?? 0) !== (int) ($saved['after'][$handle] ?? -1)) {
                    throw new ConflictException(
                        'The draft changed after the store was applied, so it was not put back. Your changes are kept.',
                        DocumentPresenter::state($handle, Document::where('handle', $handle)->first()),
                    );
                }
            }

            // Settings first, so the sections are checked against the color schemes put back.
            foreach ($handles as $handle) {
                $before = $saved['before'][$handle];
                $row = $rows->get($handle);
                if (! $before['exists']) {
                    // The apply made this document; without it the store reads the shipped default again.
                    DB::table('xelga_documents')->where('id', $row->id)->delete();
                    continue;
                }
                $draft = $before['draft'];
                if ($draft !== null) {
                    $decoded = json_decode((string) $draft, true);
                    $draft = DocumentWriter::clean($handle, $decoded, [$decoded])['json'];
                }
                DB::table('xelga_documents')->where('id', $row->id)->update([
                    'draft' => $draft,
                    'draft_version' => (int) $row->draft_version + 1,
                    'draft_base_version' => $before['draft_base_version'],
                    'draft_updated_by' => $before['draft_updated_by'],
                    'draft_updated_at' => $before['draft_updated_at'],
                    'updated_at' => now(),
                ]);
            }

            $out = [];
            foreach ($handles as $handle) {
                $out[$handle] = DocumentPresenter::state($handle, Document::where('handle', $handle)->first());
            }

            return $out;
        }));

        Cache::forget(self::undoKey($user));
        $this->audit('store-template-undo', $user, (string) $saved['store'], array_map(fn (array $d) => ['draft_version' => (int) $d['draft_version']], $documents), []);

        return [
            'documents' => $documents,
            'store' => (string) $saved['store'],
        ];
    }

    private static function undoKey(User $user): string
    {
        return self::UNDO_KEY . (int) $user->id;
    }

    /**
     * Two admins applying at once to a store with no saved drafts yet can
     * meet in the database's locks; one of them is turned away. That is a
     * conflict to try again, not a failure.
     *
     * @template T
     *
     * @param  callable(): T  $write
     * @return T
     *
     * @throws ConflictException
     */
    private function guarded(callable $write): mixed
    {
        try {
            return $write();
        } catch (\Throwable $e) {
            if (! $this->causedByConcurrencyError($e)) {
                throw $e;
            }
            Log::warning('Theme store-template write lost a lock race.', ['event' => 'theme_editor', 'theme' => 'xelga', 'error' => $e->getMessage()]);

            throw new ConflictException(self::BUSY, DocumentPresenter::state(Handles::SETTINGS, Document::where('handle', Handles::SETTINGS)->first()));
        }
    }

    /**
     * The settings draft: the merchant's own settings (contact, cart and the
     * like) stay; the look is the store's, wholly, so nothing of the old look
     * lingers in a key the store leaves at its default.
     */
    private function settings(array $look, array $current, string $slug, string $sectionsFrom): array
    {
        $values = (array) ($current['values'] ?? []);
        foreach (StoreTemplates::lookKeys() as $key) {
            unset($values[$key]);
        }
        foreach ((array) ($look['values'] ?? []) as $key => $value) {
            if (in_array($key, StoreTemplates::lookKeys(), true)) {
                $values[$key] = $value;
            }
        }

        return [
            'schema' => 1,
            'values' => $values,
            'schemes' => (array) ($look['schemes'] ?? []),
            'based_on' => ['look' => $slug, 'sections' => $sectionsFrom],
        ];
    }

    /**
     * Copies the store's photos into the media library. A file already there
     * is left alone, so a second apply copies nothing and a photo the
     * merchant replaced under the same name stays theirs. Only real photo
     * files are copied: a link, or a file whose bytes are not a JPG, PNG or
     * WebP, is skipped whatever it is named.
     */
    private function copyPhotos(string $slug): void
    {
        $disk = Storage::disk('public');
        $target = StoreTemplates::mediaDir($slug);
        foreach (glob(StoreTemplates::dir($slug) . '/images/*') ?: [] as $file) {
            $name = basename($file);
            if (is_link($file) || ! is_file($file) || preg_match(StoreTemplates::IMAGE, 'images/' . $name) !== 1 || ! self::isPhoto($file)) {
                continue;
            }
            if (! $disk->exists("{$target}/{$name}")) {
                $disk->put("{$target}/{$name}", (string) file_get_contents($file));
            }
        }
    }

    /** Whether a file's bytes are a JPG, PNG or WebP photo, whatever its name says. */
    private static function isPhoto(string $file): bool
    {
        $info = @getimagesize($file);

        return is_array($info) && in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
    }

    /** @return \Illuminate\Support\Collection<string, object> raw rows, so a draft is put back byte for byte */
    private function rows(array $handles, bool $lock)
    {
        $query = DB::table('xelga_documents')->whereIn('handle', $handles);

        return ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('handle');
    }

    /** A document the editor has not seen saved (no row yet) has version 0. */
    private function checkVersions($rows, array $versions): void
    {
        foreach ($versions as $handle => $version) {
            if ((int) ($rows->get($handle)?->draft_version ?? 0) !== (int) $version) {
                throw new ConflictException(
                    'Someone else saved after you opened the editor, so the store was not applied.',
                    DocumentPresenter::state((string) $handle, Document::where('handle', $handle)->first()),
                );
            }
        }
    }

    /** @return array<string, array{exists: bool, draft: ?string, draft_base_version: ?int, draft_updated_by: ?int, draft_updated_at: ?string}> */
    private function snapshot(array $handles, $rows): array
    {
        $out = [];
        foreach ($handles as $handle) {
            $row = $rows->get($handle);
            $out[$handle] = [
                'exists' => $row !== null,
                'draft' => $row?->draft,
                'draft_base_version' => $row?->draft_base_version === null ? null : (int) $row->draft_base_version,
                'draft_updated_by' => $row?->draft_updated_by === null ? null : (int) $row->draft_updated_by,
                'draft_updated_at' => $row?->draft_updated_at,
            ];
        }

        return $out;
    }

    private static function decode(mixed $json): ?array
    {
        if (is_array($json)) {
            return $json;
        }
        $data = is_string($json) ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * One structured log entry, like publish and discard: who changed the
     * draft from a starting store, and the versions after.
     */
    private function audit(string $action, User $user, string $slug, array $documents, array $extra): void
    {
        Log::info("Theme {$action}.", [
            'event' => 'theme_editor',
            'theme' => 'xelga',
            'action' => $action,
            'user_id' => (int) $user->id,
            'store_template' => $slug,
            'handles' => array_keys($documents),
            'versions' => $documents,
        ] + $extra);
    }
}
