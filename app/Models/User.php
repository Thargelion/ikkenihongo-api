<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'nickname', 'email', 'password', 'birthdate', 'avatar', 'is_guest', 'locale'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $appends = ['avatar_url'];

    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->avatar
                ? Storage::disk(self::avatarDisk($this->avatar))->url($this->avatar)
                : null,
        )->shouldCache();
    }

    /**
     * Avatars stored before the "avatars" disk existed live on the "public"
     * disk under an "avatars/" prefix; new uploads are bare filenames on
     * the "avatars" disk.
     */
    public static function avatarDisk(string $avatar): string
    {
        return str_starts_with($avatar, 'avatars/') ? 'public' : 'avatars';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'is_guest' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSettings::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(UserStats::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(StudySession::class);
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->settings()->create();
            $user->stats()->create();
        });
    }
}
