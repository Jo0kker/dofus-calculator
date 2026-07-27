<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DofusDBImportService
{
    private const API_BASE_URL = 'https://api.dofusdb.fr';

    private const FALLBACK_JOB_NAMES = [
        1 => 'Base',
        2 => 'Bûcheron',
        11 => 'Forgeron',
        13 => 'Sculpteur',
        15 => 'Cordonnier',
        16 => 'Bijoutier',
        24 => 'Mineur',
        26 => 'Alchimiste',
        27 => 'Tailleur',
        28 => 'Paysan',
        36 => 'Pêcheur',
        41 => 'Chasseur',
        44 => 'Forgemage',
        48 => 'Sculptemage',
        60 => 'Façonneur',
        62 => 'Cordomage',
        63 => 'Joaillomage',
        64 => 'Costumage',
        65 => 'Bricoleur',
        74 => 'Façomage',
        75 => 'Parchomage',
        78 => 'Bestiologue',
    ];

    private ?array $jobNames = null;

    private array $unknownJobIds = [];

    /**
     * Get HTTP client with proper headers for DofusDB API
     */
    private function getHttpClient()
    {
        return Http::withHeaders([
            'Referer' => config('app.url', 'https://dofus-calculator.fr'),
            'User-Agent' => 'Dofus-Calculator/1.0 (Compatible; Laravel)',
        ]);
    }

    private function updateOrRestoreItem(array $attributes, array $values): Item
    {
        $item = Item::withTrashed()->updateOrCreate($attributes, $values);

        if ($item->trashed()) {
            $item->restore();
        }

        return $item;
    }

    private function firstOrRestoreItem(array $attributes, array $values): Item
    {
        $item = Item::withTrashed()->firstOrCreate($attributes, $values);

        if ($item->trashed()) {
            $item->restore();
        }

        return $item;
    }

    private function updateOrRestoreRecipe(array $attributes, array $values): Recipe
    {
        $recipe = Recipe::withTrashed()->updateOrCreate($attributes, $values);

        if ($recipe->trashed()) {
            $recipe->restore();
        }

        return $recipe;
    }

    public function importItems(array $itemIds = [], int $maxItems = 1000): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        try {
            if (empty($itemIds)) {
                // L'API limite à 50 par requête, donc on fait plusieurs requêtes
                $skip = 0;
                $limit = 50; // Limite max de l'API
                $totalProcessed = 0;

                while ($totalProcessed < $maxItems) {
                    $response = $this->getHttpClient()->get(self::API_BASE_URL.'/items', [
                        '$limit' => $limit,
                        '$skip' => $skip,
                    ]);

                    if (! $response->successful()) {
                        $errors[] = "Failed to fetch items at skip $skip";
                        break;
                    }

                    $items = $response->json('data', []);
                    if (empty($items)) {
                        // Plus d'items à importer
                        break;
                    }

                    foreach ($items as $itemData) {
                        try {
                            $this->processItem($itemData, $imported, $updated);
                            $totalProcessed++;

                            // Afficher la progression tous les 50 items
                            if ($totalProcessed % 50 === 0) {
                                echo "Processed $totalProcessed items...\n";
                            }

                            if ($totalProcessed >= $maxItems) {
                                break 2; // Sortir des deux boucles
                            }
                        } catch (\Exception $e) {
                            $errors[] = "Item {$itemData['id']}: ".$e->getMessage();
                            Log::error('Failed to import item', [
                                'item_id' => $itemData['id'] ?? 'unknown',
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $skip += $limit;

                    // Pause pour éviter de surcharger l'API
                    usleep(500000); // 0.5 secondes
                }

                return [
                    'imported' => $imported,
                    'updated' => $updated,
                    'errors' => $errors,
                ];
            } else {
                foreach ($itemIds as $itemId) {
                    try {
                        $itemData = $this->fetchItemByDofusId((int) $itemId);

                        if ($itemData) {
                            $this->processItem($itemData, $imported, $updated);
                        } else {
                            $errors[] = "Item {$itemId}: not found in DofusDB";
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Item {$itemId}: ".$e->getMessage();
                        Log::error('Failed to import item', [
                            'item_id' => $itemId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'imported' => $imported,
                    'updated' => $updated,
                    'errors' => $errors,
                ];
            }
        } catch (\Exception $e) {
            Log::error('DofusDB import failed', ['error' => $e->getMessage()]);
            $errors[] = 'Import général: '.$e->getMessage();
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function processItem(array $itemData, int &$imported, int &$updated): void
    {
        // Helper function to extract string from array or return as is
        $extractString = function ($value) {
            if (! isset($value)) {
                return null;
            }
            if (is_array($value)) {
                // Si c'est un tableau avec 'name', utiliser name
                if (isset($value['name'])) {
                    // Si name est aussi un tableau multilangue
                    if (is_array($value['name'])) {
                        return $value['name']['fr'] ?? (count($value['name']) > 0 ? array_values($value['name'])[0] : null);
                    }

                    return $value['name'];
                }

                // Sinon chercher fr ou prendre la première valeur
                return $value['fr'] ?? (count($value) > 0 ? array_values($value)[0] : null);
            }
            // Si c'est une string qui ressemble à un ID MongoDB, retourner null
            if (is_string($value) && preg_match('/^[a-f0-9]{24}$/i', $value)) {
                return null;
            }

            return $value;
        };

        $item = $this->updateOrRestoreItem(
            ['dofusdb_id' => $itemData['id']],
            [
                'name' => $extractString($itemData['name'] ?? null),
                'type' => $extractString($itemData['type'] ?? null),
                'category' => $extractString($itemData['category'] ?? null),
                'level' => $itemData['level'] ?? null,
                'image_url' => $itemData['img'] ?? null,
                'metadata' => [
                    'description' => $itemData['description'] ?? null,
                    'conditions' => $itemData['conditions'] ?? null,
                    'effects' => $itemData['effects'] ?? [],
                ],
            ]
        );

        if ($item->wasRecentlyCreated) {
            $imported++;
        } else {
            $updated++;
        }

        if (! empty($itemData['recipe'])) {
            $this->processRecipe($item, $itemData['recipe']);
        }
    }

    private function processRecipe(Item $item, array $recipeData): void
    {
        DB::transaction(function () use ($item, $recipeData) {
            $recipe = $this->updateOrRestoreRecipe(
                ['item_id' => $item->id],
                [
                    'quantity_produced' => $recipeData['quantity'] ?? 1,
                    'profession' => $recipeData['job'] ?? null,
                    'profession_level' => $recipeData['level'] ?? null,
                ]
            );

            $recipe->ingredients()->detach();

            foreach ($recipeData['ingredients'] ?? [] as $ingredientData) {
                $ingredientItem = $this->firstOrRestoreItem(
                    ['dofusdb_id' => $ingredientData['id']],
                    [
                        'name' => $ingredientData['name']['fr'] ?? $ingredientData['name'] ?? 'Unknown',
                        'image_url' => $ingredientData['img'] ?? null,
                    ]
                );

                $recipe->ingredients()->attach($ingredientItem->id, [
                    'quantity' => $ingredientData['quantity'] ?? 1,
                ]);
            }
        });
    }

    public function importSpecificCategories(array $categories): array
    {
        $itemIds = [];

        foreach ($categories as $category) {
            $response = $this->getHttpClient()->get(self::API_BASE_URL.'/items', [
                'category' => $category,
                'lang' => 'fr',
            ]);

            if ($response->successful()) {
                $items = $response->json('data', []);
                $itemIds = array_merge($itemIds, array_column($items, 'id'));
            }
        }

        return $this->importItems(array_unique($itemIds));
    }

    public function importRecipesFirst(int $maxRecipes = 1000, int $chunkSize = 100, ?callable $progressCallback = null): array
    {
        $imported = 0;
        $updated = 0;
        $deletedRecipes = 0;
        $deletedItems = 0;
        $errors = [];
        $errorCount = 0;
        $maxErrorsStored = 50;
        $reportedTotal = null;
        $documentsSeen = 0;
        $fullScanCompleted = false;
        $scanFailed = false;
        $allRecipesIdentified = true;
        $limitReached = false;
        $cleanupPerformed = false;
        $itemCleanupPerformed = false;
        $activeRecipeResultIds = [];

        $this->jobNames = null;
        $this->unknownJobIds = [];
        $this->jobNames();

        try {
            $skip = 0;
            $limit = 50;
            $totalProcessed = 0;
            $chunkProcessed = 0;

            while ($totalProcessed < $maxRecipes) {
                $response = $this->getHttpClient()->get(self::API_BASE_URL.'/recipes', [
                    '$limit' => $limit,
                    '$skip' => $skip,
                ]);

                if (! $response->successful()) {
                    $errors[] = "Failed to fetch recipes at skip $skip";
                    $scanFailed = true;
                    break;
                }

                if ($reportedTotal === null && is_numeric($response->json('total'))) {
                    $reportedTotal = (int) $response->json('total');
                }

                $recipes = $response->json('data', []);
                $pageCount = count($recipes);

                if ($pageCount === 0) {
                    $fullScanCompleted = $reportedTotal !== null && $documentsSeen >= $reportedTotal;
                    break;
                }

                $documentsSeen += $pageCount;

                foreach ($recipes as $recipeData) {
                    if (is_numeric($recipeData['resultId'] ?? null)) {
                        $activeRecipeResultIds[(int) $recipeData['resultId']] = true;
                    } else {
                        $allRecipesIdentified = false;
                    }

                    try {
                        $this->processRecipeFromAPI($recipeData, $imported, $updated);
                        $totalProcessed++;
                        $chunkProcessed++;

                        if ($chunkProcessed >= $chunkSize) {
                            $this->clearMemory();
                            $chunkProcessed = 0;

                            if ($progressCallback) {
                                $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
                                $progressCallback($totalProcessed, $memoryUsage);
                            }
                        }

                        if ($totalProcessed >= $maxRecipes) {
                            $limitReached = true;
                            break 2;
                        }
                    } catch (\Exception $e) {
                        $errorCount++;
                        $recipeReference = $recipeData['_id']
                            ?? $recipeData['id']
                            ?? $recipeData['resultId']
                            ?? 'unknown';

                        if (count($errors) < $maxErrorsStored) {
                            $errors[] = "Recipe {$recipeReference}: ".$e->getMessage();
                        }

                        Log::error('Failed to import recipe', [
                            'recipe_id' => $recipeReference,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                unset($recipes, $response);

                if ($reportedTotal !== null && $documentsSeen >= $reportedTotal) {
                    $fullScanCompleted = true;
                    break;
                }

                $skip += $limit;
                usleep(500000);
            }

            if ($errorCount > $maxErrorsStored) {
                $errors[] = '... and '.($errorCount - $maxErrorsStored).' more errors (check logs for details)';
            }

            if (
                $fullScanCompleted
                && ! $scanFailed
                && $errorCount === 0
                && $allRecipesIdentified
                && $reportedTotal !== null
                && $reportedTotal > 0
                && $documentsSeen >= $reportedTotal
            ) {
                $deletedRecipes = $this->deleteMissingRecipes($activeRecipeResultIds);
                $cleanupPerformed = true;

                $itemCleanup = $this->deleteItemsMissingFromDofusDB();
                $deletedItems = $itemCleanup['deleted'];
                $itemCleanupPerformed = $itemCleanup['cleanup_performed'];

                if ($itemCleanup['error']) {
                    $errors[] = $itemCleanup['error'];
                }
            } else {
                Log::info('DofusDB stale data cleanup skipped', [
                    'full_scan_completed' => $fullScanCompleted,
                    'scan_failed' => $scanFailed,
                    'processing_errors' => $errorCount,
                    'all_recipes_identified' => $allRecipesIdentified,
                    'limit_reached' => $limitReached,
                    'reported_total' => $reportedTotal,
                    'documents_seen' => $documentsSeen,
                ]);
            }
        } catch (\Exception $e) {
            $scanFailed = true;
            Log::error('DofusDB recipe import failed', ['error' => $e->getMessage()]);
            $errors[] = 'Import recettes: '.$e->getMessage();
        }

        $this->clearMemory();

        $unknownJobIds = array_map('intval', array_keys($this->unknownJobIds));
        sort($unknownJobIds);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'deleted' => $deletedRecipes,
            'deleted_items' => $deletedItems,
            'cleanup_performed' => $cleanupPerformed,
            'item_cleanup_performed' => $itemCleanupPerformed,
            'unknown_job_ids' => $unknownJobIds,
            'errors' => $errors,
        ];
    }

    /**
     * Libère la mémoire en forçant le garbage collection
     */
    private function clearMemory(): void
    {
        // Forcer le garbage collector à libérer les cycles de référence
        gc_collect_cycles();
    }

    /**
     * Supprime les recettes absentes du scan complet sans requête NOT IN massive.
     * Le tableau est un ensemble d’IDs DofusDB et les lignes locales sont parcourues par lots.
     */
    private function deleteMissingRecipes(array $activeResultIds): int
    {
        return DB::transaction(function () use ($activeResultIds) {
            $deleted = 0;

            Recipe::query()
                ->with('item:id,dofusdb_id')
                ->chunkById(500, function ($recipes) use ($activeResultIds, &$deleted) {
                    $idsToDelete = [];

                    foreach ($recipes as $recipe) {
                        $dofusdbId = $recipe->item?->dofusdb_id;

                        if ($dofusdbId === null || ! isset($activeResultIds[(int) $dofusdbId])) {
                            $idsToDelete[] = $recipe->id;
                        }
                    }

                    if ($idsToDelete) {
                        $deleted += Recipe::query()->whereKey($idsToDelete)->delete();
                    }
                });

            return $deleted;
        });
    }

    /**
     * Récupère uniquement les IDs actuels de DofusDB puis supprime les items locaux absents.
     * Environ 22 000 clés entières sont conservées temporairement, puis libérées en fin d’import.
     */
    private function deleteItemsMissingFromDofusDB(): array
    {
        $activeItemIds = [];
        $skip = 0;
        $limit = 50;
        $reportedTotal = null;
        $documentsSeen = 0;
        $fullScanCompleted = false;
        $allItemsIdentified = true;

        try {
            while (true) {
                $response = $this->getHttpClient()->get(self::API_BASE_URL.'/items', [
                    '$limit' => $limit,
                    '$skip' => $skip,
                    '$select' => ['id'],
                    '$sort' => ['id' => 1],
                ]);

                if (! $response->successful()) {
                    throw new \RuntimeException("Failed to fetch item inventory at skip $skip");
                }

                if ($reportedTotal === null && is_numeric($response->json('total'))) {
                    $reportedTotal = (int) $response->json('total');
                }

                $items = $response->json('data', []);
                $pageCount = count($items);

                if ($pageCount === 0) {
                    $fullScanCompleted = $reportedTotal !== null && $documentsSeen >= $reportedTotal;
                    break;
                }

                $pageItemIds = [];

                foreach ($items as $itemData) {
                    if (is_numeric($itemData['id'] ?? null)) {
                        $itemId = (int) $itemData['id'];
                        $activeItemIds[$itemId] = true;
                        $pageItemIds[] = $itemId;
                    } else {
                        $allItemsIdentified = false;
                    }
                }

                if ($pageItemIds) {
                    Item::onlyTrashed()
                        ->whereIn('dofusdb_id', $pageItemIds)
                        ->restore();
                }

                $documentsSeen += $pageCount;
                unset($items, $response);

                if ($reportedTotal !== null && $documentsSeen >= $reportedTotal) {
                    $fullScanCompleted = true;
                    break;
                }

                $skip += $limit;
                usleep(100000);
            }

            if (
                ! $fullScanCompleted
                || ! $allItemsIdentified
                || $reportedTotal === null
                || $reportedTotal <= 0
                || $documentsSeen < $reportedTotal
                || count($activeItemIds) !== $reportedTotal
            ) {
                Log::warning('DofusDB stale item cleanup skipped', [
                    'full_scan_completed' => $fullScanCompleted,
                    'all_items_identified' => $allItemsIdentified,
                    'reported_total' => $reportedTotal,
                    'documents_seen' => $documentsSeen,
                    'unique_ids_seen' => count($activeItemIds),
                ]);

                return [
                    'deleted' => 0,
                    'cleanup_performed' => false,
                    'error' => 'Nettoyage des items ignoré : inventaire DofusDB incomplet.',
                ];
            }

            $deleted = DB::transaction(function () use ($activeItemIds) {
                $deleted = 0;

                Item::query()
                    ->select(['id', 'dofusdb_id'])
                    ->chunkById(500, function ($items) use ($activeItemIds, &$deleted) {
                        $idsToDelete = [];

                        foreach ($items as $item) {
                            if (! isset($activeItemIds[(int) $item->dofusdb_id])) {
                                $idsToDelete[] = $item->id;
                            }
                        }

                        if ($idsToDelete) {
                            $deleted += Item::query()->whereKey($idsToDelete)->delete();
                        }
                    });

                return $deleted;
            });

            return [
                'deleted' => $deleted,
                'cleanup_performed' => true,
                'error' => null,
            ];
        } catch (\Exception $e) {
            Log::error('DofusDB item inventory failed; no items deleted', [
                'error' => $e->getMessage(),
                'reported_total' => $reportedTotal,
                'documents_seen' => $documentsSeen,
            ]);

            return [
                'deleted' => 0,
                'cleanup_performed' => false,
                'error' => 'Nettoyage des items ignoré : '.$e->getMessage(),
            ];
        }
    }

    private function jobNames(): array
    {
        if ($this->jobNames !== null) {
            return $this->jobNames;
        }

        $this->jobNames = self::FALLBACK_JOB_NAMES;

        try {
            $skip = 0;
            $limit = 50;
            $reportedTotal = null;

            do {
                $response = $this->getHttpClient()->get(self::API_BASE_URL.'/jobs', [
                    '$limit' => $limit,
                    '$skip' => $skip,
                    'lang' => 'fr',
                ]);

                if (! $response->successful()) {
                    throw new \RuntimeException("Failed to fetch jobs at skip $skip");
                }

                if ($reportedTotal === null && is_numeric($response->json('total'))) {
                    $reportedTotal = (int) $response->json('total');
                }

                $jobs = $response->json('data', []);
                $pageCount = count($jobs);

                foreach ($jobs as $job) {
                    $jobId = $job['id'] ?? null;
                    $jobName = $this->extractLocalizedString($job['name'] ?? null);

                    if (is_numeric($jobId) && $jobName) {
                        $this->jobNames[(int) $jobId] = $jobName;
                    }
                }

                $skip += $pageCount;
                unset($jobs, $response);
            } while (
                $pageCount > 0
                && $skip < 1000
                && ($reportedTotal === null ? $pageCount === $limit : $skip < $reportedTotal)
            );
        } catch (\Exception $e) {
            Log::warning('Failed to refresh DofusDB jobs, using fallback mapping', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->jobNames;
    }

    private function resolveJobName(mixed $jobId): string
    {
        if (! is_numeric($jobId)) {
            return 'Métier inconnu';
        }

        $jobId = (int) $jobId;
        $jobName = $this->jobNames()[$jobId] ?? null;

        if ($jobName) {
            return $jobName;
        }

        if (! isset($this->unknownJobIds[$jobId])) {
            Log::warning('Unknown DofusDB job encountered', ['job_id' => $jobId]);
        }

        $this->unknownJobIds[$jobId] = true;

        return "Métier inconnu (#{$jobId})";
    }

    private function extractLocalizedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            if (isset($value['name'])) {
                return $this->extractLocalizedString($value['name']);
            }

            return $this->extractLocalizedString($value['fr'] ?? (array_values($value)[0] ?? null));
        }

        if (is_string($value) && preg_match('/^[a-f0-9]{24}$/i', $value)) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    public function importRecipes(array $recipeIds = [], bool $importDependencies = true): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $processedRecipes = [];
        $recipesToProcess = [];

        try {
            if (empty($recipeIds)) {
                $response = $this->getHttpClient()->get(self::API_BASE_URL.'/recipes', [
                    '$limit' => 500,
                ]);

                if ($response->successful()) {
                    $recipes = $response->json('data', []);
                    foreach ($recipes as $recipeData) {
                        $recipesToProcess[] = $recipeData;
                    }
                }
            } else {
                foreach ($recipeIds as $recipeId) {
                    $response = $this->getHttpClient()->get(self::API_BASE_URL."/recipes/{$recipeId}");

                    if ($response->successful()) {
                        $recipesToProcess[] = $response->json();
                    } else {
                        $errors[] = "Recipe {$recipeId}: API error ".$response->status();
                    }
                }
            }

            // Traiter les recettes et leurs dépendances
            foreach ($recipesToProcess as $recipeData) {
                $this->processRecipeWithDependencies($recipeData, $imported, $updated, $errors, $processedRecipes, $importDependencies);
            }

        } catch (\Exception $e) {
            Log::error('DofusDB recipe import failed', ['error' => $e->getMessage()]);
            $errors[] = 'Import recettes: '.$e->getMessage();
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function processRecipeWithDependencies(array $recipeData, int &$imported, int &$updated, array &$errors, array &$processedRecipes, bool $importDependencies): void
    {
        $recipeId = $recipeData['_id'] ?? $recipeData['id'];

        // Éviter de traiter la même recette plusieurs fois
        if (isset($processedRecipes[$recipeId])) {
            return;
        }

        try {
            // D'abord, importer les recettes des ingrédients si elles existent
            if ($importDependencies) {
                foreach ($recipeData['ingredients'] ?? [] as $ingredientData) {
                    if (isset($ingredientData['hasRecipe']) && $ingredientData['hasRecipe']) {
                        $this->importIngredientRecipes($ingredientData['id'], $imported, $updated, $errors, $processedRecipes);
                    }
                }
            }

            // Ensuite traiter la recette actuelle
            $this->processRecipeFromAPI($recipeData, $imported, $updated);
            $processedRecipes[$recipeId] = true;

        } catch (\Exception $e) {
            $errors[] = "Recipe {$recipeId}: ".$e->getMessage();
            Log::error('Failed to import recipe with dependencies', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function importIngredientRecipes(int $itemId, int &$imported, int &$updated, array &$errors, array &$processedRecipes): void
    {
        try {
            $response = Http::get(self::API_BASE_URL.'/recipes', [
                'resultId' => $itemId,
                '$limit' => 10,
            ]);

            if ($response->successful()) {
                $recipes = $response->json('data', []);
                foreach ($recipes as $recipeData) {
                    $this->processRecipeWithDependencies($recipeData, $imported, $updated, $errors, $processedRecipes, true);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to import ingredient recipes', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processRecipeFromAPI(array $recipeData, int &$imported, int &$updated): void
    {
        // Helper function to extract string from array or return as is
        $extractString = function ($value) {
            if (! isset($value)) {
                return null;
            }
            if (is_array($value)) {
                // Si c'est un tableau avec 'name', utiliser name
                if (isset($value['name'])) {
                    if (is_array($value['name'])) {
                        return $value['name']['fr'] ?? (count($value['name']) > 0 ? array_values($value['name'])[0] : null);
                    }

                    return $value['name'];
                }

                return $value['fr'] ?? (count($value) > 0 ? array_values($value)[0] : null);
            }
            // Si c'est une string qui ressemble à un ID MongoDB, retourner null
            if (is_string($value) && preg_match('/^[a-f0-9]{24}$/i', $value)) {
                return null;
            }

            return $value;
        };

        // Créer l'item résultant (on a seulement le nom et l'ID dans les recettes)
        $resultItem = $this->updateOrRestoreItem(
            ['dofusdb_id' => $recipeData['resultId']],
            [
                'name' => $extractString($recipeData['resultName'] ?? null),
                // Les autres détails de l'item seront récupérés plus tard si nécessaire
            ]
        );

        // Si l'item résultat a un nom temporaire ou vient d'être créé, récupérer ses détails
        $resultItemNeedsUpdate = $resultItem->wasRecentlyCreated ||
            str_starts_with($resultItem->name ?? '', 'Item ') ||
            empty($resultItem->type);

        if ($resultItemNeedsUpdate) {
            $this->fetchItemDetails([$recipeData['resultId']]);
        }

        DB::transaction(function () use ($resultItem, $recipeData, &$imported, &$updated) {
            $jobName = $this->resolveJobName($recipeData['jobId'] ?? null);

            $recipe = $this->updateOrRestoreRecipe(
                ['item_id' => $resultItem->id],
                [
                    'quantity_produced' => 1, // Par défaut 1 pour DofusDB
                    'profession' => $jobName,
                    'profession_level' => $recipeData['resultLevel'] ?? null,
                ]
            );

            if ($recipe->wasRecentlyCreated) {
                $imported++;
            } elseif ($recipe->wasChanged()) {
                $updated++;
            }

            // Supprimer les anciens ingrédients
            $recipe->ingredients()->detach();

            // Traiter les ingrédients avec leurs quantités (on n'a que les IDs)
            $ingredientIds = $recipeData['ingredientIds'] ?? [];
            $quantities = $recipeData['quantities'] ?? [];

            // Créer les items ingrédients et récupérer leurs détails
            $ingredientItemsToFetch = [];

            foreach ($ingredientIds as $index => $ingredientId) {
                // Créer/trouver l'item ingrédient
                $ingredientItem = $this->firstOrRestoreItem(
                    ['dofusdb_id' => $ingredientId],
                    [
                        'name' => "Item $ingredientId", // Nom temporaire
                    ]
                );

                // Si c'est un nouvel item ou qu'il a un nom temporaire, on devra le mettre à jour
                if ($ingredientItem->wasRecentlyCreated || str_starts_with($ingredientItem->name, 'Item ')) {
                    $ingredientItemsToFetch[] = $ingredientId;
                }

                // Attacher l'ingrédient avec sa quantité
                $quantity = $quantities[$index] ?? 1;
                $recipe->ingredients()->attach($ingredientItem->id, [
                    'quantity' => $quantity,
                ]);
            }

            // Récupérer les détails des items ingrédients
            if (! empty($ingredientItemsToFetch)) {
                $this->fetchItemDetails($ingredientItemsToFetch);
            }
        });
    }

    private function fetchItemByDofusId(int $itemId): ?array
    {
        $response = $this->getHttpClient()->get(self::API_BASE_URL.'/items', [
            'id' => $itemId,
            '$limit' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to fetch DofusDB item {$itemId}: HTTP {$response->status()}");
        }

        $itemData = $response->json('data.0');

        if (! is_array($itemData) || (int) ($itemData['id'] ?? 0) !== $itemId) {
            return null;
        }

        return $itemData;
    }

    /**
     * Récupérer les détails complets des items par leurs IDs
     * Traite par batch pour limiter l'utilisation mémoire
     */
    private function fetchItemDetails(array $itemIds): void
    {
        // Limiter le nombre d'items à récupérer par appel
        $batchSize = 10;
        $batches = array_chunk($itemIds, $batchSize);

        foreach ($batches as $batchIds) {
            foreach ($batchIds as $itemId) {
                try {
                    $itemData = $this->fetchItemByDofusId((int) $itemId);

                    if ($itemData) {
                        $this->updateItemFromAPI($itemData);
                        unset($itemData);
                    }

                    // Petite pause pour ne pas surcharger l'API
                    usleep(100000); // 0.1 secondes

                } catch (\Exception $e) {
                    Log::warning('Failed to fetch item details', [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Libérer la mémoire après chaque batch
            gc_collect_cycles();
        }
    }

    /**
     * Mettre à jour un item avec les données de l'API
     */
    private function updateItemFromAPI(array $itemData): void
    {
        // Helper function to extract string from array or return as is
        $extractString = function ($value) {
            if (! isset($value)) {
                return null;
            }
            if (is_array($value)) {
                // Si c'est un tableau avec 'name', utiliser name
                if (isset($value['name'])) {
                    if (is_array($value['name'])) {
                        return $value['name']['fr'] ?? (count($value['name']) > 0 ? array_values($value['name'])[0] : null);
                    }

                    return $value['name'];
                }

                return $value['fr'] ?? (count($value) > 0 ? array_values($value)[0] : null);
            }
            // Si c'est une string qui ressemble à un ID MongoDB, retourner null
            if (is_string($value) && preg_match('/^[a-f0-9]{24}$/i', $value)) {
                return null;
            }

            return $value;
        };

        $this->updateOrRestoreItem(
            ['dofusdb_id' => $itemData['id']],
            [
                'name' => $extractString($itemData['name'] ?? null),
                'type' => $extractString($itemData['type'] ?? null),
                'category' => $extractString($itemData['category'] ?? null),
                'level' => $itemData['level'] ?? null,
                'image_url' => $itemData['img'] ?? null,
                'metadata' => [
                    'description' => $itemData['description'] ?? null,
                    'conditions' => $itemData['conditions'] ?? null,
                    'effects' => $itemData['effects'] ?? [],
                ],
            ]
        );
    }

    public function updatePricesFromCommunity(): void
    {
        // Cette méthode pourrait être utilisée pour intégrer avec d'autres sources de prix
        // Pour l'instant, les prix sont uniquement saisis par les utilisateurs
    }
}
