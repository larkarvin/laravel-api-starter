<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UploadMediaRequest;
use App\Http\Resources\V1\MediaResource;
use App\Models\User;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends ApiController
{
    public function __construct(private readonly MediaService $mediaService) {}

    /**
     * POST /api/v1/media/upload
     *
     * Store a file as a standalone resource — no model attachment.
     * Returns the UUID and a 15-minute temporary URL.
     * The client uses the UUID in a subsequent attachment request
     * (e.g. POST /profile/avatar with media_uuid).
     */
    public function upload(UploadMediaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $media = $this->mediaService->storeUpload($request->file('file'), $user);
        $url = $this->mediaService->temporaryUrl($media);

        return $this->respondWithMessage(
            'File uploaded.',
            (new MediaResource($media))->withUrl($url),
            201,
        );
    }

    /**
     * GET /api/v1/media/{uuid}
     *
     * Retrieve a fresh temporary URL for any media identified by UUID.
     * Use this when a previously issued URL has expired.
     */
    public function show(string $uuid): JsonResponse
    {
        $media = $this->mediaService->findByUuid($uuid);

        if ($media === null) {
            return $this->respondWithError('Media not found.', 'MEDIA_NOT_FOUND', 404);
        }

        $url = $this->mediaService->temporaryUrl($media);

        return response()->json([
            'data' => (new MediaResource($media))->withUrl($url),
        ]);
    }

    /**
     * GET /api/v1/media/serve/{uuid}
     *
     * Serve a local-disk file through the application.
     * Accessed via a temporary signed URL — no Bearer token required.
     * S3 files are never routed here; they use pre-signed S3 URLs directly.
     *
     * Named route: v1.media.serve
     */
    public function serve(string $uuid): StreamedResponse|JsonResponse
    {
        $media = $this->mediaService->findByUuid($uuid);

        if ($media === null) {
            return $this->respondWithError('Media not found.', 'MEDIA_NOT_FOUND', 404);
        }

        return response()->streamDownload(
            function () use ($media): void {
                echo file_get_contents($media->getPath());
            },
            $media->file_name,
            ['Content-Type' => $media->mime_type],
        );
    }
}
