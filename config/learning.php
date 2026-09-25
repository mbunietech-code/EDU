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
    /*
    | Live classroom — our own self-hosted WebRTC stack (no third-party video
    | service): a LiveKit SFU (open source, runs on your server) for media and
    | signalling, coturn for STUN/TURN, and LiveKit Egress for recordings.
    | Laravel stays the authority: it issues short-lived access tokens with the
    | exact publish rights of each user, moderates through the SFU's server
    | API and records attendance. Deployment: deploy/live-server/README.md.
    */
    'live' => [
        'room_prefix' => env('LEARNING_ROOM_PREFIX', 'mbunie'),

        // Browser-facing SFU address (signalling WebSocket): wss://live.example.com
        'server_url' => env('LIVE_SERVER_URL'),
        // Server API (Twirp) base URL; defaults to server_url with ws→http.
        'api_url' => env('LIVE_SERVER_API_URL'),
        'api_key' => env('LIVE_SERVER_API_KEY'),
        'api_secret' => env('LIVE_SERVER_API_SECRET'),
        'api_timeout_seconds' => (int) env('LIVE_SERVER_API_TIMEOUT', 5),

        // Join tokens only need to be valid while connecting; the SFU keeps a
        // connected client's session alive and a full reconnect fetches a new one.
        'token_ttl_minutes' => (int) env('LIVE_TOKEN_TTL_MINUTES', 10),

        // Hard cap per room enforced by the SFU (0 = unlimited).
        'max_participants' => (int) env('LIVE_MAX_PARTICIPANTS', 100),

        'ice' => [
            // Comma-separated STUN URLs, e.g. stun:turn.example.com:3478
            'stun_urls' => env('STUN_SERVER_URLS'),
            // Comma-separated TURN URLs, e.g.
            // turn:turn.example.com:3478?transport=udp,turn:turn.example.com:3478?transport=tcp,turns:turn.example.com:5349?transport=tcp
            'turn_urls' => env('TURN_SERVER_URL'),
            // Preferred: coturn "static-auth-secret" → per-user credentials that expire (TURN REST API).
            'turn_secret' => env('TURN_SERVER_SECRET'),
            'turn_ttl_minutes' => (int) env('TURN_CREDENTIAL_TTL_MINUTES', 720),
            // Fallback: one fixed coturn user (lt-cred-mech).
            'turn_username' => env('TURN_SERVER_USERNAME'),
            'turn_credential' => env('TURN_SERVER_CREDENTIAL'),
            // relay = force TURN (testing firewalls); all = normal.
            'transport_policy' => env('LIVE_ICE_TRANSPORT_POLICY', 'all'),
        ],

        'recording' => [
            // Requires LiveKit Egress running next to the SFU (deploy/live-server).
            'enabled' => (bool) env('LIVE_RECORDING_ENABLED', false),
            // Where Egress writes files, as seen INSIDE the egress container.
            'egress_output_dir' => env('LIVE_EGRESS_OUTPUT_DIR', '/out'),
            // The same folder as seen by Laravel (same server, or a mounted share).
            'import_dir' => env('LIVE_RECORDING_IMPORT_DIR'),
            'layout' => env('LIVE_RECORDING_LAYOUT', 'speaker'), // speaker | grid
        ],
    ],
];
