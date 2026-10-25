<?php

// Which proxies' X-Forwarded-* headers to believe: the client address, the
// scheme (https) and the host they report. Read by Laravel's TrustProxies
// middleware.
//
// TRUSTED_PROXIES, comma separated:
//
//   (empty)          none, the default. The connecting address is the
//                    client, and X-Forwarded-* headers are ignored. Right
//                    for a store that is reached directly.
//   private_ranges   any address in a private range (10.0.0.0/8,
//                    172.16.0.0/12, 192.168.0.0/16, loopback and their IPv6
//                    equivalents). Right for a proxy on the same machine or
//                    Docker network: Traefik, Caddy, nginx.
//   10.0.0.2,10.0.1.0/24   those addresses or ranges only.
//   *                trust whoever connects. Only when nothing but the proxy
//                    can reach the store, since anyone else could then claim
//                    any address and step around rate limits.
//
// See docs/deployment-proxies.md.

return [
    'proxies' => App\Support\TrustedProxies::parse(env('TRUSTED_PROXIES')),
];
