<?php

namespace App\Console\Commands;

use App\Services\DofusDBImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportRecipes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dofus:import-recipes
                            {--limit= : Maximum number of recipes to import (default: all)}
                            {--chunk-size=100 : Number of recipes to process before clearing memory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import recipes from DofusDB (recipe-first approach) with memory optimization';

    /**
     * Execute the console command.
     */
    public function handle(DofusDBImportService $importService)
    {
        // Désactiver le query log pour économiser la mémoire
        DB::disableQueryLog();

        $this->info('Starting DofusDB recipes import (recipe-first approach)...');
        $this->info('Memory optimization enabled.');

        $limit = $this->option('limit');
        $chunkSize = (int) $this->option('chunk-size');

        if ($limit) {
            $limit = (int) $limit;
            $this->info("Importing up to $limit recipes with their items and ingredients...");
        } else {
            $limit = PHP_INT_MAX; // Import toutes les recettes disponibles
            $this->info("Importing ALL available recipes with their items and ingredients...");
        }

        $this->info("Chunk size: $chunkSize (memory cleared every $chunkSize recipes)");

        $result = $importService->importRecipesFirst($limit, $chunkSize, function($processed, $memoryUsage) {
            $this->line("  → Processed $processed recipes | Memory: {$memoryUsage}MB");
        });

        $this->newLine();
        $this->info("Import completed!");
        $this->info("- Recipes imported: {$result['imported']}");
        $this->info("- Recipes updated: {$result['updated']}");

        if (!empty($result['errors'])) {
            $this->warn('Errors encountered: ' . count($result['errors']));
            if ($this->getOutput()->isVerbose()) {
                foreach ($result['errors'] as $error) {
                    $this->error("  - $error");
                }
            } else {
                $this->line('  Use -v flag to see error details');
            }
        }

        $this->newLine();
        $this->info('Recipe-first import ensures all imported items have recipes!');
        $this->info('This approach is perfect for a profitability calculator.');

        return 0;
    }
}
