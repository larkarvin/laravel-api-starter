<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, InteractsWithMedia, Notifiable;

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Scope used by Spatie QueryBuilder: filter[verified]=true|false
     *
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     */
    public function scopeVerified(\Illuminate\Database\Eloquent\Builder $query, bool $value): void
    {
        if ($value) {
            $query->whereNotNull('email_verified_at');
        } else {
            $query->whereNull('email_verified_at');
        }
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(150)
            ->height(150);
    }

    /**
     * Returns the avatar URL, falling back to a generated initial-based avatar.
     *
     * S3 disk  — returns a pre-signed temporary URL (15 min TTL).
     * Local disk — returns the public URL served through the application.
     */
    public function getAvatarUrl(): string
    {
        $media = $this->getFirstMedia('avatar');

        if ($media === null) {
            return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&size=150&background=random&color=fff';
        }

        if ($media->disk === 's3') {
            return Storage::disk('s3')->temporaryUrl(
                $media->getPath(),
                now()->addMinutes(15),
            );
        }

        return $media->getUrl('thumb') ?: $media->getUrl();
    }
}
