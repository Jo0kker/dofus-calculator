<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemPrice extends Model
{
    protected $fillable = [
        'server_id',
        'item_id',
        'price',
        'reports_count',
        'status',
        'created_by',
    ];

    protected $casts = [
        'price' => 'integer',
        'reports_count' => 'integer',
    ];

    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_REJECTED = 'rejected';

    const REPORT_THRESHOLD = 3;

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

    public function reports(): HasMany
    {
        return $this->hasMany(PriceReport::class);
    }

    public function report(User $user, ?string $reason = null): bool
    {
        $existingReport = $this->reports()
            ->where('reported_by', $user->id)
            ->first();
        
        if ($existingReport) {
            return false;
        }
        
        $this->reports()->create([
            'reported_by' => $user->id,
            'reason' => $reason,
        ]);
        
        $this->increment('reports_count');
        
        if ($this->reports_count >= self::REPORT_THRESHOLD) {
            $this->update(['status' => self::STATUS_PENDING_REVIEW]);
        }
        
        return true;
    }

    public function approve(): void
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reports_count' => 0,
        ]);
        
        $this->reports()->delete();
    }

    public function reject(): void
    {
        $this->update(['status' => self::STATUS_REJECTED]);
    }
}
