<?php

namespace App\Console\Commands;

use App\Services\DofusDBImportService;
use Illuminate\Console\Command;

class ImportRecipes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dofus:import-recipes
                            {--limit=500 : Maximum number of recipes to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import recipes from DofusDB (recipe-first approach)';

    /**
     * Execute the console command.
     */
    public function handle(DofusDBImportService $importService)
    {
        $this->info('Starting DofusDB recipes import (recipe-first approach)...');

        $limit = (int) $this->option('limit');

        $this->info("Importing up to $limit recipes with their items and ingredients...");

        $result = $importService->importRecipesFirst($limit);

        $this->info("Import completed!");
        $this->info("- Recipes imported: {$result['imported']}");
        $this->info("- Recipes updated: {$result['updated']}");

        if (!empty($result['errors'])) {
            $this->error('Errors encountered:');
            foreach ($result['errors'] as $error) {
                $this->error("  - $error");
            }
        }

        $this->info('');
        $this->info('Recipe-first import ensures all imported items have recipes!');
        $this->info('This approach is perfect for a profitability calculator.');

        return 0;
    }
}
