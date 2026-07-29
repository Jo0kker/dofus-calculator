<?php

use App\Jobs\ImportRecipesJob;
use App\Services\DiscordWebhookService;
use App\Services\DofusDBImportService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Telescope\Telescope;

beforeEach(function () {
    config()->set('services.discord.webhook_url', 'https://discord.test/webhook');
    Http::fake([
        'https://discord.test/*' => Http::response([], 204),
    ]);
    Cache::flush();

    $this->successfulImportResult = [
        'imported' => 1,
        'updated' => 2,
        'deleted' => 3,
        'deleted_items' => 4,
        'cleanup_performed' => true,
        'item_cleanup_performed' => true,
        'unknown_job_ids' => [],
        'errors' => [],
    ];
});

afterEach(function () {
    Telescope::stopRecording();
    Telescope::flushEntries();
});

it('runs scheduled imports without Telescope recording and notifies Discord', function () {
    $result = $this->successfulImportResult;
    $importService = Mockery::mock(DofusDBImportService::class);
    $importService->shouldReceive('importRecipesFirst')
        ->once()
        ->andReturnUsing(function (int $maxRecipes, int $chunkSize, callable $progress) use ($result) {
            expect($maxRecipes)->toBe(1)
                ->and($chunkSize)->toBe(100)
                ->and(Telescope::isRecording())->toBeFalse();

            $progress(100, 48.5);

            return $result;
        });

    Telescope::startRecording(false);

    $job = new ImportRecipesJob('Planification quotidienne', 1);
    $job->handle($importService, app(DiscordWebhookService::class));

    expect(Telescope::isRecording())->toBeTrue()
        ->and(Cache::get('import_recipes_status'))->toBe('completed');

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhook'
        && str_contains($request->body(), 'Planification quotidienne'));
});

it('notifies Discord when the queue marks a running import as failed', function () {
    Cache::put('import_recipes_status', 'running');
    Cache::put('import_recipes_started_at', now()->subMinute()->toIso8601String());

    $job = new ImportRecipesJob('Planification quotidienne');
    $job->failed(new RuntimeException('Worker timeout'));

    expect(Cache::get('import_recipes_status'))->toBe('failed');

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhook'
        && str_contains($request->body(), 'Worker timeout')
        && str_contains($request->body(), '1m 0s'));
});

it('notifies Discord after a manual recipe import and pauses Telescope only during the import', function () {
    $result = $this->successfulImportResult;
    $importService = Mockery::mock(DofusDBImportService::class);
    $importService->shouldReceive('importRecipesFirst')
        ->once()
        ->andReturnUsing(function (int $maxRecipes, int $chunkSize) use ($result) {
            expect($maxRecipes)->toBe(1)
                ->and($chunkSize)->toBe(100)
                ->and(Telescope::isRecording())->toBeFalse();

            return $result;
        });
    app()->instance(DofusDBImportService::class, $importService);

    Telescope::startRecording(false);

    expect(Artisan::call('dofus:import-recipes', ['--limit' => 1]))->toBe(0)
        ->and(Telescope::isRecording())->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhook'
        && str_contains($request->body(), 'Commande CLI'));
});

it('starts supervised schedulers with dedicated worker memory limits', function () {
    $nixpacks = file_get_contents(base_path('nixpacks.toml'));
    $dockerSupervisor = file_get_contents(base_path('docker/supervisord.conf'));
    $scheduledEvents = collect(app(Schedule::class)->events())->pluck('description');

    expect($nixpacks)
        ->toContain('memory_limit=${QUEUE_MEMORY_LIMIT:-512M}')
        ->toContain('/app/artisan schedule:work')
        ->and($dockerSupervisor)
        ->toContain('memory_limit=512M')
        ->toContain('/var/www/html/artisan schedule:work')
        ->and($scheduledEvents)
        ->toContain('import-recipes-daily')
        ->toContain('recalculate-price-confidence-daily');
});
