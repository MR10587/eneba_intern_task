<?php

/**
 * Browser access is limited to explicit origins. The local defaults keep the
 * Vite dev server working; production must set CORS_ALLOWED_ORIGINS to the
 * deployed frontend URL. A wildcard is removed in production on purpose.
 */
$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')),
)));

if (env('APP_ENV') === 'production') {
    $allowedOrigins = array_values(array_filter(
        $allowedOrigins,
        static fn (string $origin): bool => $origin !== '*',
    ));
}

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Cart-Token',
        'X-Requested-With',
    ],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
