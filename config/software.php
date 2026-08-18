<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Software Delivery Settings
    |--------------------------------------------------------------------------
    |
    | access_minutes: how long (in minutes) the buyer can see the product key
    | and download the software file after the payment is approved. After this
    | window the delivery locks itself and the admin can re-open it manually.
    |
    */

    'access_minutes' => 20,

    'download_disk' => 'private',
];