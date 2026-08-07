<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,

    // Extension kernel: boots every installed+enabled extension's own
    // provider from the `extensions` DB registry. Individual extensions
    // (e.g. banners) are never listed here — see App\Services\ExtensionManager.
    App\Providers\ExtensionServiceProvider::class,
];
