<?php

namespace Themes\Xelga\Preview;

use App\Http\Middleware\RegisterThemeViews;
use App\Services\ThemeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Themes\Xelga\XelgaServiceProvider;

/**
 * Decides, per storefront request, whether drafts are shown. All of these
 * must hold:
 *
 * 1. an admin is signed in and holds the theme permission;
 * 2. that admin's preview window is open;
 * 3. the request carries the window's nonce (?xelga_preview=...), or it is
 *    the editor's frame navigating (Sec-Fetch-Dest: iframe).
 *
 * The third rule keeps an admin's ordinary tabs on the published store
 * while the editor is open. A shopper can never pass the first. Preview
 * answers are never cached, never indexed and only framed by the store.
 */
final class PreviewMode
{
    public const QUERY = 'xelga_preview';

    private static bool $active = false;

    public static function active(): bool
    {
        return self::$active;
    }

    /** For tests and long-running workers: forget the last request's answer. */
    public static function reset(): void
    {
        self::$active = false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Per-request state starts empty, also in a long-running worker.
        \Themes\Xelga\Rendering\Storefront::forgetRequestState();
        self::$active = $this->qualifies($request);

        if (! self::$active) {
            return $next($request);
        }

        // The preview draws with Xelga even while another theme is live, so
        // a merchant can prepare it before switching.
        $themes = app(ThemeService::class);
        $overlaid = $themes->getActiveTheme() !== XelgaServiceProvider::SLUG;
        if ($overlaid) {
            $themes->overlay(['identity' => ['active_theme' => XelgaServiceProvider::SLUG]]);
            RegisterThemeViews::register();
        }

        try {
            $response = $next($request);
        } finally {
            self::$active = false;
            // The theme service and the view finder live as long as the
            // process. A long-running worker would otherwise serve the next
            // shopper with Xelga: drop the overlay and put the live theme's
            // views first again. The response is already rendered.
            if ($overlaid) {
                $this->restoreLiveTheme($themes);
            }
        }

        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $this->keepPreviewThroughRedirect($request, $response);

        return $response;
    }

    /**
     * A storefront page that redirects (checkout or the account sending a
     * guest to sign in) must stay in the preview. Browsers on plain http send
     * no frame header, so the next page would only be a preview if it carries
     * the nonce. It is added only to addresses on this store, never to
     * another site.
     */
    private function keepPreviewThroughRedirect(Request $request, Response $response): void
    {
        if (! $response->isRedirection() || ! is_string($location = $response->headers->get('Location'))) {
            return;
        }
        $nonce = PreviewSession::nonce();
        if ($nonce === null || ! $this->staysOnThisStore($request, $location)) {
            return;
        }

        // Appended to the address as written, never rebuilt, so nothing in
        // it is reinterpreted and no host can be invented.
        [$address, $fragment] = array_pad(explode('#', $location, 2), 2, null);
        if (preg_match('/[?&]' . self::QUERY . '=/', $address)) {
            return;
        }
        $address .= (str_contains($address, '?') ? '&' : '?') . self::QUERY . '=' . rawurlencode($nonce);
        $response->headers->set('Location', $address . ($fragment !== null ? '#' . $fragment : ''));
    }

    /**
     * Only two shapes are trusted: a path on this store ("/x", never "//x"
     * or "/\x"), or an absolute http(s) address whose host and port are this
     * request's. Anything browsers and PHP might read differently (back
     * slashes, control characters, leading spaces, user info, a scheme with
     * no host) is refused.
     */
    private function staysOnThisStore(Request $request, string $location): bool
    {
        if ($location === '' || str_contains($location, '\\') || preg_match('/[\x00-\x20\x7f]/', $location)) {
            return false;
        }
        if ($location[0] === '/') {
            return ! isset($location[1]) || ($location[1] !== '/');
        }
        if (! preg_match('#^https?://#i', $location)) {
            return false;
        }
        $parts = parse_url($location);
        if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return strcasecmp($parts['host'], $request->getHost()) === 0 && (int) $port === (int) $request->getPort();
    }

    private function restoreLiveTheme(ThemeService $themes): void
    {
        try {
            $themes->reload();
            RegisterThemeViews::register();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function qualifies(Request $request): bool
    {
        if (! $request->isMethodSafe() || $this->isAdmin($request)) {
            return false;
        }

        $nonce = $request->query(self::QUERY);
        $framed = $request->headers->get('Sec-Fetch-Dest') === 'iframe';

        if (is_string($nonce) && $nonce !== '') {
            return PreviewSession::valid($nonce);
        }

        return $framed && PreviewSession::valid();
    }

    private function isAdmin(Request $request): bool
    {
        $prefix = trim((string) config('admin.prefix', 'admin'), '/');

        return $request->is($prefix) || $request->is($prefix . '/*');
    }
}
