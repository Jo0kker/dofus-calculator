<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DofusDBImportService
{
    private const API_BASE_URL = 'https://api.dofusdb.fr';
    private const BATCH_SIZE = 100;

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
                    $response = $this->getHttpClient()->get(self::API_BASE_URL . '/items', [
                        '$limit' => $limit,
                        '$skip' => $skip,
                    ]);
                    
                    if (!$response->successful()) {
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
                            $errors[] = "Item {$itemData['id']}: " . $e->getMessage();
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
                // Pour FeathersJS, on fait plusieurs requêtes pour des IDs spécifiques
                foreach ($itemIds as $itemId) {
                    $response = $this->getHttpClient()->get(self::API_BASE_URL . "/items/{$itemId}");

                    if ($response->successful()) {
                        $itemData = $response->json();
                        try {
                            $this->processItem($itemData, $imported, $updated);
                        } catch (\Exception $e) {
                            $errors[] = "Item {$itemId}: " . $e->getMessage();
                            Log::error('Failed to import item', [
                                'item_id' => $itemId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $errors[] = "Item {$itemId}: API error " . $response->status();
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
            $errors[] = 'Import général: ' . $e->getMessage();
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
        $extractString = function($value) {
            if (!isset($value)) return null;
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

        $item = Item::updateOrCreate(
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

        if (!empty($itemData['recipe'])) {
            $this->processRecipe($item, $itemData['recipe']);
        }
    }

    private function processRecipe(Item $item, array $recipeData): void
    {
        DB::transaction(function () use ($item, $recipeData) {
            $recipe = Recipe::updateOrCreate(
                ['item_id' => $item->id],
                [
                    'quantity_produced' => $recipeData['quantity'] ?? 1,
                    'profession' => $recipeData['job'] ?? null,
                    'profession_level' => $recipeData['level'] ?? null,
                ]
            );

            $recipe->ingredients()->detach();

            foreach ($recipeData['ingredients'] ?? [] as $ingredientData) {
                $ingredientItem = Item::firstOrCreate(
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

    private function fetchAllItemIds(): array
    {
        $response = $this->getHttpClient()->get(self::API_BASE_URL . '/items/list');

        if ($response->successful()) {
            return array_column($response->json('data', []), 'id');
        }

        throw new \Exception('Failed to fetch item list from DofusDB');
    }

    public function importSpecificCategories(array $categories): array
    {
        $itemIds = [];

        foreach ($categories as $category) {
            $response = $this->getHttpClient()->get(self::API_BASE_URL . '/items', [
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

    public function importRecipesFirst(int $maxRecipes = 1000): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];
        
        try {
            // Paginer les recettes comme pour les items
            $skip = 0;
            $limit = 50; // Limite max de l'API
            $totalProcessed = 0;
            
            while ($totalProcessed < $maxRecipes) {
                $response = $this->getHttpClient()->get(self::API_BASE_URL . '/recipes', [
                    '$limit' => $limit,
                    '$skip' => $skip,
                ]);
                
                if (!$response->successful()) {
                    $errors[] = "Failed to fetch recipes at skip $skip";
                    break;
                }
                
                $recipes = $response->json('data', []);
                if (empty($recipes)) {
                    // Plus de recettes à importer
                    break;
                }
                
                foreach ($recipes as $recipeData) {
                    try {
                        $this->processRecipeFromAPI($recipeData, $imported, $updated);
                        $totalProcessed++;
                        
                        // Afficher la progression tous les 50 recettes
                        if ($totalProcessed % 50 === 0) {
                            echo "Processed $totalProcessed recipes...\n";
                        }
                        
                        if ($totalProcessed >= $maxRecipes) {
                            break 2; // Sortir des deux boucles
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Recipe {$recipeData['_id']}: " . $e->getMessage();
                        Log::error('Failed to import recipe', [
                            'recipe_id' => $recipeData['_id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                $skip += $limit;
                
                // Pause pour éviter de surcharger l'API
                usleep(500000); // 0.5 secondes
            }
        } catch (\Exception $e) {
            Log::error('DofusDB recipe import failed', ['error' => $e->getMessage()]);
            $errors[] = 'Import recettes: ' . $e->getMessage();
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
        ];
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
                $response = $this->getHttpClient()->get(self::API_BASE_URL . '/recipes', [
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
                    $response = $this->getHttpClient()->get(self::API_BASE_URL . "/recipes/{$recipeId}");

                    if ($response->successful()) {
                        $recipesToProcess[] = $response->json();
                    } else {
                        $errors[] = "Recipe {$recipeId}: API error " . $response->status();
                    }
                }
            }

            // Traiter les recettes et leurs dépendances
            foreach ($recipesToProcess as $recipeData) {
                $this->processRecipeWithDependencies($recipeData, $imported, $updated, $errors, $processedRecipes, $importDependencies);
            }

        } catch (\Exception $e) {
            Log::error('DofusDB recipe import failed', ['error' => $e->getMessage()]);
            $errors[] = 'Import recettes: ' . $e->getMessage();
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
            $errors[] = "Recipe {$recipeId}: " . $e->getMessage();
            Log::error('Failed to import recipe with dependencies', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function importIngredientRecipes(int $itemId, int &$imported, int &$updated, array &$errors, array &$processedRecipes): void
    {
        try {
            $response = Http::get(self::API_BASE_URL . '/recipes', [
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
        $extractString = function($value) {
            if (!isset($value)) return null;
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
        $resultItem = Item::updateOrCreate(
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

        DB::transaction(function () use ($resultItem, $recipeData, &$imported, &$updated, $extractString) {
            // Mapper les job IDs aux noms de métiers
            $jobNames = [
                11 => 'Forgeron d\'épées',
                13 => 'Forgeron de dagues', 
                14 => 'Forgeron de marteaux',
                15 => 'Forgeur de pelles',
                16 => 'Sculpteur d\'arcs',
                17 => 'Sculpteur de baguettes',
                18 => 'Sculpteur de bâtons',
                // Ajoutez d'autres mappings selon vos besoins
            ];
            
            $jobName = $jobNames[$recipeData['jobId'] ?? null] ?? 'Métier inconnu';

            $recipe = Recipe::updateOrCreate(
                ['item_id' => $resultItem->id],
                [
                    'quantity_produced' => 1, // Par défaut 1 pour DofusDB
                    'profession' => $jobName,
                    'profession_level' => $recipeData['resultLevel'] ?? null,
                ]
            );

            if ($recipe->wasRecentlyCreated) {
                $imported++;
            } else {
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
                $ingredientItem = Item::firstOrCreate(
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
            if (!empty($ingredientItemsToFetch)) {
                $this->fetchItemDetails($ingredientItemsToFetch);
            }
        });
    }

    /**
     * Récupérer les détails complets des items par leurs IDs
     */
    private function fetchItemDetails(array $itemIds): void
    {
        foreach ($itemIds as $itemId) {
            try {
                $response = $this->getHttpClient()->get(self::API_BASE_URL . "/items/{$itemId}");
                
                if ($response->successful()) {
                    $itemData = $response->json();
                    $this->updateItemFromAPI($itemData);
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
    }
    
    /**
     * Mettre à jour un item avec les données de l'API
     */
    private function updateItemFromAPI(array $itemData): void
    {
        // Helper function to extract string from array or return as is
        $extractString = function($value) {
            if (!isset($value)) return null;
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

        Item::updateOrCreate(
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
