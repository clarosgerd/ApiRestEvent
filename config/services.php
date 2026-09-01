<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openwa' => [
    'base_url' => env('OPENWA_BASE_URL', 'http://localhost:2785'),
    'api_key' => env('OPENWA_API_KEY'),
    'session_id' => env('OPENWA_SESSION_ID'),
    ],

    // Solo consumo (de solo lectura) — no creamos ni administramos nada en
    // ChronoTrack, solo leemos resultados de eventos que el organizador ya
    // registró ahí. Ver App\Services\ChronoTrackClient y
    // brain/groovy-chasing-ladybug.md (sync de resultados, 09/08/2026).
    'chronotrack' => [
        'base_url'  => env('CHRONOTRACK_BASE_URL', 'https://api.chronotrack.com/api'),
        'client_id' => env('CHRONOTRACK_CLIENT_ID'),
        'user_id'   => env('CHRONOTRACK_USER_ID'),
        'user_pass' => env('CHRONOTRACK_USER_PASS'),
    ],

    // SIP multi-banco (28/08/2026) — secreto compartido para los
    // endpoints /internal/* que devuelven credenciales SIP reales
    // (nunca pasan por auth:admins/sanctum, es tráfico server-to-server
    // desde elascenso/event, no algo que un navegador deba poder llamar
    // nunca). Ver Http/Middleware/RequiresInternalSecret y
    // brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md.
    'internal' => [
        'secret' => env('INTERNAL_API_SECRET'),
    ],

];
