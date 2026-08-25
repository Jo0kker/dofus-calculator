<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Client;

class ApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'oauth_client_id',
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

    public function oauthApplication(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }
}
