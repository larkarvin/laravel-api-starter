<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\ChangePasswordRequest;
use App\Http\Requests\Api\V1\SetProfileRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ProfileController extends ApiController
{
    /**
     * Set the authenticated user's initial profile after registration.
     * Only name is accepted — email and password are managed separately.
     */
    public function setProfile(SetProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['name' => $request->string('name')->toString()]);

        return $this->respondWithMessage('Profile updated.', new UserResource($user));
    }

    /**
     * Change the authenticated user's password.
     * Requires current password verification before applying the new one.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return $this->respondEmpty('Password changed successfully.');
    }
}
