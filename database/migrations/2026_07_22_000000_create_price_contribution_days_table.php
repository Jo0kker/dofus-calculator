<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('price_contribution_days')) {
            Schema::create('price_contribution_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('server_id')->constrained()->cascadeOnDelete();
                $table->foreignId('item_id')->constrained()->cascadeOnDelete();
                $table->date('contribution_date');
                $table->timestamps();

                $table->unique(
                    ['user_id', 'server_id', 'item_id', 'contribution_date'],
                    'price_contribution_day_unique',
                );
                $table->index(['user_id', 'contribution_date'], 'price_contribution_user_date');
            });
        }

        $this->backfillHistories();
        $unresolvedLegacyCounts = $this->backfillLegacyApiLogs();
        $this->recalculateUserCounts($unresolvedLegacyCounts);
    }

    private function backfillHistories(): void
    {
        DB::table('price_histories')
            ->whereNotNull('created_by')
            ->select(['id', 'created_by', 'server_id', 'item_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($histories) {
                $now = now();
                $rows = $histories->map(fn ($history) => [
                    'user_id' => $history->created_by,
                    'server_id' => $history->server_id,
                    'item_id' => $history->item_id,
                    'contribution_date' => Carbon::parse($history->created_at ?? $now)->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('price_contribution_days')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * @return array<int, int>
     */
    private function backfillLegacyApiLogs(): array
    {
        $unresolvedCounts = [];

        DB::table('api_logs')
            ->whereNotNull('user_id')
            ->where('method', 'POST')
            ->where('endpoint', 'like', '%prices%')
            ->whereBetween('response_status', [200, 299])
            ->select(['id', 'user_id', 'items_affected', 'request_data', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function ($logs) use (&$unresolvedCounts) {
                foreach ($logs as $log) {
                    $requestData = json_decode($log->request_data ?? '{}', true);
                    $serverId = (int) ($requestData['server_id'] ?? 0);
                    $prices = is_array($requestData['prices'] ?? null) ? $requestData['prices'] : [];

                    if ($serverId <= 0 || $prices === []) {
                        $unresolvedCounts[$log->user_id] = ($unresolvedCounts[$log->user_id] ?? 0)
                            + max(0, (int) $log->items_affected);

                        continue;
                    }

                    $date = Carbon::parse($log->created_at ?? now())->toDateString();
                    $seenUnresolved = [];

                    foreach ($prices as $priceData) {
                        $itemId = $this->resolveItemId($priceData);
                        if (! $itemId) {
                            $key = mb_strtolower(trim((string) ($priceData['item_name'] ?? '')));
                            if ($key !== '') {
                                $seenUnresolved[$key] = true;
                            }

                            continue;
                        }

                        DB::table('price_contribution_days')->insertOrIgnore([
                            'user_id' => $log->user_id,
                            'server_id' => $serverId,
                            'item_id' => $itemId,
                            'contribution_date' => $date,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    $unresolvedCounts[$log->user_id] = ($unresolvedCounts[$log->user_id] ?? 0)
                        + count($seenUnresolved);
                }
            });

        return $unresolvedCounts;
    }

    private function resolveItemId(array $priceData): ?int
    {
        if (! empty($priceData['item_id'])) {
            $itemId = (int) $priceData['item_id'];

            return DB::table('items')->where('id', $itemId)->exists() ? $itemId : null;
        }

        $itemName = trim((string) ($priceData['item_name'] ?? ''));
        if ($itemName === '') {
            return null;
        }

        return DB::table('items')
            ->whereRaw('LOWER(name) = LOWER(?)', [$itemName])
            ->value('id');
    }

    /**
     * @param  array<int, int>  $unresolvedLegacyCounts
     */
    private function recalculateUserCounts(array $unresolvedLegacyCounts): void
    {
        DB::table('users')->update(['price_contributions_count' => 0]);

        DB::table('price_contribution_days')
            ->select('user_id', DB::raw('COUNT(*) as contribution_count'))
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get()
            ->each(function ($count) use ($unresolvedLegacyCounts) {
                DB::table('users')
                    ->where('id', $count->user_id)
                    ->update([
                        'price_contributions_count' => (int) $count->contribution_count
                            + ($unresolvedLegacyCounts[$count->user_id] ?? 0),
                    ]);
            });

        foreach ($unresolvedLegacyCounts as $userId => $count) {
            if (DB::table('price_contribution_days')->where('user_id', $userId)->exists()) {
                continue;
            }

            DB::table('users')->where('id', $userId)->update([
                'price_contributions_count' => $count,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_contribution_days');
    }
};
