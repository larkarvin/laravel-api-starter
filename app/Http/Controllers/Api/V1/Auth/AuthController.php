<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthController extends ApiController
{
    /**
     * Authenticate user and issue a token.
     *
     * Supports named tokens for multi-device tracking via `device_name` field.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $token = $request->authenticate();

        return $this->respondWithToken(
            plainTextToken: $token->plainTextToken,
            user: new UserResource($token->accessToken->tokenable),
        );
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respondWithMessage('Authenticated user.', UserResource::make($user));
    }

    /**
     * Revoke the current access token (logout from this device).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->currentAccessToken()->delete();

        return $this->respondEmpty('Logged out successfully.');
    }

    /**
     * Revoke all tokens (logout from all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->delete();

        return $this->respondEmpty('Logged out from all devices.');
    }

    /**
     * List all active tokens for the authenticated user (multi-device).
     */
    public function tokens(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $user->tokens()
            ->select(['id', 'name', 'last_used_at', 'created_at'])
            ->get();

        return $this->respondWithMessage('Active tokens.', new JsonResource($tokens));
    }

    /**
     * Revoke a specific token by ID.
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('id', $tokenId)->delete();

        return $this->respondEmpty('Token revoked.');
    }
}
