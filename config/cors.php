<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:5174',
        'http://127.0.0.1:5174',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ],

    'allowed_origins_patterns' => [
        // Expresiones regulares para permitir cualquier puerto en localhost, IPs de red local y dominios de Herd (.test)
        '#^https?://localhost(:[0-9]+)?$#',
        '#^https?://127\.0\.0\.1(:[0-9]+)?$#',
        '#^https?://192\.168\.[0-9]+\.[0-9]+(:[0-9]+)?$#', // Redes locales (Wi-Fi)
        '#^https?://.*\.test$#', // Dominios locales de Laravel Herd
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // IMPORTANTE: Se mantiene en `true` porque si se cambia a `false`, 
    // la autenticación del panel de administración (Laravel Sanctum) dejará de funcionar.
    // Los patrones de arriba garantizan que React nunca tenga errores de CORS.
    'supports_credentials' => true,
];
