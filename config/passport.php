<?php

use App\Http\Middleware\RequirePkceS256;

return [
    'guard' => 'web',

    'middleware' => [
        RequirePkceS256::class,
    ],

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    'connection' => env('PASSPORT_CONNECTION'),

    'access_token_lifetime' => (int) env('PASSPORT_ACCESS_TOKEN_LIFETIME', 15),

    'refresh_token_lifetime' => (int) env('PASSPORT_REFRESH_TOKEN_LIFETIME', 43200),
];
