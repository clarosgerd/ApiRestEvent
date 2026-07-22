<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Solo se permite el acceso desde los orígenes que realmente consumen esta
    | API. El default de Laravel es '*' (cualquier origen) — se restringe
    | aquí explícitamente en vez de dejarlo abierto.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://events.inscrito.net',   // frontend de producción (Pass2Go)
        'http://localhost',              // frontend en XAMPP durante desarrollo local
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
