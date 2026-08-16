<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIdentityRequest;
use App\Models\Setting;
use App\Services\ThemeService;
use App\Support\StoreIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The store's name, logo and favicon: the identity every surface wears.
 *
 * Uploads land under one stable directory on the public disk and the
 * settings row holds the path. Replacing a mark deletes the one it
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
        Setting::updateOrCreate(
            ['group' => 'identity', 'key' => 'site_name'],
            ['value' => trim((string) $request->input('site_name'))]
        );

        foreach (self::ASSETS as $asset) {
            $file = $request->file($asset);
            $extension = $request->extensionFor($asset);

            if ($file instanceof UploadedFile && $extension !== null) {
                $this->replace($asset, $file, $extension);
            }
        }

        // v3 doctrine: the General page saves identity AND its plain
        // setting groups in the same submit (groups[general][...]).
        // None of these keys are encrypted.
        if (is_array($request->input('groups'))) {
            foreach ($request->input('groups') as $group => $data) {
                if (!is_array($data)) {
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

    public function destroy(string $asset): RedirectResponse
    {
        abort_unless(in_array($asset, self::ASSETS, true), 404);

        $this->deleteFile($this->currentPath($asset));
        $this->write($asset, null);

        return $this->done(ucfirst($asset).' removed.');
    }

    /** Store the new file, record it, then drop the one it displaced. */
    private function replace(string $asset, UploadedFile $file, string $extension): void
    {
        $previous = $this->currentPath($asset);

        // The name is ours, not the client's: a random stem defeats both
        // guessing and the double-extension trick, and the extension comes
        // from the bytes the request already inspected.
        $path = $file->storeAs(
            self::DIRECTORY,
            $asset.'-'.Str::random(24).'.'.$extension,
            'public'
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
