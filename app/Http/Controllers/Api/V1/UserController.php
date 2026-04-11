<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends ApiController
{
    /**
     * GET /users
     *
     * Supported query parameters:
     *   filter[name]=jane          partial, case-insensitive
     *   filter[email]=example.com  partial, case-insensitive
     *   filter[verified]=true      exact boolean (email_verified_at is not null)
     *   sort=name,-created_at      prefix with - for descending
     *   page=2&per_page=20
     */
    public function index(): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::scope('verified'),
            )
            ->allowedSorts(
                AllowedSort::field('name'),
                AllowedSort::field('email'),
                AllowedSort::field('created_at'),
            )
            ->defaultSort('-created_at')
            ->paginate(request()->integer('per_page', 15))
            ->appends(request()->query());

        return UserResource::collection($users);
    }

    /**
     * GET /users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return UserResource::make($user)->response();
    }

    /**
     * POST /users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->respondWithMessage(
            message: 'User created.',
            data: new UserResource($user),
            status: 201,
        );
    }

    /**
     * PUT /users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());

        return $this->respondWithMessage(
            message: 'User updated.',
            data: new UserResource($user),
        );
    }

    /**
     * DELETE /users/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return $this->respondEmpty('User deleted.');
    }
}
