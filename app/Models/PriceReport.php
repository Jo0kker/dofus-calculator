<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceReport extends Model
{
    protected $fillable = [
        'item_price_id',
        'reported_by',
        'reason',
    ];

    public function itemPrice(): BelongsTo
    {
        return $this->belongsTo(ItemPrice::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
