<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Staging model for decoupled media uploads.
 *
 * Files attach here first, then move to their target model
 * (e.g. user avatar) via an explicit attachment endpoint.
 * Orphaned rows are cleaned up by the media:cleanup scheduled command.
 */
class Upload extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['user_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
