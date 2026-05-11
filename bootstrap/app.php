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

        $middleware->alias([
            'customer.auth' => \App\Http\Middleware\CustomerAuthenticated::class,
            'erp.auth' => \App\Http\Middleware\ErpApiAuth::class,
            'noindex' => \App\Http\Middleware\NoIndexResponse::class,
        ]);

        // Web requests go through the redirect lookup first (early exit on
        // 301/410) and then through the 404 logger (records misses for the
        // admin's not-found monitor).
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleRedirects::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\LogNotFound::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
