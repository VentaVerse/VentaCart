<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Themes\Xelga\Extensions\Extensions;

/**
 * An extension's stylesheet served as the file it ships, for a store whose
 * build does not have it (an extension installed from an upload). Only
 * the one file the extension's xelga.json names is served, and only while
 * the extension is switched on. Its address carries a fingerprint of the
 * file, so it is cached for a year and a new version gets a new address.
 */
class ExtensionStylesheetController extends Controller
{
    public function show(string $extension, string $hash): Response|RedirectResponse
    {
        $part = Extensions::current()->get($extension);
        $file = $part?->stylesheetFile;
        $current = $part?->stylesheetHash();
        if ($file === null || $current === null || realpath($file) !== $file) {
            abort(404);
        }

        // An old address (a page cached before an update) goes to the current one.
        if (! hash_equals($current, $hash)) {
            return redirect()->route('xelga.extension-css', ['extension' => $extension, 'hash' => $current], 302)
                ->header('Cache-Control', 'no-cache');
        }

        return response((string) file_get_contents($file), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
