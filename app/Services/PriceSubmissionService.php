<?php

namespace App\Services;

use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\UserItemPricePreference;
use Illuminate\Support\Facades\DB;

class PriceSubmissionService
{
    public function __construct(private readonly CommunityPriceTrustService $trustService) {}

    public function submitCommunityPrice(User $user, int $itemId, int $serverId, int $price): ItemPrice
    {
        PriceHistory::create([
            'item_id' => $itemId,
            'server_id' => $serverId,
            'price' => $price,
            'created_by' => $user->id,
            'reliability_snapshot' => $user->price_reliability_score ?? 60,
        ]);

        $counted = DB::table('price_contribution_days')->insertOrIgnore([
            'user_id' => $user->id,
            'server_id' => $serverId,
            'item_id' => $itemId,
            'contribution_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($counted === 1) {
            $user->increment('price_contributions_count');
        }

        return $this->trustService->recalculate($itemId, $serverId);
    }

    public function submitPersonalPrice(User $user, int $itemId, int $serverId, int $price): PersonalItemPrice
    {
        return PersonalItemPrice::updateOrCreate(
            [
                'user_id' => $user->id,
                'item_id' => $itemId,
                'server_id' => $serverId,
            ],
            ['price' => $price]
        );
    }

    public function rememberItemMode(User $user, int $itemId, int $serverId, ?string $mode): void
    {
        if ($mode !== 'personal') {
            UserItemPricePreference::query()
                ->where('user_id', $user->id)
                ->where('item_id', $itemId)
                ->where('server_id', $serverId)
                ->delete();

            return;
        }

        UserItemPricePreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'item_id' => $itemId,
                'server_id' => $serverId,
            ],
            ['mode' => $mode],
        );
    }
}
