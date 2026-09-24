<?php


return [
    'disk' => env('LEARNING_DISK', 'private'),              // videos, renditions, resources, recordings
    'public_disk' => env('LEARNING_PUBLIC_DISK', 'public'), // thumbnails only
    'upload_tmp_disk' => 'local',                            // chunk assembly (always local)
    'max_video_mb' => (int) env('LEARNING_MAX_VIDEO_MB', 4096),
    'max_resource_mb' => (int) env('LEARNING_MAX_RESOURCE_MB', 50),
    'chunk_mb' => (int) env('LEARNING_CHUNK_MB', 5),
    'video_mimes' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v'],
    'video_extensions' => ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'],
    'resource_extensions' => ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'png', 'jpg', 'jpeg'],
    'trash_retention_days' => (int) env('LEARNING_TRASH_DAYS', 30),
    'reminder_minutes' => 15,
    'presence_timeout_seconds' => 45,
    'poll_interval_ms' => 4000,
    'presence_interval_ms' => 15000,
    'stale_room_grace_minutes' => 180,
    'completion_threshold' => 90, // percent watched that counts as completed
    'live' => [
        'provider' => env('LEARNING_LIVE_PROVIDER', 'jitsi'), // jitsi | jaas
        'room_prefix' => env('LEARNING_ROOM_PREFIX', 'mbunie'),
        'token_ttl_minutes' => 240,
        'jitsi' => [
            'domain' => env('LEARNING_JITSI_DOMAIN', 'meet.jit.si'),
            'app_id' => env('LEARNING_JITSI_APP_ID'),         // self-hosted token auth (HS256)
            'app_secret' => env('LEARNING_JITSI_APP_SECRET'),
            'recording' => (bool) env('LEARNING_JITSI_RECORDING', false), // true only if Jibri is installed
        ],
        'jaas' => [
            'app_id' => env('LEARNING_JAAS_APP_ID'),            // vpaas-magic-cookie-...
            'api_key_id' => env('LEARNING_JAAS_API_KEY_ID'),    // full kid: vpaas-magic-cookie-.../abc123
            'private_key' => env('LEARNING_JAAS_PRIVATE_KEY'),  // PEM (\n escaped) OR use path below
            'private_key_path' => env('LEARNING_JAAS_PRIVATE_KEY_PATH'),
            'webhook_secret' => env('LEARNING_JAAS_WEBHOOK_SECRET'),
        ],
    ],
];
