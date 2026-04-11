<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = hrtime(true);

        $response = $next($request);

        $durationMs = round((hrtime(true) - $start) / 1_000_000, 2);

        /** @var string $channel */
        $channel = config('logging.api_channel', 'daily');
        Log::channel($channel)->info('api.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query() ?: null,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }
}
