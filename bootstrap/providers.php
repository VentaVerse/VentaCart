<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,

    // Extensions
    Extensions\PhilippineAddress\PhilippineAddressServiceProvider::class,
    Extensions\Banners\BannerServiceProvider::class,
    Extensions\CourierFulfillment\CourierFulfillmentServiceProvider::class,
];
