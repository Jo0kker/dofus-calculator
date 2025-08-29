<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    public function getPriceForServer(Server $server): ?ItemPrice
    {
        return $this->prices()
            ->where('server_id', $server->id)
            ->where('status', 'approved')
            ->orderBy('updated_at', 'desc')
            ->first();
    }
}
