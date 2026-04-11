<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\AttachAvatarRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvatarController extends ApiController
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * POST /api/v1/profile/avatar
     *
     * Attach a previously uploaded file to the user's avatar collection.
     * The request carries only a media_uuid — no file is transferred here.
     *
     * Flow:
     *   1. Client uploads file → POST /media/upload → receives media_uuid
     *   2. Client sends media_uuid here → file moves to user's avatar collection
     *   3. Staging Upload record is deleted automatically
     */
    public function update(AttachAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $media = $this->mediaService->findByUuid($request->validated()['media_uuid']);

        if ($media === null) {
            return $this->respondWithError('Media not found.', 'MEDIA_NOT_FOUND', 404);
        }

        if (! $this->mediaService->isStagingOwnedBy($media, $user)) {
            return $this->respondWithError(
                'This file does not belong to you or has already been attached.',
                'FORBIDDEN',
                403,
            );
        }

        $this->mediaService->attachUserAvatar($media, $user);

        return $this->respondWithMessage('Avatar updated.', new UserResource($user->fresh()));
    }

    /**
     * DELETE /api/v1/profile/avatar
     *
     * Remove the authenticated user's avatar.
     * UserResource falls back to the UI Avatars initials URL.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->clearMediaCollection('avatar');

        return $this->respondWithMessage('Avatar removed.', new UserResource($user));
    }
}
