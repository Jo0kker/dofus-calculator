<?php

use App\Models\Item;
use App\Models\Recipe;
use App\Models\User;
use App\Services\DofusDBImportService;
use Illuminate\Support\Facades\Http;

it('loads new professions from DofusDB and removes recipes missing from a complete scan', function () {
    $staleItem = Item::create([
        'dofusdb_id' => 700001,
        'name' => 'Ancien résultat',
        'type' => 'Objet obsolète',
    ]);
    $staleRecipe = Recipe::create([
        'item_id' => $staleItem->id,
        'profession' => 'Ancien métier',
    ]);
    $favoriteOwner = User::factory()->create();
    $favoriteOwner->favoriteItems()->attach($staleItem->id);

    $resultItem = Item::create([
        'dofusdb_id' => 700002,
        'name' => 'Résultat actuel',
        'type' => 'Objet d’élevage',
    ]);
    $ingredient = Item::create([
        'dofusdb_id' => 700003,
        'name' => 'Ingrédient actuel',
        'type' => 'Ressource',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [
                ['id' => 999, 'name' => ['fr' => 'Éleveur']],
            ],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 1,
            'data' => [[
                '_id' => 'recipe-current',
                'resultId' => $resultItem->dofusdb_id,
                'resultName' => $resultItem->name,
                'resultLevel' => 20,
                'jobId' => 999,
                'ingredientIds' => [$ingredient->dofusdb_id],
                'quantities' => [2],
            ]],
        ]),
        'https://api.dofusdb.fr/items*' => Http::response([
            'total' => 2,
            'data' => [
                ['id' => $resultItem->dofusdb_id],
                ['id' => $ingredient->dofusdb_id],
            ],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result)
        ->deleted->toBe(1)
        ->deleted_items->toBe(1)
        ->cleanup_performed->toBeTrue()
        ->item_cleanup_performed->toBeTrue()
        ->unknown_job_ids->toBe([]);

    $recipe = $resultItem->fresh()->recipe;

    expect($recipe->profession)->toBe('Éleveur')
        ->and($recipe->profession_level)->toBe(20)
        ->and($recipe->ingredients()->first()->id)->toBe($ingredient->id)
        ->and($recipe->ingredients()->first()->pivot->quantity)->toBe(2);

    $this->assertSoftDeleted('recipes', ['id' => $staleRecipe->id]);
    $this->assertSoftDeleted('items', ['id' => $staleItem->id]);
    $this->assertDatabaseHas('user_favorites', [
        'user_id' => $favoriteOwner->id,
        'item_id' => $staleItem->id,
    ]);
    expect($favoriteOwner->favoriteItems()->whereKey($staleItem->id)->exists())->toBeFalse();
    $this->actingAs($favoriteOwner)
        ->get(route('items.show', $staleItem->id))
        ->assertNotFound();
});

it('never deletes stale recipes when the import stops at a limit', function () {
    $staleItem = Item::create([
        'dofusdb_id' => 700010,
        'name' => 'Recette à conserver',
        'type' => 'Objet',
    ]);
    $staleRecipe = Recipe::create([
        'item_id' => $staleItem->id,
        'profession' => 'Forgeron',
    ]);
    $resultItem = Item::create([
        'dofusdb_id' => 700011,
        'name' => 'Premier résultat',
        'type' => 'Objet',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [['id' => 11, 'name' => ['fr' => 'Forgeron']]],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 2,
            'data' => [
                [
                    '_id' => 'recipe-first',
                    'resultId' => $resultItem->dofusdb_id,
                    'resultName' => $resultItem->name,
                    'resultLevel' => 1,
                    'jobId' => 11,
                    'ingredientIds' => [],
                    'quantities' => [],
                ],
                [
                    '_id' => 'recipe-not-processed',
                    'resultId' => 700012,
                    'resultName' => 'Second résultat',
                    'resultLevel' => 1,
                    'jobId' => 11,
                    'ingredientIds' => [],
                    'quantities' => [],
                ],
            ],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(1);

    expect($result)
        ->deleted->toBe(0)
        ->cleanup_performed->toBeFalse();

    $this->assertDatabaseHas('recipes', ['id' => $staleRecipe->id]);
});

it('never deletes stale recipes when a later DofusDB page fails', function () {
    $staleItem = Item::create([
        'dofusdb_id' => 700015,
        'name' => 'Recette protégée pendant une panne',
        'type' => 'Objet',
    ]);
    $staleRecipe = Recipe::create([
        'item_id' => $staleItem->id,
        'profession' => 'Forgeron',
    ]);
    $resultItem = Item::create([
        'dofusdb_id' => 700016,
        'name' => 'Résultat de la première page',
        'type' => 'Objet',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [['id' => 11, 'name' => ['fr' => 'Forgeron']]],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::sequence()
            ->push([
                'total' => 2,
                'data' => [[
                    '_id' => 'recipe-before-api-failure',
                    'resultId' => $resultItem->dofusdb_id,
                    'resultName' => $resultItem->name,
                    'resultLevel' => 1,
                    'jobId' => 11,
                    'ingredientIds' => [],
                    'quantities' => [],
                ]],
            ])
            ->pushStatus(500),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result)
        ->deleted->toBe(0)
        ->cleanup_performed->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();

    $this->assertDatabaseHas('recipes', ['id' => $staleRecipe->id]);
});
it('keeps an actionable DofusDB id when a profession is genuinely unknown', function () {
    $resultItem = Item::create([
        'dofusdb_id' => 700020,
        'name' => 'Résultat au métier inconnu',
        'type' => 'Objet',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 0,
            'data' => [],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 1,
            'data' => [[
                '_id' => 'recipe-unknown-job',
                'resultId' => $resultItem->dofusdb_id,
                'resultName' => $resultItem->name,
                'resultLevel' => 1,
                'jobId' => 321,
                'ingredientIds' => [],
                'quantities' => [],
            ]],
        ]),
        'https://api.dofusdb.fr/items*' => Http::response([
            'total' => 1,
            'data' => [['id' => $resultItem->dofusdb_id]],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result['unknown_job_ids'])->toBe([321])
        ->and($result['deleted_items'])->toBe(0)
        ->and($result['item_cleanup_performed'])->toBeTrue()
        ->and($resultItem->fresh()->recipe->profession)->toBe('Métier inconnu (#321)');
});

it('fetches a numeric DofusDB item through the filtered collection endpoint', function () {
    Http::fake([
        'https://api.dofusdb.fr/items*' => Http::response([
            'total' => 0,
            'data' => [],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importItems([597]);

    expect($result)
        ->imported->toBe(0)
        ->updated->toBe(0)
        ->and($result['errors'])->toContain('Item 597: not found in DofusDB');

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://api.dofusdb.fr/items?')
        && (int) $request['id'] === 597
        && (int) $request['$limit'] === 1);
});

it('never deletes items when the DofusDB item inventory fails', function () {
    $existingItem = Item::create([
        'dofusdb_id' => 700030,
        'name' => 'Item protégé pendant une panne',
        'type' => 'Objet',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [['id' => 11, 'name' => ['fr' => 'Forgeron']]],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 1,
            'data' => [[
                '_id' => 'recipe-before-item-api-failure',
                'resultId' => $existingItem->dofusdb_id,
                'resultName' => $existingItem->name,
                'resultLevel' => 1,
                'jobId' => 11,
                'ingredientIds' => [],
                'quantities' => [],
            ]],
        ]),
        'https://api.dofusdb.fr/items*' => Http::response([], 500),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result)
        ->deleted_items->toBe(0)
        ->item_cleanup_performed->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();

    $this->assertDatabaseHas('items', ['id' => $existingItem->id]);
});
it('never cleans stale data when processing one recipe fails', function () {
    $staleItem = Item::create([
        'dofusdb_id' => 700040,
        'name' => 'Recette à protéger après une erreur',
        'type' => 'Objet',
    ]);
    $staleRecipe = Recipe::create([
        'item_id' => $staleItem->id,
        'profession' => 'Forgeron',
    ]);
    $resultItem = Item::create([
        'dofusdb_id' => 700041,
        'name' => 'Résultat invalide',
        'type' => 'Objet',
    ]);

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [['id' => 11, 'name' => ['fr' => 'Forgeron']]],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 1,
            'data' => [[
                '_id' => 'recipe-processing-failure',
                'resultId' => $resultItem->dofusdb_id,
                'resultName' => $resultItem->name,
                'resultLevel' => 1,
                'jobId' => 11,
                'ingredientIds' => [null],
                'quantities' => [1],
            ]],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result)
        ->deleted->toBe(0)
        ->deleted_items->toBe(0)
        ->cleanup_performed->toBeFalse()
        ->item_cleanup_performed->toBeFalse()
        ->and($result['errors'])->not->toBeEmpty();

    $this->assertDatabaseHas('recipes', ['id' => $staleRecipe->id]);
});
it('restores an item and its recipe automatically when they return to DofusDB', function () {
    $item = Item::create([
        'dofusdb_id' => 700050,
        'name' => 'Item revenu dans le jeu',
        'type' => 'Objet',
    ]);
    $recipe = Recipe::create([
        'item_id' => $item->id,
        'profession' => 'Forgeron',
    ]);
    $favoriteOwner = User::factory()->create();
    $favoriteOwner->favoriteItems()->attach($item->id);

    $recipe->delete();
    $item->delete();

    expect($favoriteOwner->favoriteItems()->whereKey($item->id)->exists())->toBeFalse();

    Http::fake([
        'https://api.dofusdb.fr/jobs*' => Http::response([
            'total' => 1,
            'data' => [['id' => 11, 'name' => ['fr' => 'Forgeron']]],
        ]),
        'https://api.dofusdb.fr/recipes*' => Http::response([
            'total' => 1,
            'data' => [[
                '_id' => 'recipe-returned',
                'resultId' => $item->dofusdb_id,
                'resultName' => $item->name,
                'resultLevel' => 1,
                'jobId' => 11,
                'ingredientIds' => [],
                'quantities' => [],
            ]],
        ]),
        'https://api.dofusdb.fr/items*' => Http::response([
            'total' => 1,
            'data' => [['id' => $item->dofusdb_id]],
        ]),
    ]);

    $result = app(DofusDBImportService::class)->importRecipesFirst(PHP_INT_MAX);

    expect($result)
        ->deleted->toBe(0)
        ->deleted_items->toBe(0)
        ->and(Item::find($item->id))->not->toBeNull()
        ->and(Recipe::find($recipe->id))->not->toBeNull()
        ->and($favoriteOwner->favoriteItems()->whereKey($item->id)->exists())->toBeTrue();
});
