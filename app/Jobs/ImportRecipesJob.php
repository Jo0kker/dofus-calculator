<?php

namespace App\Jobs;

use App\Services\DiscordWebhookService;
use App\Services\DofusDBImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRecipesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    public function __construct(
        private ?string $triggeredBy = null,
    ) {}

    public function handle(DofusDBImportService $importService, DiscordWebhookService $discord): void
    {
        Cache::put('import_recipes_status', 'running', now()->addHours(2));
        Cache::put('import_recipes_started_at', now()->toIso8601String(), now()->addHours(2));

        DB::disableQueryLog();

        $startTime = microtime(true);

        Log::info('ImportRecipesJob started', ['triggered_by' => $this->triggeredBy]);

        try {
            $result = $importService->importRecipesFirst(PHP_INT_MAX, 100, function ($processed, $memoryUsage) {
                Cache::put('import_recipes_progress', [
                    'processed' => $processed,
                    'memory' => $memoryUsage,
                ], now()->addHours(2));
            });

            $duration = microtime(true) - $startTime;

            Log::info('ImportRecipesJob completed', [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
                'duration' => round($duration, 2),
            ]);

            Cache::put('import_recipes_status', 'completed', now()->addHours(2));
            Cache::put('import_recipes_last_result', [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
                'duration' => round($duration, 2),
                'finished_at' => now()->toIso8601String(),
            ], now()->addDays(7));

            $discord->sendImportResult($result, $duration, $this->triggeredBy);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Log::error('ImportRecipesJob failed', [
                'error' => $e->getMessage(),
                'duration' => round($duration, 2),
            ]);

            Cache::put('import_recipes_status', 'failed', now()->addHours(2));

            $discord->sendImportResult([
                'imported' => 0,
                'updated' => 0,
                'errors' => [$e->getMessage()],
            ], $duration, $this->triggeredBy);

            throw $e;
        }
    }
}
