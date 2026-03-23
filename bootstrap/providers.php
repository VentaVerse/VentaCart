<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ThemeServiceProvider::class,

    // Extensions
    Extensions\PhilippineAddress\PhilippineAddressServiceProvider::class,
    Extensions\BannerCarousel\BannerCarouselServiceProvider::class,
];
