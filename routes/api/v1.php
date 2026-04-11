<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\AvatarController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Prefix:  /api/v1   (applied in routes/api.php)
| Auth:    auth:sanctum middleware on protected groups
|
*/

// ── Health ─────────────────────────────────────────────────────────────────
Route::get('/health', fn () => response()->json(['status' => 'ok', 'version' => 'v1']));

// ── Auth: Public ───────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function (): void {
    Route::post('register', [RegisterController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    // Password Reset
    Route::prefix('password')->name('password.')->group(function (): void {
        Route::post('forgot', [PasswordResetController::class, 'forgot'])->name('forgot');
        Route::get('reset/{token}', [PasswordResetController::class, 'resetInfo'])->name('reset.info');
        Route::post('reset', [PasswordResetController::class, 'reset'])->name('reset');
    });
});

// ── Auth: Protected ────────────────────────────────────────────────────────
Route::prefix('auth')->name('auth.')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me', [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');

    // Multi-device token management
    Route::prefix('tokens')->name('tokens.')->group(function (): void {
        Route::get('/', [AuthController::class, 'tokens'])->name('index');
        Route::delete('{tokenId}', [AuthController::class, 'revokeToken'])->name('revoke');
    });

    // Email Verification
    Route::prefix('email')->name('email.')->group(function (): void {
        Route::post('send-notification', [EmailVerificationController::class, 'send'])->name('send');
    });
});

// Email verification via signed URL (accessible without Bearer but still signed)
Route::get(
    'auth/email/verify/{id}/{hash}',
    [EmailVerificationController::class, 'verify']
)->name('auth.email.verify')->middleware('signed');

// ── Profile & Avatar ───────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::prefix('profile')->name('profile.')->group(function (): void {
        Route::patch('/', [ProfileController::class, 'setProfile'])->name('set');
        Route::put('password', [ProfileController::class, 'changePassword'])->name('password');
        Route::post('avatar', [AvatarController::class, 'update'])->name('avatar.update');
        Route::delete('avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
    });
});

// ── Media ──────────────────────────────────────────────────────────────────
// Serve local-disk files via signed URL — no Bearer token required.
// S3 files bypass this route entirely (pre-signed S3 URLs are returned instead).
Route::get('media/serve/{uuid}', [MediaController::class, 'serve'])
    ->name('v1.media.serve')
    ->middleware('signed');

Route::middleware(['auth:sanctum', 'throttle:uploads'])->group(function (): void {
    // Step 1: upload file — returns uuid + temporary URL, no model attached yet.
    Route::post('media/upload', [MediaController::class, 'upload'])->name('media.upload');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    // Step 2: get a fresh temporary URL for a previously uploaded file.
    Route::get('media/{uuid}', [MediaController::class, 'show'])->name('media.show');
});

// ── API Resources (add below, protected by auth:sanctum) ──────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    // Route::apiResource('jobs', JobController::class);
});
