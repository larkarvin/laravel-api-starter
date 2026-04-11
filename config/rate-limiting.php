<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rate Limiting Configuration
|--------------------------------------------------------------------------
|
| Define all named rate limiters here. Each key becomes a throttle name
| usable as middleware('throttle:{key}') on any route or group.
|
| Fields per limiter:
|   max_attempts  – integer, overridable via env
|   decay_seconds – window length in seconds (default 60)
|   by            – 'user_or_ip' | 'user' | 'ip'
|   message       – human-readable 429 message
|
| Usage in routes:
|   Route::middleware('throttle:api')->group(...)
|   Route::middleware('throttle:auth')->group(...)
|   Route::middleware('throttle:strict')->post('/sensitive', ...)
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Default API limiter
    |--------------------------------------------------------------------------
    | Applied to all resource routes. Keyed by authenticated user ID or IP
    | so each user gets their own independent bucket.
    */
    'api' => [
        'max_attempts' => (int)env('RATE_LIMIT_API', 60),
        'decay_seconds' => (int)env('RATE_LIMIT_API_DECAY', 60),
        'by' => 'user_or_ip',
        'message' => 'Too many requests. Please slow down.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth limiter (login, register, password reset)
    |--------------------------------------------------------------------------
    | Keyed by IP only — no user context is available at auth time.
    | Login brute-force is additionally throttled inside LoginRequest itself.
    */
    'auth' => [
        'max_attempts' => (int)env('RATE_LIMIT_AUTH', 10),
        'decay_seconds' => (int)env('RATE_LIMIT_AUTH_DECAY', 60),
        'by' => 'ip',
        'message' => 'Too many attempts. Please try again later.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload limiter
    |--------------------------------------------------------------------------
    | Applied to media/file upload endpoints. Lower ceiling to protect storage.
    */
    'uploads' => [
        'max_attempts' => (int)env('RATE_LIMIT_UPLOADS', 10),
        'decay_seconds' => (int)env('RATE_LIMIT_UPLOADS_DECAY', 60),
        'by' => 'user_or_ip',
        'message' => 'Upload limit reached. Please try again later.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Strict limiter — for sensitive or expensive endpoints
    |--------------------------------------------------------------------------
    | Use on endpoints like OTP verify, 2FA, etc.
    | middleware('throttle:strict')
    */
    'strict' => [
        'max_attempts' => (int)env('RATE_LIMIT_STRICT', 3),
        'decay_seconds' => (int)env('RATE_LIMIT_STRICT_DECAY', 60),
        'by' => 'ip',
        'message' => 'Too many attempts on a sensitive endpoint.',
    ],

];
