<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceReport extends Model
{
    protected $fillable = [
        'item_price_id',
        'price_history_id',
        'reported_by',
        'comment',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function itemPrice(): BelongsTo
    {
        return $this->belongsTo(ItemPrice::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function priceHistory(): BelongsTo
    {
        return $this->belongsTo(PriceHistory::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'En attente',
            'reviewed' => 'Traité',
            'dismissed' => 'Rejeté',
            default => 'Inconnu'
        };
    }
}
