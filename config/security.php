<?php

/*
 * Daily security scan (php artisan security:scan). See App\Services\SecurityScanner.
 * Defaults fit Hostinger: every site under ~/domains is checked, because all
 * of them run as the same Unix user and one infected site reaches the others.
 */
return [
    // Folders to scan (comma separated). Empty = ~/domains when the app lives
    // in ~/domains/<site>/public_html, else this app only.
    'roots' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_SCAN_ROOTS', ''))))),

    // Sites checked from the outside for leaked files (.env, .git, logs).
    'urls' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'SECURITY_SCAN_URLS',
        env('APP_URL', '').','.env('MVPN_URL', 'https://vpn.mbuniehub.com')
    ))))),

    // Daily scan: files changed in this many hours are also read inside vendor/.
    'recent_hours' => (int) env('SECURITY_SCAN_RECENT_HOURS', 72),

    // Hourly quick scan: files changed in this many hours.
    'quick_hours' => (int) env('SECURITY_SCAN_QUICK_HOURS', 2),
];
