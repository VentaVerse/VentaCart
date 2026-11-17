<?php

namespace Themes\Xelga\Rendering;

use App\Services\ThemeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Themes\Xelga\XelgaServiceProvider;

/**
 * The store's "page not found" page, drawn inside the store's header and
 * footer from the not-found template, while Xelga is the theme shoppers
 * see (or the one the editor previews). Anything else keeps core's own
 * answer: JSON callers, the admin, missing files, and every store where
 * Xelga is only installed.
 */
final class NotFoundPage
{
    /** Folders that only ever hold files; a miss there is never a page. */
    private const FILE_FOLDERS = ['build', 'storage', 'vendor'];

    public static function render(NotFoundHttpException $e, Request $request): ?Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }
        if ($request->expectsJson() || self::isAdmin($request) || self::isFile($request)) {
            return null;
        }

        try {
            if (app(ThemeService::class)->getActiveTheme() !== XelgaServiceProvider::SLUG) {
                return null;
            }

            return response()->view('xelga::not-found', [], 404);
        } catch (\Throwable $error) {
            // A store that cannot draw the page still answers 404 with core's own page.
            report($error);

            return null;
        }
    }

    /**
     * A missing image, script or other file gets core's small static page:
     * drawing the whole store for every broken asset or probe for a .php
     * file would cost a full page render each time.
     */
    public static function isFile(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $first = strtolower(explode('/', $path, 2)[0]);
        if (in_array($first, self::FILE_FOLDERS, true)) {
            return true;
        }

        // A file extension has at least one letter, so "size-2.5" stays a page.
        $last = basename($path);

        return preg_match('/\.[a-z0-9]*[a-z][a-z0-9]*$/i', $last) === 1;
    }

    private static function isAdmin(Request $request): bool
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        return $request->is($prefix) || $request->is($prefix . '/*');
    }
}
