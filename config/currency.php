<?php

return [

    'base' => 'TZS',

    /*
    | Fallback rates (1 TZS in the target currency) used when the live rate
    | API cannot be reached. The live rate refreshes automatically.
    */
    'tzs_to_usd' => env('CURRENCY_TZS_TO_USD', 0.00038),

    'tzs_to_cny' => env('CURRENCY_TZS_TO_CNY', 0.00255),

    'cache_ttl_hours' => 12,
];
