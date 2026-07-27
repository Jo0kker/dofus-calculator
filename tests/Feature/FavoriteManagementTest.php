<?php

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\Recipe;
use App\Models\Server;
use App\Models\User;
use App\Models\UserItemPricePreference;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
});

it('shares favorite item ids with the item list and recipe page', function () {
    $ingredient = Item::create([
        'dofusdb_id' => 120020,
        'name' => 'Ingrédient déjà favori',
    ]);
    $craftedItem = Item::create([
        'dofusdb_id' => 120021,
        'name' => 'Objet avec recette',
    ]);
    $recipe = Recipe::create([
        'item_id' => $craftedItem->id,
        'quantity_produced' => 1,
        'profession' => 'Sculpteur',
    ]);
    $recipe->ingredients()->attach($ingredient->id, ['quantity' => 2]);
    $this->user->favoriteItems()->attach($ingredient->id);

    $this->actingAs($this->user)
        ->get(route('items.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Items/Index')
            ->has('favoriteItemIds', 1)
            ->where('favoriteItemIds.0', $ingredient->id));

    $this->get(route('items.index', ['profession' => 'Sculpteur']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.profession', 'Sculpteur')
            ->where('professions.0', 'Sculpteur')
            ->has('items.data', 1)
            ->where('items.data.0.id', $craftedItem->id));

    $this->get(route('items.show', $craftedItem))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Items/Show')
            ->has('favoriteItemIds', 1)
            ->where('favoriteItemIds.0', $ingredient->id));
});
it('shows favorites without requiring a selected server', function () {
    $olderItem = Item::create([
        'dofusdb_id' => 120001,
        'name' => 'Bois ancien',
        'type' => 'Bois',
        'level' => 10,
    ]);
    $recentItem = Item::create([
        'dofusdb_id' => 120002,
        'name' => 'Potion récente',
        'type' => 'Potion',
        'level' => 20,
    ]);

    $this->user->favoriteItems()->attach($olderItem->id, [
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $this->user->favoriteItems()->attach($recentItem->id, [
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Favorites/Index')
            ->has('favorites', 2)
            ->where('favorites.0.item.id', $recentItem->id)
            ->where('favorites.0.best_option', 'unavailable')
            ->where('favorites.1.item.id', $olderItem->id));
});

it('uses the account server and personal price when the session has no server', function () {
    $server = Server::create([
        'name' => 'Serveur favori',
        'slug' => 'serveur-favori',
    ]);
    $contributor = User::factory()->create();
    $this->user->update(['server_id' => $server->id]);
    $item = Item::create([
        'dofusdb_id' => 120005,
        'name' => 'Favori personnel',
    ]);
    $this->user->favoriteItems()->attach($item->id);

    ItemPrice::create([
        'server_id' => $server->id,
        'item_id' => $item->id,
        'price' => 1000,
        'created_by' => $contributor->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);
    PersonalItemPrice::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'item_id' => $item->id,
        'price' => 750,
    ]);
    UserItemPricePreference::create([
        'user_id' => $this->user->id,
        'server_id' => $server->id,
        'item_id' => $item->id,
        'mode' => 'personal',
    ]);

    $this->actingAs($this->user)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('favorites.0.direct_price', 750)
            ->where('favorites.0.best_option', 'buy'));
});

it('removes a favorite explicitly and idempotently', function () {
    $item = Item::create([
        'dofusdb_id' => 120003,
        'name' => 'Minerai à retirer',
    ]);
    $this->user->favoriteItems()->attach($item->id);

    $this->actingAs($this->user)
        ->delete(route('favorites.destroy', $item))
        ->assertRedirect()
        ->assertSessionHas('success', 'Retiré des favoris');

    expect($this->user->favoriteItems()->whereKey($item->id)->exists())->toBeFalse();

    $this->delete(route('favorites.destroy', $item))
        ->assertRedirect()
        ->assertSessionHas('success', 'Retiré des favoris');

    expect($this->user->favoriteItems()->whereKey($item->id)->exists())->toBeFalse();
});

it('lists and removes real account favorites from the desktop app', function () {
    $this->user->update(['interface_mode' => 'desktop']);
    $item = Item::create([
        'dofusdb_id' => 120004,
        'name' => 'Favori du bureau',
        'type' => 'Ressource',
        'category' => 'Bois',
        'level' => 42,
    ]);

    $this->actingAs($this->user)
        ->postJson(route('desktop.api.favorites.store', $item))
        ->assertOk()
        ->assertJsonPath('is_favorite', true);

    $this->postJson(route('desktop.api.favorites.store', $item))
        ->assertOk();

    expect($this->user->favoriteItems()->whereKey($item->id)->count())->toBe(1);

    $this->getJson(route('desktop.api.favorites.index'))
        ->assertOk()
        ->assertJsonPath('favorites.0.id', $item->id)
        ->assertJsonPath('favorites.0.name', 'Favori du bureau')
        ->assertJsonPath('favorites.0.is_craftable', false)
        ->assertJsonPath('types.0', 'Ressource');

    $this->deleteJson(route('desktop.api.favorites.destroy', $item))
        ->assertNoContent();

    expect($this->user->favoriteItems()->whereKey($item->id)->exists())->toBeFalse();
});

it('returns the classic buy and craft analysis to desktop favorites', function () {
    $server = Server::create([
        'name' => 'Serveur analyse bureau',
        'slug' => 'serveur-analyse-bureau',
    ]);
    $this->user->update([
        'interface_mode' => 'desktop',
        'server_id' => $server->id,
    ]);

    $ingredient = Item::create([
        'dofusdb_id' => 120010,
        'name' => 'Ingrédient favori bureau',
    ]);
    $buyItem = Item::create([
        'dofusdb_id' => 120011,
        'name' => 'Favori à acheter',
        'type' => 'Équipement',
        'level' => 50,
    ]);
    $craftItem = Item::create([
        'dofusdb_id' => 120012,
        'name' => 'Favori à crafter',
        'type' => 'Équipement',
        'level' => 100,
    ]);

    foreach ([$buyItem, $craftItem] as $item) {
        $recipe = Recipe::create([
            'item_id' => $item->id,
            'quantity_produced' => 1,
            'profession' => 'Forgeron',
        ]);
        $recipe->ingredients()->attach($ingredient->id, ['quantity' => 1]);
        $this->user->favoriteItems()->attach($item->id);
    }

    foreach ([
        [$ingredient, 1000],
        [$buyItem, 500],
        [$craftItem, 2000],
    ] as [$item, $price]) {
        ItemPrice::create([
            'server_id' => $server->id,
            'item_id' => $item->id,
            'price' => $price,
            'created_by' => $this->user->id,
            'status' => ItemPrice::STATUS_APPROVED,
        ]);
    }

    $this->actingAs($this->user)
        ->getJson(route('desktop.api.favorites.index'))
        ->assertOk()
        ->assertJsonPath('professions.0', 'Forgeron')
        ->assertJsonFragment([
            'profession' => 'Forgeron',
        ])
        ->assertJsonFragment([
            'name' => 'Favori à acheter',
            'direct_price' => 500,
            'craft_cost' => 1000,
            'best_option' => 'buy',
            'savings' => 500,
        ])
        ->assertJsonFragment([
            'name' => 'Favori à crafter',
            'direct_price' => 2000,
            'craft_cost' => 1000,
            'best_option' => 'craft',
            'savings' => 1000,
        ]);
});

it('keeps the desktop favorites api restricted to desktop accounts', function () {
    $this->actingAs($this->user)
        ->getJson(route('desktop.api.favorites.index'))
        ->assertForbidden();
});
