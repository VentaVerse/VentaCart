<?php

return [
    'spx' => [
        /*
         * How many times one SPX booking may be updated (1.7 Update Order)
         * while it is still pending pickup. SPX's documentation states 3;
         * kept here so it can follow SPX if that changes, without a release.
         */
        'booking_edits_max' => (int) env('SPX_BOOKING_EDITS_MAX', 3),
    ],
];
