<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'server_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function favoriteItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'user_favorites')
            ->withTimestamps();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function isFavorite(Item $item): bool
    {
        return $this->favoriteItems()->where('items.id', $item->id)->exists();
    }

    public function toggleFavorite(Item $item): bool
    {
        if ($this->isFavorite($item)) {
            $this->favoriteItems()->detach($item->id);
            return false;
        } else {
            $this->favoriteItems()->attach($item->id);
            return true;
        }
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function priceReports(): HasMany
    {
        return $this->hasMany(PriceReport::class, 'reported_by');
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function reviewedReports(): HasMany
    {
        return $this->hasMany(PriceReport::class, 'reviewed_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['moderator', 'admin']);
    }

    public function canModerate(): bool
    {
        return $this->isModerator();
    }
}
