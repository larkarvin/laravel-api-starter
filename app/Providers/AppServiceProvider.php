<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void {}

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Register all named rate limiters from config/rate-limiting.php.
     *
     * Each entry is automatically available as middleware('throttle:{key}').
     * To add a new limiter, add an entry to the config — no code change needed.
     */
    private function configureRateLimiting(): void
    {
        /** @var array<string, array{max_attempts: int, decay_seconds: int, by: string, message: string}> $limiters */
        $limiters = config('rate-limiting', []);

        foreach ($limiters as $name => $config) {
            RateLimiter::for($name, function (Request $request) use ($config): Limit {
                $userId = $request->user()?->getAuthIdentifier();
                $byUserOrIp = match (true) {
                    is_int($userId), is_string($userId) => (string) $userId,
                    default => (string) $request->ip(),
                };

                $key = match ($config['by']) {
                    'user' => $byUserOrIp,
                    'ip' => (string) $request->ip(),
                    default => $byUserOrIp,
                };

                return (new Limit($config['max_attempts'], $config['decay_seconds']))
                    ->by($key)
                    ->response(fn (): JsonResponse => response()->json([
                        'success' => false,
                        'message' => $config['message'],
                    ], 429));
            });
        }
    }
}
