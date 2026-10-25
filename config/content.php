<?php

return [
    // Hosts whose players may be embedded (an iframe) in a product
    // description, page or blog post by an admin without storefront.code,
    // comma separated, e.g. a video service's player host. Empty: only a
    // super admin can embed frames. Only https addresses on these exact
    // hosts are kept, and every frame is sandboxed.
    'embed_hosts' => env('RICH_TEXT_EMBED_HOSTS', ''),
];
