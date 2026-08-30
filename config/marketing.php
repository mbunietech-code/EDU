<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Home page ticker
    |--------------------------------------------------------------------------
    |
    | The scrolling strip on the public home page. It shows real activity
    | (access unlocked, subscriptions activated, new tools, new scholarships,
    | new members) and refreshes itself in the browser every few seconds.
    |
    | Manual promos below are only used to pad the strip when there is not
    | yet enough real activity to fill it.
    |
    */

    'ticker' => [

        'enabled' => env('HOME_TICKER_ENABLED', true),

        // Seconds the browser waits between live refreshes of the feed.
        'refresh_seconds' => env('HOME_TICKER_REFRESH', 20),

        // Server-side cache for the computed feed (protects the DB from
        // many visitors polling at once). Keep <= refresh_seconds.
        'cache_seconds' => env('HOME_TICKER_CACHE', 15),

        // How fast one loop scrolls: seconds of screen time per item.
        'seconds_per_item' => 4,

        // Only look at activity from the last N days.
        'activity_window_days' => 60,

        // Target number of items on the strip. Real activity fills this first;
        // promos pad whatever is left.
        'target_items' => 10,

        // Set true to never show promos — real activity only (strip may be
        // short or empty until there is traffic).
        'activity_only' => env('HOME_TICKER_ACTIVITY_ONLY', false),

        // Manual promo messages (padding / fallback).
        'promos' => [
            ['icon' => '⚡', 'text' => 'Claude, ChatGPT & more — activated fast after payment', 'url' => '/ai-tools'],
            ['icon' => '🎓', 'text' => 'Fresh scholarship opportunities added every week', 'url' => '/scholarships'],
            ['icon' => '🛡️', 'text' => 'Authorized access, managed subscriptions, real support', 'url' => '/about'],
        ],
    ],

];
