<?php

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Recipe;
use App\Models\Server;
use App\Models\User;

it('returns recipe ingredient images and approved prices in the item detail response', function () {
    $server = Server::create([
        'name' => 'Serveur mobile',
        'slug' => 'serveur-mobile',
    ]);
    $otherServer = Server::create([
        'name' => 'Autre serveur',
        'slug' => 'autre-serveur',
    ]);
    $craftedItem = Item::create([
        'dofusdb_id' => 990001,
        'name' => 'Objet fabriqué',
    ]);
    $ingredient = Item::create([
        'dofusdb_id' => 990002,
        'name' => 'Ingrédient illustré',
        'image_url' => 'https://cdn.example.test/ingredient.png',
    ]);
    $recipe = Recipe::create([
        'item_id' => $craftedItem->id,
        'quantity_produced' => 1,
        'profession' => 'Artisan',
        'profession_level' => 10,
    ]);
    $recipe->ingredients()->attach($ingredient->id, ['quantity' => 3]);
    $contributor = User::factory()->create();

    ItemPrice::create([
        'item_id' => $ingredient->id,
        'server_id' => $server->id,
        'price' => 125,
        'status' => ItemPrice::STATUS_APPROVED,
        'reports_count' => 0,
        'created_by' => $contributor->id,
    ]);
    ItemPrice::create([
        'item_id' => $ingredient->id,
        'server_id' => $otherServer->id,
        'price' => 999,
        'status' => ItemPrice::STATUS_APPROVED,
        'reports_count' => 0,
        'created_by' => $contributor->id,
    ]);

    $this->getJson("/api/items/{$craftedItem->id}?include=recipe,recipe.ingredients&server_id={$server->id}")
        ->assertOk()
        ->assertJsonPath('data.recipe.ingredients.0.item_id', $ingredient->id)
        ->assertJsonPath('data.recipe.ingredients.0.quantity', 3)
        ->assertJsonPath('data.recipe.ingredients.0.image_url', 'https://cdn.example.test/ingredient.png')
        ->assertJsonCount(1, 'data.recipe.ingredients.0.prices')
        ->assertJsonPath('data.recipe.ingredients.0.prices.0.server_id', $server->id)
        ->assertJsonPath('data.recipe.ingredients.0.prices.0.price', 125);
});
