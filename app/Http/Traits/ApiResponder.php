<?php

declare(strict_types=1);

namespace App\Http\Traits;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Controller-level response helpers.
 *
 * Use this trait in any controller that needs structured API responses.
 * All methods delegate to ApiResponse so the shape is always consistent.
 */
trait ApiResponder
{
    /**
     * Action response for mutations (create, update) that return a resource.
     *
     *   $this->respondWithMessage('User updated.', new UserResource($user));
     *   $this->respondWithMessage('User created.', new UserResource($user), 201);
     */
    protected function respondWithMessage(
        string $message,
        JsonResource|null $data = null,
        int $status = 200,
    ): JsonResponse {
        return ApiResponse::message($message, $data, $status);
    }

    /**
     * Authentication response.
     *
     *   $this->respondWithToken($token->plainTextToken, new UserResource($user));
     */
    protected function respondWithToken(
        string $plainTextToken,
        JsonResource $user,
        string $message = 'Login successful.',
    ): JsonResponse {
        return ApiResponse::token($plainTextToken, $user, $message);
    }

    /**
     * Empty response for deletes or side-effect-only operations.
     * Returns HTTP 200 with a consistent envelope — not a raw 204.
     *
     *   $this->respondEmpty('User deleted successfully.');
     */
    protected function respondEmpty(string $message): JsonResponse
    {
        return ApiResponse::empty($message);
    }

    /**
     * Error response — prefer the exception handler for framework exceptions.
     * Use this only for domain-level errors raised inside controllers.
     *
     *   $this->respondWithError('Club not found.', 'CLUB_NOT_FOUND', 404);
     *
     * @param  array<string, mixed>|null  $details
     */
    protected function respondWithError(
        string $message,
        string $code,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        return ApiResponse::error($message, $code, $status, $details);
    }
}
