<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $hidden = [
        'plausibility_score',
        'reliability_snapshot',
        'consensus_deviation',
        'influence_weight',
        'evaluation_score',
        'evaluation_weight',
        'evaluated_at',
        'rejected_at',
    ];

    protected $fillable = [
        'server_id',
        'item_id',
        'price',
        'created_by',
        'plausibility_score',
        'reliability_snapshot',
        'consensus_deviation',
        'influence_weight',
        'evaluation_score',
        'evaluation_weight',
        'evaluated_at',
        'rejected_at',
    ];

    protected $casts = [
        'price' => 'integer',
        'plausibility_score' => 'integer',
        'reliability_snapshot' => 'integer',
        'consensus_deviation' => 'float',
        'influence_weight' => 'float',
        'evaluation_score' => 'integer',
        'evaluation_weight' => 'float',
        'evaluated_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
