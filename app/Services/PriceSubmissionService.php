<?php

namespace App\Services;

use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\UserItemPricePreference;

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

        $user->increment('price_contributions_count');

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

    public function rememberMode(User $user, string $mode): void
    {
        if ($user->price_mode !== $mode) {
            $user->forceFill(['price_mode' => $mode])->save();
        }
    }

    public function rememberItemMode(User $user, int $itemId, int $serverId, ?string $mode): void
    {
        if ($mode === null) {
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
