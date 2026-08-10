<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted fetch hosts
    |--------------------------------------------------------------------------
    |
    | SafeUrlFetcher refuses to fetch any URL resolving to a loopback, private,
    | link-local or otherwise non-public address. That is what stops a caller
    | who can supply a URL (the ERP API, a review import, an admin pasting an
    | image link) from making this server read your internal network or a cloud
    | metadata endpoint.
    |
    | This list names specific `host:port` pairs that are exempt from the IP
    | check. It exists for one situation: a self-hosted install where the store
    | and its ERP sit on the same machine or LAN, so the only address the ERP
    | can offer for its images is a private one.
    |
    | EMPTY IS THE DEFAULT AND THE SAFE STATE. It is deliberately absent from
    | .env.example so that a fresh install is strict and nobody enables it by
    | filling in a blank they found in a template. Set it only on a deployment
    | where you know exactly which host you are trusting.
    |
    | Rules that still apply to an exempt host:
    |   - the scheme allowlist (http/https only)
    |   - the redirect cap
    |   - the IP check on every OTHER hop, so an exempt host cannot redirect
    |     this server onward to an internal address
    |
    | Format: comma separated, exact `host:port`, no wildcards, no CIDR.
    | Example: VENTA_TRUSTED_FETCH_HOSTS=192.168.0.10:8000,erp.internal:80
    |
    */

    'trusted_fetch_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('VENTA_TRUSTED_FETCH_HOSTS', ''))
    ))),

];
