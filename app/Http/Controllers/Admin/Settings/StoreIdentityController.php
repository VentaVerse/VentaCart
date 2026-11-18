<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIdentityRequest;
use App\Models\Setting;
use App\Models\Store;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The store's name, logo and favicon: the identity every surface wears.
 * The same save carries the store's contact details (email, phone and
 * address), which live on the store record rather than in settings.
 *
 * Marks are uploaded or chosen from the media library. Either way the file
 * is copied under one stable directory on the public disk and the settings
 * row holds that path, so deleting a library image never breaks the store. Replacing a mark deletes the one it
 * replaces, and removing it deletes the file outright, so the directory
 * only ever holds what is currently in use.
 */
class StoreIdentityController extends Controller
{
    /** Where the marks live on the public disk. */
    private const DIRECTORY = 'identity';

    /** The settings keys this screen owns, in the order the card shows them. */
    private const ASSETS = ['logo', 'favicon'];

    public function update(StoreIdentityRequest $request): RedirectResponse
    {
        $contact = $request->contact();
        $store = null;

        if ($contact !== []) {
            $store = Store::query()->find((int) config('app.store_id', 1));

            // Checked before any write, so a failed save changes nothing.
            if ($store === null) {
                return back()->withInput()->with('error', 'The store record is missing, so its contact details cannot be saved.');
            }
        }

        $store?->update($contact);

        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'site_name'],
            ['value' => trim((string) $request->input('site_name'))]
        );

        foreach (self::ASSETS as $asset) {
            $source = $request->sourceFor($asset);
            $extension = $request->extensionFor($asset);

            if ($source !== null && $extension !== null) {
                $this->replace($asset, $source, $extension);
            }
        }

        // v doctrine: the General page saves identity AND its plain
        // setting groups in the same submit (groups[general][...]).
        // Allowlisted to the one group this page owns: anything else
        // must go through SettingController::updateGroups and its
        // encryption/secret pipeline, never around it.
        if (is_array($request->input('groups'))) {
            foreach ($request->input('groups') as $group => $data) {
                if ($group !== 'general' || !is_array($data)) {
                    continue;
                }
                foreach ($data as $key => $value) {
                    Setting::updateOrCreate(
                        ['group' => $group, 'key' => $key],
                        ['value' => is_array($value) ? json_encode($value) : $value]
                    );
                }
            }

            return $this->done('General settings saved.');
        }

        return $this->done('Identity updated.');
    }

    public function destroy(Request $request, string $asset)
    {
        abort_unless(in_array($asset, self::ASSETS, true), 404);

        $this->deleteFile($this->currentPath($asset));
        $this->write($asset, null);

        // v removes in place: answer JSON so the page never navigates
        // and typed edits elsewhere on it survive the removal.
        if ($request->wantsJson()) {
            app(ThemeService::class)->clearCache();
            StoreIdentity::flush();

            return response()->json(['ok' => true, 'message' => ucfirst($asset).' removed.']);
        }

        return $this->done(ucfirst($asset).' removed.');
    }

    /**
     * Copy the checked file into the identity folder, record it, then drop
     * the one it displaced. The source is an upload or a library image;
     * either way the store keeps its own copy.
     */
    private function replace(string $asset, string $source, string $extension): void
    {
        $previous = $this->currentPath($asset);

        // The name is ours, not the client's: a random stem defeats both
        // guessing and the double-extension trick, and the extension comes
        // from the bytes the request already inspected.
        $path = Storage::disk('public')->putFileAs(
            self::DIRECTORY,
            new File($source),
            $asset.'-'.Str::random(24).'.'.$extension
        );

        if ($path === false || $path === '') {
            return;
        }

        $this->write($asset, $path);

        if ($previous !== $path) {
            $this->deleteFile($previous);
        }
    }

    private function currentPath(string $asset): ?string
    {
        return Setting::where('group', 'identity')->where('key', $asset)->value('value');
    }

    private function write(string $asset, ?string $path): void
    {
        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => $asset],
            ['value' => $path]
        );
    }

    /**
     * Delete a stored mark. Paths here are written by this controller, but
     * the row is old enough to predate it, so traversal is refused rather
     * than trusted.
     */
    private function deleteFile(?string $path): void
    {
        if (! is_string($path)) {
            return;
        }

        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function done(string $message): RedirectResponse
    {
        app(ThemeService::class)->clearCache();
        StoreIdentity::flush();

        return redirect()
            ->route('admin.settings.section', 'general')
            ->with('success', $message);
    }
}
