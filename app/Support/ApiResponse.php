<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single source of truth for all API response shapes.
 *
 * Controllers use this via the ApiResponder trait.
 * The exception handler calls these static methods directly.
 */
final class ApiResponse
{
    /**
     * Action response — create, update, or any mutation that returns data.
     */
    public static function message(
        string $message,
        JsonResource|null $data = null,
        int $status = 200,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    /**
     * Authentication response — includes token + user resource.
     */
    public static function token(
        string $plainTextToken,
        JsonResource $user,
        string $message = 'Login successful.',
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'data' => [
                'token' => $plainTextToken,
                'token_type' => 'Bearer',
                'user' => $user,
            ],
        ]);
    }

    /**
     * Empty action response — deletes or side-effect-only operations.
     *
     * Returns HTTP 200 with a consistent envelope rather than raw 204
     * so every API client gets the same shape regardless of operation.
     */
    public static function empty(string $message): JsonResponse
    {
        return response()->json(['message' => $message]);
    }

    /**
     * Error response — used by the exception handler and controllers.
     *
     * @param  array<string, mixed>|null  $details
     */
    public static function error(
        string $message,
        string $code,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'message' => $message,
            'error' => ['code' => $code],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return response()->json($payload, $status);
    }
}
