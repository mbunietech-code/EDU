<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home page ticker
    |--------------------------------------------------------------------------
    |
    | The scrolling strip on the public home page. It shows the manual
    | promos below, blended with auto-generated activity lines (new members,
    | open scholarships, recent access grants).
    |
    | Each promo: ['icon' => '🔥', 'text' => '...', 'url' => '/optional-link']
    | 'url' may be null. Keep 'text' short — one line.
    |
    */

    'ticker' => [

        'enabled' => env('HOME_TICKER_ENABLED', true),

        // How fast one full loop takes, in seconds per item on screen.
        // Lower = faster. 4 is a comfortable reading pace.
        'seconds_per_item' => 4,

        // Manual promo messages. Edit freely.
        'promos' => [
            ['icon' => '⚡', 'text' => 'Claude, ChatGPT & more — activated fast after payment', 'url' => '/ai-tools'],
            ['icon' => '🎓', 'text' => 'Fresh scholarship opportunities added every week', 'url' => '/scholarships'],
            ['icon' => '🛡️', 'text' => 'Authorized access, managed subscriptions, real support', 'url' => '/about'],
        ],

        // Include auto-generated lines from live activity.
        'show_activity' => true,
    ],

];
