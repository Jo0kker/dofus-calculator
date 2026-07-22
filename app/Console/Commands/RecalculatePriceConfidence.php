<?php

namespace App\Console\Commands;

use App\Models\PriceHistory;
use App\Services\CommunityPriceTrustService;
use Illuminate\Console\Command;

class RecalculatePriceConfidence extends Command
{
    protected $signature = 'prices:recalculate-confidence
        {--server= : Limiter le recalcul à un serveur}
        {--item= : Limiter le recalcul à un item}';

    protected $description = 'Recalcule les consensus communautaires, leur confiance et la fiabilité des contributeurs';

    public function handle(CommunityPriceTrustService $trustService): int
    {
        $pairs = PriceHistory::query()
            ->select(['server_id', 'item_id'])
            ->when($this->option('server'), fn ($query, $serverId) => $query->where('server_id', $serverId))
            ->when($this->option('item'), fn ($query, $itemId) => $query->where('item_id', $itemId))
            ->distinct()
            ->orderBy('server_id')
            ->orderBy('item_id')
            ->cursor();

        $count = 0;
        foreach ($pairs as $pair) {
            $trustService->recalculate($pair->item_id, $pair->server_id);
            $count++;
        }

        $this->info("{$count} prix communautaire(s) recalculé(s).");

        return self::SUCCESS;
    }
}
