<?php

namespace App\Http\Middleware;

use App\Models\NotFoundLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs 404 responses to `not_found_logs` so the admin can see what URLs are
 * being crawled but missing — typical sources are old indexed URLs from a
 * prior platform, broken inbound links, or typos.
 *
 * Dedupes by exact path; each unique path gets one row with hit count and
 * last-seen metadata. Skips paths that look like asset/probe noise so the
 * log stays useful.
 */
class LogNotFound
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        if (! Schema::hasTable('not_found_logs')) {
            return $response;
        }

        $path = '/' . ltrim($request->path(), '/');

        if ($this->shouldSkip($path)) {
            return $response;
        }

        $now = now();
        $log = NotFoundLog::where('path', $path)->first();

        if ($log) {
            NotFoundLog::where('id', $log->id)->update([
                'hits' => $log->hits + 1,
                'last_hit_at' => $now,
                'last_query' => $request->getQueryString() ?: null,
                'last_referer' => $request->headers->get('referer'),
                'last_user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            ]);
        } else {
            NotFoundLog::create([
                'path' => $path,
                'hits' => 1,
                'first_hit_at' => $now,
                'last_hit_at' => $now,
                'last_query' => $request->getQueryString() ?: null,
                'last_referer' => $request->headers->get('referer'),
                'last_user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
            ]);
        }

        return $response;
    }

    /**
     * Skip noise paths — assets, well-known probes, common bot scans.
     * These would otherwise dominate the 404 log and obscure real signal.
     */
    private function shouldSkip(string $path): bool
    {
        $skipPatterns = [
            '#^/\.well-known/#',
            '#^/(wp-admin|wp-login|wordpress|xmlrpc\.php)#',
            '#\.(php|asp|aspx|jsp|cgi|env|sql|bak|swp|log|conf|ini|yml|yaml|git|htaccess)$#i',
            '#^/(favicon\.ico|apple-touch-icon|robots\.txt)$#',
            '#^/(storage|build|hot|vendor)/#',
            '#\.(css|js|png|jpe?g|gif|svg|ico|webp|woff2?|ttf|eot|map)$#i',
        ];

        foreach ($skipPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
