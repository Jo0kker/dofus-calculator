<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $fillable = [
        'dofusdb_id',
        'name',
        'type',
        'category',
        'level',
        'image_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'level' => 'integer',
        'dofusdb_id' => 'integer',
    ];

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function usedInRecipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function personalPrices(): HasMany
    {
        return $this->hasMany(PersonalItemPrice::class);
    }

    public function pricePreferences(): HasMany
    {
        return $this->hasMany(UserItemPricePreference::class);
    }

    public function getPriceModeForServer(Server $server, ?User $user = null): string
    {
        if (! $user) {
            return 'community';
        }

        $preference = $this->relationLoaded('pricePreferences')
            ? $this->pricePreferences
                ->where('user_id', $user->id)
                ->firstWhere('server_id', $server->id)
            : $this->pricePreferences()
                ->where('user_id', $user->id)
                ->where('server_id', $server->id)
                ->first();

        return $preference?->mode ?? $user->price_mode ?? 'community';
    }

    public function getPriceForServer(Server $server, ?User $user = null): ItemPrice|PersonalItemPrice|null
    {
        if ($this->getPriceModeForServer($server, $user) === 'personal') {
            $personalPrice = $this->relationLoaded('personalPrices')
                ? $this->personalPrices
                    ->where('user_id', $user->id)
                    ->firstWhere('server_id', $server->id)
                : $this->personalPrices()
                    ->where('user_id', $user->id)
                    ->where('server_id', $server->id)
                    ->first();

            if ($personalPrice) {
                return $personalPrice;
            }
        }

        if ($this->relationLoaded('prices')) {
            return $this->prices
                ->where('server_id', $server->id)
                ->where('status', ItemPrice::STATUS_APPROVED)
                ->sortByDesc('updated_at')
                ->first();
        }

        return $this->prices()
            ->where('server_id', $server->id)
            ->where('status', ItemPrice::STATUS_APPROVED)
            ->orderBy('updated_at', 'desc')
            ->first();
    }
}
