<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaService
{
    /**
     * Store an uploaded file in the staging area (Upload model).
     *
     * No model attachment happens here. The caller receives a Media record
     * identified only by its UUID. Attachment to a target model is a
     * separate, explicit step via attachUserAvatar() or equivalent.
     */
    public function storeUpload(UploadedFile $file, User $uploader): Media
    {
        $staging = Upload::create(['user_id' => $uploader->id]);

        return $staging->addMedia($file)->toMediaCollection('default');
    }

    /**
     * Move staged media to the user's avatar collection.
     *
     * Reads the file from the staging location (local or S3) using
     * addMediaFromDisk, attaches it to the avatar collection (singleFile —
     * old avatar removed automatically), then deletes the staging Upload
     * record (Spatie observer cascades to remove the file).
     *
     * When adding attachment support for other models (e.g. Post cover image),
     * add a dedicated method here: attachPostCover(Media $staging, Post $post).
     */
    public function attachUserAvatar(Media $stagingMedia, User $user): Media
    {
        $newMedia = $user
            ->addMediaFromDisk($stagingMedia->getPathRelativeToRoot(), $stagingMedia->disk)
            ->usingFileName($stagingMedia->file_name)
            ->usingName($stagingMedia->name)
            ->toMediaCollection('avatar');

        // Delete staging — Spatie observer removes the file from disk.
        $stagingMedia->model->delete();

        return $newMedia;
    }

    /**
     * Generate a temporary access URL.
     *
     * S3  — pre-signed AWS URL (bucket path never reaches the client).
     * Local — Laravel signed route URL served through the application.
     */
    public function temporaryUrl(Media $media, int $minutes = 15): string
    {
        if ($media->disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $media->getPath(),
                now()->addMinutes($minutes),
            );
        }

        return URL::temporarySignedRoute(
            'v1.media.serve',
            now()->addMinutes($minutes),
            ['uuid' => $media->uuid],
        );
    }

    /**
     * Find a media record by its UUID — never by DB ID.
     */
    public function findByUuid(string $uuid): ?Media
    {
        return Media::where('uuid', $uuid)->first();
    }

    /**
     * Assert that a Media record is a staged upload owned by the given user.
     *
     * Only staging media (model_type = Upload) can be attached.
     * Already-attached media returns false, preventing accidental re-attachment.
     */
    public function isStagingOwnedBy(Media $media, User $user): bool
    {
        return $media->model_type === Upload::class
            && $media->model instanceof Upload
            && $media->model->user_id === $user->id;
    }

    /**
     * Permanently delete a media item and its files from disk.
     */
    public function delete(Media $media): void
    {
        $media->delete();
    }
}
