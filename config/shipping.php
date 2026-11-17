<?php

return [

    /*
    | Shipping quotes one shopper may ask for per minute: the cart's
    | estimate and checkout's delivery options together. Each quote asks
    | every shipping provider, and some of them call a courier's API.
    */
    'quotes_per_minute' => (int) env('SHIPPING_QUOTES_PER_MINUTE', 20),

];
