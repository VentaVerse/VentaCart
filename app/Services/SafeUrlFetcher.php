<?php

namespace App\Services;

use App\Exceptions\UnsafeUrlException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SSRF-hardened replacement for `Http::get($userSuppliedUrl)`.
 *
 * Any endpoint that downloads a file from a caller-supplied URL (product
 * image import, ERP image sync, review image sync, …) is a potential
 * Server-Side Request Forgery vector: a malicious URL can point at the
 * loopback interface, RFC1918 space, or a cloud metadata endpoint
 * (169.254.169.254) to make the server issue requests on the attacker's
 * behalf. This class centralizes the guard so every such call site shares
 * the same validation instead of re-implementing (and potentially
 * mis-implementing) it.
 *
 * Defense in depth, in order:
 *   1. Scheme allowlist (http/https only — no file://, ftp://, gopher://, …).
 *   2. Resolve the host to every A + AAAA record and reject if any of them
 *      is loopback/private/link-local/reserved. Literal IP hosts (including
 *      bracketed IPv6 literals) are validated directly.
 *   3. Redirects are NOT auto-followed by the HTTP client. Each redirect
 *      hop's Location is re-validated through the same checks before it is
 *      followed, up to MAX_REDIRECTS hops. Without this, a URL that passes
 *      validation could 302 to an internal address and the underlying
 *      client would happily follow it.
 *
 * Residual risk (documented, not fully closed here): steps 2/3 validate the
 * IP at request time, but the HTTP client resolves the hostname again when
 * it actually connects. A DNS answer that changes between our check and the
 * client's connect (DNS rebinding) is not pinned against. Closing that gap
 * fully requires connecting directly to the validated IP (e.g. a curl
 * CURLOPT_RESOLVE pin) — flagged for follow-up, not implemented here to
 * keep this change narrowly scoped to the flagged vulnerability.
 */
class SafeUrlFetcher
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    private const MAX_REDIRECTS = 3;

    /**
     * CIDR ranges that filter_var(..., FILTER_FLAG_NO_PRIV_RANGE |
     * FILTER_FLAG_NO_RES_RANGE) does NOT reject but which are still
     * unsuitable destinations for a server-initiated fetch.
     */
    private const EXTRA_BLOCKED_RANGES = [
        '100.64.0.0/10',   // RFC 6598 shared address space (carrier-grade NAT; used by some cloud metadata proxies)
        '0.0.0.0/8',        // "this network" / unspecified
    ];

    /**
     * Fetch a caller-supplied URL, rejecting anything that resolves to a
     * non-public address at any hop.
     *
     * @throws UnsafeUrlException if the URL (or a redirect target) fails validation
     */
    public function get(string $url, int $timeout = 15): Response
    {
        $currentUrl = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $this->assertSafeUrl($currentUrl);

            $response = Http::withOptions(['allow_redirects' => false])
                ->timeout($timeout)
                ->get($currentUrl);

            $status = $response->status();

            if ($status < 300 || $status >= 400) {
                return $response;
            }

            $location = $response->header('Location');

            if (empty($location)) {
                throw new UnsafeUrlException("Redirect response ({$status}) had no Location header.");
            }

            $currentUrl = $this->resolveRedirectTarget($currentUrl, $location);
        }

        throw new UnsafeUrlException('Too many redirects (max '.self::MAX_REDIRECTS.').');
    }

    /**
     * Validate a URL's scheme and every IP its host resolves to. Public so
     * callers can pre-validate (e.g. fail fast in a Form Request) without
     * issuing the fetch.
     *
     * @throws UnsafeUrlException
     */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new UnsafeUrlException('Malformed or incomplete URL.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new UnsafeUrlException("URL scheme '{$scheme}' is not allowed.");
        }

        $host = trim($parts['host'], '[]');
        $ips = $this->resolveHostIps($host);

        if (empty($ips)) {
            throw new UnsafeUrlException("Host '{$host}' could not be resolved to a public address.");
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                throw new UnsafeUrlException("Host '{$host}' resolves to a disallowed address.");
            }
        }
    }

    /**
     * Resolve a hostname to every IPv4 + IPv6 address it answers with.
     * Literal IP hosts (v4 or v6) are returned as-is without a DNS lookup.
     *
     * @return string[]
     */
    protected function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        $aRecords = @dns_get_record($host, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        $aaaaRecords = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    protected function isPublicIp(string $ip): bool
    {
        // filter_var's private/reserved-range flags treat an IPv6 address
        // that merely *carries* an IPv4 address (NAT64 64:ff9b::/96, or
        // IPv4-mapped ::ffff:0:0/96) as opaque IPv6 and never look inside
        // it — so 64:ff9b::a9fe:a9fe (= 169.254.169.254) and
        // ::ffff:127.0.0.1 (= 127.0.0.1) both pass as "public". Unwrap the
        // embedded IPv4 first and validate *that* against the same rules.
        $embedded = $this->extractEmbeddedIpv4($ip);
        if ($embedded !== null) {
            $ip = $embedded;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED_RANGES as $range) {
            if ($this->ipInCidr($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    /**
     * If $ip is an IPv6 address in the NAT64 well-known prefix
     * (64:ff9b::/96) or the IPv4-mapped range (::ffff:0:0/96), return the
     * embedded IPv4 address in dotted-quad form. Returns null for plain
     * IPv6 or IPv4 input.
     */
    protected function extractEmbeddedIpv4(string $ip): ?string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return null;
        }

        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return null;
        }

        $prefixes = [
            "\x00\x64\xff\x9b\x00\x00\x00\x00\x00\x00\x00\x00", // 64:ff9b::/96 (NAT64 well-known prefix)
            "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff", // ::ffff:0:0/96 (IPv4-mapped)
        ];

        foreach ($prefixes as $prefix) {
            if (substr($bin, 0, 12) === $prefix) {
                $v4 = inet_ntop(substr($bin, 12, 4));

                return $v4 === false ? null : $v4;
            }
        }

        return null;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);

        return (substr($ipBin, $bytes, 1) & $mask) === (substr($subnetBin, $bytes, 1) & $mask);
    }

    /**
     * Resolve a redirect's Location header (absolute or relative) against
     * the URL that produced it.
     */
    protected function resolveRedirectTarget(string $baseUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'http';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$port}{$location}";
        }

        $basePath = $base['path'] ?? '/';
        $dir = rtrim(dirname($basePath), '/');

        return "{$scheme}://{$host}{$port}{$dir}/{$location}";
    }
}
