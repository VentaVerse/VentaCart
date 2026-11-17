<?php

namespace Themes\Xelga\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Themes\Xelga\Documents\Defaults;
use Themes\Xelga\Documents\Document;
use Themes\Xelga\Documents\Handles;
use Themes\Xelga\Preview\PreviewSession;
use Themes\Xelga\Rendering\Tokens;

/**
 * The tokens stylesheet: the published one by version, cached for a year
 * since a publish changes its address, and the draft one for the preview.
 */
class TokensController extends Controller
{
    public function published(string $version): Response|RedirectResponse
    {
        $row = Document::where('handle', Handles::SETTINGS)->first(['id', 'published', 'published_version']);
        $current = (int) ($row?->published_version ?? 0);

        // An old address (a page cached before a publish) is sent to the
        // current one rather than cached forever under the wrong version.
        if ((int) $version !== $current) {
            return redirect()->route('xelga.tokens', ['version' => $current], 302)
                ->header('Cache-Control', 'no-cache');
        }

        return $this->css(Tokens::css($row?->published ?? Defaults::content(Handles::SETTINGS)))
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }

    public function draft(Request $request): Response
    {
        $nonce = $request->query('n');
        if (! is_string($nonce) || ! PreviewSession::valid($nonce)) {
            abort(404);
        }

        $row = Document::where('handle', Handles::SETTINGS)->first(['id', 'draft', 'published']);

        return $this->css(Tokens::css($row?->draft ?? $row?->published ?? Defaults::content(Handles::SETTINGS)))
            ->header('Cache-Control', 'no-store, private');
    }

    private function css(string $css): Response
    {
        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
