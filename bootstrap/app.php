<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust X-Forwarded-* headers from any proxy. Required when Venta runs
        // behind a reverse proxy (Traefik on Coolify, Cloudflare, nginx, etc.)
        // — without this, request()->isSecure() returns false even on HTTPS,
        // and Laravel generates http:// URLs for assets, which browsers block
        // as mixed content on an HTTPS page. The container should only ever
        // be reachable via its proxy, so trusting all forwarded headers is safe.
        $middleware->trustProxies(at: '*');

        // The storefront currency selector sets a plain (JS) `currency` cookie, so
        // it must be exempt from Laravel's cookie encryption or it can't be read.
        $middleware->encryptCookies(except: ['currency']);

        $middleware->alias([
            'customer.auth' => \App\Http\Middleware\CustomerAuthenticated::class,
            'erp.auth' => \App\Http\Middleware\ErpApiAuth::class,
            'noindex' => \App\Http\Middleware\NoIndexResponse::class,
            'admin.session.cap' => \App\Http\Middleware\EnforceAdminSessionCap::class,
        ]);

        // Extension kernel boots first, and must be truly global (not just
        // prepended to the 'web' group): group middleware only runs *after*
        // a route has already matched, which is too late — an unbooted
        // extension's routes wouldn't exist yet for the router to match
        // against. See BootExtensions for the full rationale.
        $middleware->prepend(\App\Http\Middleware\BootExtensions::class);

        // Web requests then go through the redirect lookup first (early exit
        // on 301/410) and then through the 404 logger (records misses for
        // the admin's not-found monitor).
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleRedirects::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\LogNotFound::class,
            // The admin is frameable by itself (the layout editor's panel)
            // and by nobody else, and a framed page stays framed across its
            // own redirects. Storefront responses are left alone.
            \App\Http\Middleware\AdminFraming::class,
            // Every admin route, core or extension, needs the permission its
            // name maps to (config/admin-permissions.php).
            \App\Http\Middleware\EnforceAdminPermission::class,
        ]);
        // After sign-in, so the user is known; before route model binding,
        // so a refused request never loads what it named.
        $middleware->appendToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnforceAdminPermission::class,
        );

        // The active theme's view folder, re-checked per request. The provider
        // already prepends it at boot; this only catches the case where that
        // boot ran before the database was readable (tests, or a restart
        // during a database blip), where it would otherwise stay unregistered
        // for the life of the process. Idempotent and cheap. See the
        // middleware's docblock, and BootExtensions for the same hazard.
        $middleware->web(prepend: [
            \App\Http\Middleware\RegisterThemeViews::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
