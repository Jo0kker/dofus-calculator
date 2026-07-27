<?php

namespace App\Services;

use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\PriceHistory;
use App\Models\User;
use App\Models\UserItemPricePreference;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PriceSubmissionService
{
    public function __construct(private readonly CommunityPriceTrustService $trustService) {}

    public function submitCommunityPrice(User $user, int $itemId, int $serverId, int $price): ItemPrice
    {
        return $this->submitCommunityPriceWithResult($user, $itemId, $serverId, $price)['price'];
    }

    /**
     * @return array{price: ItemPrice, recorded: bool}
     */
    public function submitCommunityPriceWithResult(
        User $user,
        int $itemId,
        int $serverId,
        int $price,
    ): array {
        $previousObservation = PriceHistory::query()
            ->where('created_by', $user->id)
            ->where('item_id', $itemId)
            ->where('server_id', $serverId)
            ->whereNull('rejected_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($previousObservation?->price === $price) {
            $communityPrice = ItemPrice::query()
                ->where('item_id', $itemId)
                ->where('server_id', $serverId)
                ->first() ?? $this->trustService->recalculate($itemId, $serverId);

            return [
                'price' => $communityPrice,
                'recorded' => false,
            ];
        }

        PriceHistory::create([
            'item_id' => $itemId,
            'server_id' => $serverId,
            'price' => $price,
            'created_by' => $user->id,
            'reliability_snapshot' => $user->price_reliability_score ?? 60,
        ]);

        $now = now();
        $lastContributionAt = DB::table('price_contribution_days')
            ->where('user_id', $user->id)
            ->where('server_id', $serverId)
            ->where('item_id', $itemId)
            ->max('created_at');
        $canCountContribution = $lastContributionAt === null
            || Carbon::parse($lastContributionAt)->lte($now->copy()->subDay());

        $counted = $canCountContribution
            ? DB::table('price_contribution_days')->insertOrIgnore([
                'user_id' => $user->id,
                'server_id' => $serverId,
                'item_id' => $itemId,
                'contribution_date' => $now->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ])
            : 0;

        if ($counted === 1) {
            $user->increment('price_contributions_count');
        }

        return [
            'price' => $this->trustService->recalculate($itemId, $serverId),
            'recorded' => true,
        ];
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
