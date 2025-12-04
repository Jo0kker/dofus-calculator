<?php

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a user for the item prices (required by foreign key)
    $this->user = User::factory()->create();

    // Create a server
    $this->server = Server::create([
        'name' => 'Test Server',
        'slug' => 'test-server',
        'type' => 'classic',
        'is_active' => true,
    ]);

    // Create another server for testing server filtering
    $this->server2 = Server::create([
        'name' => 'Test Server 2',
        'slug' => 'test-server-2',
        'type' => 'classic',
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('requires server_id parameter', function () {
    $response = $this->getJson('/api/items/by-price-update');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['server_id']);
});

it('requires valid server_id', function () {
    $response = $this->getJson('/api/items/by-price-update?server_id=9999');

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['server_id']);
});

it('returns items ordered by price update time descending by default', function () {
    // Create items
    $item1 = Item::create([
        'name' => 'Item 1',
        'dofusdb_id' => 1,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 1,
    ]);

    $item2 = Item::create([
        'name' => 'Item 2',
        'dofusdb_id' => 2,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 2,
    ]);

    // Create prices with different update times using Carbon time travel
    Carbon::setTestNow('2024-01-01 10:00:00');
    ItemPrice::create([
        'item_id' => $item1->id,
        'server_id' => $this->server->id,
        'price' => 100,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    Carbon::setTestNow('2024-01-01 11:00:00');
    ItemPrice::create([
        'item_id' => $item2->id,
        'server_id' => $this->server->id,
        'price' => 200,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    Carbon::setTestNow();

    $response = $this->getJson('/api/items/by-price-update?server_id='.$this->server->id);

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Item 2')  // Most recently updated first
        ->assertJsonPath('data.1.name', 'Item 1')
        ->assertJsonPath('meta.server_id', $this->server->id)
        ->assertJsonPath('meta.order', 'desc');
});

it('returns items ordered by price update time ascending when specified', function () {
    // Create items
    $item1 = Item::create([
        'name' => 'Item 1',
        'dofusdb_id' => 1,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 1,
    ]);

    $item2 = Item::create([
        'name' => 'Item 2',
        'dofusdb_id' => 2,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 2,
    ]);

    // Create prices with different update times using Carbon time travel
    Carbon::setTestNow('2024-01-01 10:00:00');
    ItemPrice::create([
        'item_id' => $item1->id,
        'server_id' => $this->server->id,
        'price' => 100,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    Carbon::setTestNow('2024-01-01 11:00:00');
    ItemPrice::create([
        'item_id' => $item2->id,
        'server_id' => $this->server->id,
        'price' => 200,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    Carbon::setTestNow();

    $response = $this->getJson('/api/items/by-price-update?server_id='.$this->server->id.'&order=asc');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.name', 'Item 1')  // Oldest updates first
        ->assertJsonPath('data.1.name', 'Item 2')
        ->assertJsonPath('meta.order', 'asc');
});

it('only returns items with approved prices for the specified server', function () {
    // Create items
    $item1 = Item::create([
        'name' => 'Approved Item',
        'dofusdb_id' => 1,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 1,
    ]);

    $item2 = Item::create([
        'name' => 'Pending Item',
        'dofusdb_id' => 2,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 2,
    ]);

    $item3 = Item::create([
        'name' => 'Other Server Item',
        'dofusdb_id' => 3,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 3,
    ]);

    // Create approved price for server 1
    ItemPrice::create([
        'item_id' => $item1->id,
        'server_id' => $this->server->id,
        'price' => 100,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    // Create pending price for server 1
    ItemPrice::create([
        'item_id' => $item2->id,
        'server_id' => $this->server->id,
        'price' => 200,
        'status' => 'pending_review',
        'created_by' => $this->user->id,
    ]);

    // Create approved price for server 2 (should not be returned)
    ItemPrice::create([
        'item_id' => $item3->id,
        'server_id' => $this->server2->id,
        'price' => 300,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/items/by-price-update?server_id='.$this->server->id);

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Approved Item');
});

it('includes price_updated_at in response', function () {
    $item = Item::create([
        'name' => 'Test Item',
        'dofusdb_id' => 1,
        'type' => 'resource',
        'category' => 'materials',
        'level' => 1,
    ]);

    ItemPrice::create([
        'item_id' => $item->id,
        'server_id' => $this->server->id,
        'price' => 100,
        'status' => 'approved',
        'created_by' => $this->user->id,
    ]);

    $response = $this->getJson('/api/items/by-price-update?server_id='.$this->server->id);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'dofusdb_id',
                    'name',
                    'type',
                    'category',
                    'level',
                    'image_url',
                    'created_at',
                    'updated_at',
                    'price_updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'server_id',
                'order',
                'includes',
            ],
            'links',
        ]);
});

it('respects per_page parameter', function () {
    // Create 5 items with prices
    for ($i = 1; $i <= 5; $i++) {
        $item = Item::create([
            'name' => "Item $i",
            'dofusdb_id' => $i,
            'type' => 'resource',
            'category' => 'materials',
            'level' => $i,
        ]);

        ItemPrice::create([
            'item_id' => $item->id,
            'server_id' => $this->server->id,
            'price' => $i * 100,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);
    }

    $response = $this->getJson('/api/items/by-price-update?server_id='.$this->server->id.'&per_page=2');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 5);
});
