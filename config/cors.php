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
        'https://events.inscrito.net',   // frontend de producción (Inscrito)
        'http://localhost',              // frontend en XAMPP durante desarrollo local
        'http://localhost:59063',              // frontend en XAMPP durante desarrollo local
    ],

    // Desarrollo local: el puerto de localhost cambia en cada corrida. Se acepta
    // cualquier puerto solo si CORS_ALLOW_LOCALHOST=true en el .env (apagado por defecto).
    'allowed_origins_patterns' => env('CORS_ALLOW_LOCALHOST', false)
        ? ['#^http://localhost(:\d+)?$#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
