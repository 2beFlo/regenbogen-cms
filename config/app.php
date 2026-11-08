<?php

return [
    'name' => env('APP_NAME', 'REGENBOGEN'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Europe/Berlin',
    'locale' => env('APP_LOCALE', 'de'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'de'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'de_DE'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', (string) env('APP_PREVIOUS_KEYS', ''))),
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file')],
];
