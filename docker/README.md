# Venta — Docker deployment

This directory holds the container build context for Venta. The runtime
image is built from the root `Dockerfile` and published to GHCR by
`.github/workflows/docker.yml`.

## What's in the image

A single container running:

- **PHP 8.4-FPM** on Alpine
- **nginx** fronting PHP-FPM
- **crond** firing Laravel's scheduler every minute
- **queue:work** processing the database queue
- All four managed by **supervisord**

Storage drivers used: **database** (sessions, cache, queue). No Redis dependency.

## Files in this directory

| File | Purpose |
|---|---|
| `nginx.conf` | nginx config — serves Laravel from `/var/www/html/public` |
| `php-fpm.conf` | PHP-FPM pool config — listens on `127.0.0.1:9000` |
| `php.ini` | Production-oriented PHP settings (memory, uploads, timezone) |
| `opcache.ini` | OPcache + JIT settings |
| `supervisord.conf` | Process manager — runs php-fpm, nginx, crond, queue worker |
| `crontab` | One line: `* * * * * php artisan schedule:run` |
| `entrypoint.sh` | Container start sequence (wait for DB → migrate → cache → exec supervisord) |

## Required environment variables

Set these on the container (Coolify / AgilaCP / docker-compose):

```
APP_KEY=base64:...        # generated once per tenant, persisted
APP_URL=https://merchant.example.com
DB_HOST=db                # service name of the MariaDB container
DB_PORT=3306
DB_DATABASE=tenant_db
DB_USERNAME=tenant_user
DB_PASSWORD=...
```

## Optional environment variables

```
VENTA_RUN_SEEDERS=true            # run db:seed once on first boot (set to false / unset afterwards)
```

The first admin account is created in the browser: open the admin after the
first boot and fill in the setup page. No admin is seeded.

## Building locally

```sh
docker build -t venta:local .
docker compose up -d
```

The bundled `docker-compose.yml` runs Venta + MariaDB locally with sensible
defaults. Browse to `http://localhost:8080`.

## Production deployment

The CI workflow publishes images to:

```
ghcr.io/pao2pao16/venta:latest             # tip of main
ghcr.io/pao2pao16/venta:<branch>            # tip of branch (e.g., feat/dockerize)
ghcr.io/pao2pao16/venta:<sha>               # specific commit
ghcr.io/pao2pao16/venta:v1.0.0              # semver tag
```

Coolify pulls one of these per tenant. AgilaCP provisions the tenant
(MariaDB container, env vars, domain) and tells Coolify which image tag
to deploy.

## Updating

The image is immutable. To deploy a new Venta version to all tenants:

1. Push a tag (e.g., `git tag v1.0.1 && git push --tags`)
2. CI builds and publishes `ghcr.io/pao2pao16/venta:v1.0.1`
3. AgilaCP triggers a redeploy on each tenant with the new tag
4. Coolify rolls each tenant's container atomically — zero downtime per tenant

Tenant data (DB + storage volumes) is preserved across redeploys.
