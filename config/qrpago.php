<?php

return [

    'base_auth_url' => env('QR_BASE_AUTH_URL', 'http://127.0.0.1:800/api/v1/autenticacion/'),
    'base_api_url'  => env('QR_BASE_API_URL', 'http://127.0.0.1:800/api/v1/'),
    'username'      => env('QR_USERNAME', 'GERD'),
    'password'      => env('QR_PASSWORD', '$ecret2026'),
    'apikey_test'   => env('QR_APIKEY_TEST', ''),
    'apikey_servicio' => env('QR_APIKEY_SERVICIO', ''),
    'verify_ss'     => env('QR_VERIFY_SS_TEST', true),
    'moneda'        => 'BOB',
    'callback'      => '000',
    'tipo_solicitud' => 'API',

];
