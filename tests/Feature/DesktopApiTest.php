<?php

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\Recipe;
use App\Models\Server;
use App\Models\User;
use App\Models\UserItemPricePreference;

it('searches desktop items without changing the classic items page', function () {
    $this->actingAs(User::factory()->create(['interface_mode' => 'desktop']));

    $amulet = Item::create([
        'dofusdb_id' => 900001,
        'name' => 'Amulette du Bouftou',
        'type' => 'Amulette',
        'category' => 'Équipement',
        'level' => 20,
        'image_url' => 'https://example.com/amulette.png',
    ]);
    Recipe::create([
        'item_id' => $amulet->id,
        'quantity_produced' => 1,
        'profession' => 'Bijoutier',
        'profession_level' => 20,
    ]);

    Item::create([
        'dofusdb_id' => 900002,
        'name' => 'Épée du Tofu',
        'type' => 'Épée',
        'category' => 'Arme',
        'level' => 12,
    ]);

    $response = $this->getJson('/desktop/api/items?search=bouf&limit=8');

    $response
        ->assertOk()
        ->assertJsonPath('items.0.name', 'Amulette du Bouftou')
        ->assertJsonPath('items.0.is_craftable', true)
        ->assertJsonPath('items.0.recipe.profession', 'Bijoutier')
        ->assertJsonCount(1, 'items')
        ->assertJsonStructure([
            'items' => [[
                'id',
                'name',
                'type',
                'category',
                'level',
                'image_url',
                'is_craftable',
                'recipe',
            ]],
            'types',
        ]);
});

it('rejects desktop item api when the user is not in desktop mode', function () {
    $this->actingAs(User::factory()->create(['interface_mode' => 'classic']));

    $this->getJson('/desktop/api/items')->assertForbidden();
});

it('returns desktop api tokens only in desktop mode', function () {
    $desktopUser = User::factory()->create(['interface_mode' => 'desktop']);
    $desktopUser->createToken('Mobile app', ['read']);

    $this->actingAs($desktopUser)
        ->getJson('/desktop/api/api-tokens')
        ->assertOk()
        ->assertJsonPath('tokens.0.name', 'Mobile app')
        ->assertJsonPath('tokens.0.abilities.0', 'read');

    $this->actingAs(User::factory()->create(['interface_mode' => 'classic']))
        ->getJson('/desktop/api/api-tokens')
        ->assertForbidden();
});

it('returns a desktop item inspector payload', function () {
    $server = Server::create([
        'name' => 'Desktop Test',
        'slug' => 'desktop-test',
    ]);
    $user = User::factory()->create([
        'interface_mode' => 'desktop',
        'server_id' => $server->id,
        'price_contributions_count' => 12,
    ]);
    $this->actingAs($user);

    $item = Item::create([
        'dofusdb_id' => 900003,
        'name' => 'Cape du Prespic',
        'type' => 'Cape',
        'category' => 'Équipement',
        'level' => 36,
        'metadata' => ['description' => 'Une cape parfaite pour tester le desktop.'],
    ]);

    $ingredient = Item::create([
        'dofusdb_id' => 900004,
        'name' => 'Poil de Prespic',
        'type' => 'Ressource',
        'category' => 'Ressource',
        'level' => 30,
    ]);

    $recipe = Recipe::create([
        'item_id' => $item->id,
        'quantity_produced' => 1,
        'profession' => 'Tailleur',
        'profession_level' => 40,
    ]);
    $recipe->ingredients()->attach($ingredient->id, ['quantity' => 10]);

    ItemPrice::create([
        'server_id' => $server->id,
        'item_id' => $item->id,
        'price' => 1200,
        'created_by' => $user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);
    ItemPrice::create([
        'server_id' => $server->id,
        'item_id' => $ingredient->id,
        'price' => 125,
        'created_by' => $user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);
    PersonalItemPrice::create([
        'user_id' => $user->id,
        'server_id' => $server->id,
        'item_id' => $item->id,
        'price' => 950,
    ]);
    UserItemPricePreference::create([
        'user_id' => $user->id,
        'server_id' => $server->id,
        'item_id' => $item->id,
        'mode' => 'personal',
    ]);
    $user->favoriteItems()->attach($item->id);

    $response = $this->getJson("/desktop/api/items/{$item->id}");

    $response
        ->assertOk()
        ->assertJsonPath('item.name', 'Cape du Prespic')
        ->assertJsonPath('item.recipe.profession', 'Tailleur')
        ->assertJsonPath('item.recipe.ingredients.0.name', 'Poil de Prespic')
        ->assertJsonPath('item.recipe.ingredients.0.quantity', 10)
        ->assertJsonPath('item.recipe.ingredients.0.pivot.quantity', 10)
        ->assertJsonPath('item.recipe.ingredients.0.prices.0.user.name', $user->name)
        ->assertJsonPath('item.recipe.ingredients.0.prices.0.user.price_contributions_count', 12)
        ->assertJsonPath('item.prices.0.user.name', $user->name)
        ->assertJsonPath('item.prices.0.user.price_contributions_count', 12)
        ->assertJsonMissingPath('item.prices.0.user.price_reliability_score')
        ->assertJsonMissingPath('item.prices.0.user.price_reliability_samples')
        ->assertJsonMissingPath('item.prices.0.confidence_score')
        ->assertJsonMissingPath('item.prices.0.confidence_level')
        ->assertJsonMissingPath('item.prices.0.recent_observations_count')
        ->assertJsonMissingPath('item.prices.0.recent_contributors_count')
        ->assertJsonMissingPath('item.prices.0.confidence_details')
        ->assertJsonMissingPath('item.prices.0.confidence_computed_at')
        ->assertJsonMissingPath('item.prices.0.confidence_version')
        ->assertJsonPath('item.personal_prices.0.price', 950)
        ->assertJsonPath('item.price_preferences.0.mode', 'personal')
        ->assertJsonPath('item.is_favorite', true)
        ->assertJsonStructure([
            'item' => [
                'prices',
                'personal_prices',
                'price_preferences',
                'recipe' => [
                    'ingredients' => [[
                        'prices',
                        'pivot' => ['quantity'],
                    ]],
                ],
            ],
        ])
        ->assertJsonPath('item.used_in_recipes', []);
});
