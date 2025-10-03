<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'token_name',
        'endpoint',
        'method',
        'ip_address',
        'user_agent',
        'request_data',
        'response_status',
        'items_affected',
    ];

    protected $casts = [
        'request_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
