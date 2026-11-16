<?php

/*
|--------------------------------------------------------------------------
| Order placement
|--------------------------------------------------------------------------
|
| A cart becomes one order, however many times Place order is submitted.
| These timings bound how long one placement may hold the cart, and how
| long a repeated submit is still taken as a repeat of the order it made.
|
*/

return [

    'placement' => [

        // How long one placement holds its cart lock. Placing an order can
        // wait on shipping rate providers and on a payment gateway (which
        // allows itself 30 seconds), so this stays well above both. An
        // unpaid order with no payment page yet counts as still being
        // placed for this long; after that the placement is taken as having
        // died, and the cart may place again.
        'lock_seconds' => 90,

        // How long a second submit waits for the first to finish before it
        // is answered from what the database shows.
        'wait_seconds' => 40,

        // How long after an order is placed a repeated submit for the same
        // cart is sent to that order (its confirmation, or its payment page
        // while it is unpaid) instead of placing another.
        'repeat_window_seconds' => 600,

    ],

];
