<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'dofusdb_id',
        'name',
        'slug',
        'type',
        'is_temporary',
        'is_active',
        'display_order',
        'language',
        'mono_account',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_temporary' => 'boolean',
        'mono_account' => 'boolean',
        'dofusdb_id' => 'integer',
        'display_order' => 'integer',
    ];

    public function itemPrices(): HasMany
    {
        return $this->hasMany(ItemPrice::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }
}
