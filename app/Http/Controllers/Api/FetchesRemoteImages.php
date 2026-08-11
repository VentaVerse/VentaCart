<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnsafeUrlException;
use App\Services\SafeUrlFetcher;
use Illuminate\Support\Facades\Storage;

/**
 * Shared remote-image fetching for the ERP push endpoints.
 *
 * Every ERP endpoint that pulls a caller-supplied image URL goes through
 * here so all of them share one SSRF guard, one set of limits, and one
 * vocabulary of failure reasons. The reasons matter: they are copied
 * verbatim into the ERP's own log, so each one has to explain itself to a
 * person who will never read this file.
 */
trait FetchesRemoteImages
{
    protected const IMAGE_MAX_BYTES = 5 * 1024 * 1024;

    protected const IMAGE_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Fetch one image URL through the SSRF guard.
     *
     * @param  string[]|null  $allowedTypes  null accepts any successful response
     * @return array{ok: bool, body?: string, mime?: string, reason?: string}
     */
    protected function fetchRemoteImage(string $url, ?array $allowedTypes = null, int $maxBytes = self::IMAGE_MAX_BYTES): array
    {
        try {
            $response = app(SafeUrlFetcher::class)->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => $this->describeFetchFailure($e, $url)];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'reason' => 'The image URL answered HTTP '.$response->status().', so there was nothing to download.',
            ];
        }

        $mime = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));

        if ($allowedTypes !== null && ! in_array($mime, $allowedTypes, true)) {
            return [
                'ok' => false,
                'reason' => 'The URL served content type "'.($mime !== '' ? $mime : 'none').'", which is not an accepted image format. Accepted: '.implode(', ', $allowedTypes).'.',
            ];
        }

        $body = $response->body();
        $bytes = strlen($body);

        if ($bytes > $maxBytes) {
            return [
                'ok' => false,
                'reason' => 'The image is '.$this->formatMegabytes($bytes).', over the '.$this->formatMegabytes($maxBytes).' limit.',
            ];
        }

        return ['ok' => true, 'body' => $body, 'mime' => $mime];
    }

    /**
     * Turn a fetch failure into a sentence an operator reading the ERP's log
     * can act on, rather than an exception class name or an internal phrase.
     */
    protected function describeFetchFailure(\Throwable $e, string $url): string
    {
        if (! $e instanceof UnsafeUrlException) {
            return 'The image could not be downloaded: '.$e->getMessage();
        }

        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $named = $host !== '' ? "host '{$host}'" : 'the host in the URL';
        $message = $e->getMessage();

        if (str_contains($message, 'resolves to a disallowed address')) {
            return "Refused for safety: {$named} is a private or internal network address (LAN, loopback, "
                .'link-local or reserved range) that this store cannot and will not fetch from. Images have to be '
                .'served from a URL reachable on the public internet, not from inside the ERP network.';
        }

        if (str_contains($message, 'could not be resolved')) {
            return "Refused for safety: {$named} has no public DNS address. Check the hostname is correct and "
                .'publicly resolvable.';
        }

        if (str_contains($message, 'scheme')) {
            return 'Refused for safety: only http:// and https:// image URLs are accepted.';
        }

        if (str_contains($message, 'Malformed')) {
            return 'Refused for safety: the URL is malformed or incomplete.';
        }

        if (str_contains($message, 'Too many redirects')) {
            return 'Refused for safety: the URL redirected too many times, so the real image was never reached.';
        }

        if (str_contains($message, 'no Location header')) {
            return 'Refused for safety: the URL answered with a redirect that had no destination.';
        }

        return 'Refused for safety: '.$message;
    }

    /**
     * Remove ONLY the files the current request wrote.
     *
     * Catalog media is shared between products, so deleting by path is safe
     * for exactly one class of path: one this request minted itself. Every
     * path handed here was written under a fresh Str::random(40) filename
     * that existed nowhere before the request began, so no pre-existing file
     * and no other product's image can be behind it. The row check below is
     * belt and braces: after the rollback nothing should reference these
     * paths, and on the off chance something does, the file is left alone.
     *
     * @param  array<int, string>  $paths
     * @param  callable(string): bool  $isReferenced
     */
    protected function discardWrittenFiles(array $paths, callable $isReferenced): void
    {
        foreach (array_unique($paths) as $path) {
            if ($isReferenced($path)) {
                continue;
            }

            Storage::disk('public')->delete($path);
        }
    }

    protected function formatMegabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.').' MB';
    }
}
