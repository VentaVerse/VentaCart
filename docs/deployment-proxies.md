# Running behind a proxy

A proxy in front of the store (Traefik, Caddy, nginx, a load balancer or a
CDN) terminates HTTPS and passes the request on with `X-Forwarded-For`,
`X-Forwarded-Proto` and `X-Forwarded-Host` headers. Those headers are only
true when the proxy wrote them. A client that reaches the store directly can
send any of them, so they are believed only from the proxies you name.

Getting this wrong has two failure modes:

- **Trusting too little.** The store does not know the visitor came over
  HTTPS, builds `http://` links (blocked as mixed content), and counts every
  visitor as the proxy's one address, so they share one rate limit bucket.
- **Trusting too much.** Anyone can claim any address and step around every
  limit keyed on it: sign-in attempts, contact forms, coupon checks.

## The setting

`TRUSTED_PROXIES` in `.env` (read by `config/trustedproxy.php`):

| Value | Trusts | Use it when |
|---|---|---|
| empty (default) | nobody | the store is reached directly, with no proxy |
| `private_ranges` | 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, loopback, IPv6 private and link-local | the proxy runs on the same machine or Docker network |
| `10.0.0.2,10.0.1.0/24` | those addresses and ranges | you know the proxy's address |
| `*` | whoever connects | never, unless nothing but the proxy can reach the store at all |

Values can be combined: `private_ranges,198.51.100.0/24`.

## Common set-ups

- **Docker image (Coolify, docker compose).** The image's own nginx already
  takes the client address from private addresses only and passes the
  scheme to PHP, so `TRUSTED_PROXIES` stays empty. See `docker/README.md`.
- **Pterodactyl (the VentaCart egg).** The node's Caddy reaches the server
  over Docker's network. Set *Trusted proxies* to `private_ranges` (the egg's
  default). If your Caddy connects through the node's public address instead,
  add that address: `private_ranges,192.0.2.10`.
- **A CDN in front of your proxy.** Add the CDN's published address ranges,
  or let the proxy in front of the store replace `X-Forwarded-For` with the
  CDN's client header, and trust only that proxy.
- **Plain nginx or Apache on the same server.** `private_ranges`, or
  `127.0.0.1`.

## Checking it

Open any storefront page over HTTPS and view the source: links and asset URLs
must start with `https://`. Then, from a machine outside the proxy, send a
request with a made-up `X-Forwarded-For` straight to the store's port (if it
is reachable at all); the store's logs must show your real address.
