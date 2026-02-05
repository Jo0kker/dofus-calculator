<?php

namespace App\Jobs;

use App\Services\DiscordWebhookService;
use App\Services\DofusDBImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportRecipesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1;

    private const MAX_RECIPES_DEFAULT = 10000;

    public function __construct(
        private ?string $triggeredBy = null,
        private int $maxRecipes = self::MAX_RECIPES_DEFAULT,
    ) {}

    public function failed(?\Throwable $exception): void
    {
        // Only update cache if handle() didn't already set a terminal status
        // (e.g. job killed by timeout before catch block could run)
        $currentStatus = Cache::get('import_recipes_status');
        if ($currentStatus === 'running') {
            Cache::put('import_recipes_status', 'failed', now()->addHours(6));
            Cache::forget('import_recipes_progress');
        }

        Log::error('ImportRecipesJob marked as failed by queue', [
            'error' => $exception?->getMessage(),
        ]);
    }

    public function handle(DofusDBImportService $importService, DiscordWebhookService $discord): void
    {
        Cache::put('import_recipes_status', 'running', now()->addHours(6));
        Cache::put('import_recipes_started_at', now()->toIso8601String(), now()->addHours(6));

        DB::disableQueryLog();

        $startTime = microtime(true);
        $importException = null;

        Log::info('ImportRecipesJob started', ['triggered_by' => $this->triggeredBy]);

        try {
            $result = $importService->importRecipesFirst($this->maxRecipes, 100, function ($processed, $memoryUsage) {
                Cache::put('import_recipes_progress', [
                    'processed' => $processed,
                    'memory' => $memoryUsage,
                ], now()->addHours(6));
            });

            $duration = microtime(true) - $startTime;

            Log::info('ImportRecipesJob completed', [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
                'duration' => round($duration, 2),
            ]);

            Cache::put('import_recipes_status', 'completed', now()->addHours(6));
            Cache::forget('import_recipes_progress');
            Cache::put('import_recipes_last_result', [
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'errors_count' => count($result['errors']),
                'duration' => round($duration, 2),
                'finished_at' => now()->toIso8601String(),
            ], now()->addDays(7));

            try {
                $discord->sendImportResult($result, $duration, $this->triggeredBy);
            } catch (\Exception $e) {
                Log::warning('Failed to send Discord notification after successful import', [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            $importException = $e;
            $duration = microtime(true) - $startTime;
            $isTransient = $e instanceof ConnectionException;

            Log::error('ImportRecipesJob failed', [
                'error' => $e->getMessage(),
                'type' => $isTransient ? 'transient (connection)' : 'permanent',
                'exception_class' => get_class($e),
                'duration' => round($duration, 2),
            ]);

            Cache::put('import_recipes_status', 'failed', now()->addHours(6));
            Cache::forget('import_recipes_progress');
            Cache::put('import_recipes_last_result', [
                'imported' => 0,
                'updated' => 0,
                'errors_count' => 1,
                'duration' => round($duration, 2),
                'finished_at' => now()->toIso8601String(),
            ], now()->addDays(7));

            try {
                $discord->sendImportResult([
                    'imported' => 0,
                    'updated' => 0,
                    'errors' => [$e->getMessage()],
                ], $duration, $this->triggeredBy);
            } catch (\Exception $discordException) {
                Log::warning('Failed to send Discord notification after failed import', [
                    'error' => $discordException->getMessage(),
                ]);
            }
        } finally {
            DB::enableQueryLog();
        }

        if ($importException) {
            throw $importException;
        }
    }
}
