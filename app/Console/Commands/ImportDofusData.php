<?php

namespace App\Console\Commands;

use App\Services\DofusDBImportService;
use Illuminate\Console\Command;

class ImportDofusData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dofus:import 
                            {--categories=* : Import specific categories only}
                            {--items=* : Import specific item IDs only}
                            {--with-recipes : Also import recipes for the items}
                            {--limit= : Limit the number of items to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import items and recipes data from DofusDB API (LEGACY - use dofus:import-recipes for better results)';

    /**
     * Execute the console command.
     */
    public function handle(DofusDBImportService $importService)
    {
        $this->info('Starting DofusDB data import...');
        
        $categories = $this->option('categories');
        $itemIds = $this->option('items');
        $withRecipes = $this->option('with-recipes');
        $limit = $this->option('limit');
        
        // Déterminer quels items importer
        if (!empty($categories)) {
            $this->info('Importing categories: ' . implode(', ', $categories));
            $result = $importService->importSpecificCategories($categories);
        } elseif (!empty($itemIds)) {
            $this->info('Importing specific items: ' . implode(', ', $itemIds));
            $result = $importService->importItems(array_map('intval', $itemIds));
        } else {
            $maxItems = $limit ?: 1000;
            $this->info('Importing all items (limit: ' . $maxItems . ')...');
            $result = $importService->importItems([], $maxItems);
        }
        
        // Si l'option --with-recipes est présente, importer aussi les recettes
        if ($withRecipes) {
            $this->info('Importing recipes for the imported items...');
            $recipeResult = $importService->importRecipes([], true);
            
            $result['imported'] += $recipeResult['imported'];
            $result['updated'] += $recipeResult['updated'];
            $result['errors'] = array_merge($result['errors'], $recipeResult['errors']);
        }
        
        $this->info("Import completed!");
        $this->info("- Items/Recipes imported: {$result['imported']}");
        $this->info("- Items/Recipes updated: {$result['updated']}");
        
        if (!empty($result['errors'])) {
            $this->error('Errors encountered:');
            foreach ($result['errors'] as $error) {
                $this->error("  - $error");
            }
        }
        
        return 0;
    }
}
