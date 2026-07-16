<?php

use App\Models\ApiLog;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PersonalItemPrice;
use App\Models\PriceHistory;
use App\Models\Server;
use App\Models\User;
use App\Models\UserItemPricePreference;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->withoutVite();

    $this->server = Server::create([
        'name' => 'Imagiro Test',
        'slug' => 'imagiro-test',
    ]);

    $this->item = Item::create([
        'dofusdb_id' => 987654,
        'name' => 'Potion de test',
    ]);

    $this->user = User::factory()->create([
        'server_id' => $this->server->id,
    ]);
});

it('stores a personal price without changing the community price or contribution history', function () {
    ItemPrice::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1000,
        'created_by' => $this->user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);

    $this->actingAs($this->user)
        ->post(route('prices.store'), [
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => 750,
            'price_mode' => 'personal',
        ])
        ->assertSessionHasNoErrors();

    expect(ItemPrice::first()->price)->toBe(1000)
        ->and(PersonalItemPrice::first()->price)->toBe(750)
        ->and(PriceHistory::count())->toBe(0)
        ->and($this->user->fresh()->price_mode)->toBe('community')
        ->and($this->user->fresh()->price_contributions_count)->toBe(0);
});

it('tracks every community submission as a retroactive contribution source', function () {
    $this->actingAs($this->user);

    foreach ([1000, 1200] as $price) {
        $this->post(route('prices.store'), [
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => $price,
            'price_mode' => 'community',
        ])->assertSessionHasNoErrors();
    }

    expect(ItemPrice::first()->price)->toBe(1200)
        ->and(PriceHistory::where('created_by', $this->user->id)->count())->toBe(2)
        ->and($this->user->submittedPrices()->count())->toBe(2)
        ->and($this->user->fresh()->price_contributions_count)->toBe(2);
});

it('uses a personal price in calculations and falls back to the community price', function () {
    $communityPrice = ItemPrice::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1000,
        'created_by' => $this->user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);

    $this->user->update(['price_mode' => 'personal']);

    expect($this->item->getPriceForServer($this->server, $this->user)->is($communityPrice))->toBeTrue();

    $personalPrice = PersonalItemPrice::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 750,
    ]);

    expect($this->item->getPriceForServer($this->server, $this->user)->is($personalPrice))->toBeTrue();
});

it('exposes the current contributor and their historical contribution count without their email', function () {
    $this->actingAs($this->user);

    foreach ([900, 1000, 1100] as $price) {
        $this->post(route('prices.store'), [
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => $price,
            'price_mode' => 'community',
        ])->assertSessionHasNoErrors();
    }

    $response = $this->actingAs($this->user)
        ->get(route('items.show', $this->item));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Items/Show')
            ->where('item.prices.0.user.name', $this->user->name)
            ->where('item.prices.0.user.price_contributions_count', 3)
            ->missing('item.prices.0.user.price_reliability_score')
            ->missing('item.prices.0.user.price_reliability_samples')
            ->missing('item.prices.0.confidence_score')
            ->missing('item.prices.0.confidence_details.average_reliability_score')
            ->missing('item.prices.0.confidence_details.latest_plausibility_score')
            ->missing('item.price_histories.0.reliability_snapshot')
            ->missing('item.price_histories.0.evaluation_score')
            ->missing('item.price_histories.0.influence_weight')
            ->missing('auth.user.price_reliability_score')
            ->missing('auth.user.price_reliability_samples')
            ->missing('item.prices.0.user.email')
        );
});

it('persists the selected price mode independently from a price submission', function () {
    $this->actingAs($this->user)
        ->put(route('prices.preference'), ['price_mode' => 'personal'])
        ->assertSessionHasNoErrors();

    expect($this->user->fresh()->price_mode)->toBe('personal');
});

it('lets an item override the global price mode for one server', function () {
    $communityPrice = ItemPrice::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1000,
        'created_by' => $this->user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);

    $personalPrice = PersonalItemPrice::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 750,
    ]);

    expect($this->user->fresh()->price_mode)->toBe('community')
        ->and($this->item->getPriceForServer($this->server, $this->user)->is($communityPrice))->toBeTrue();

    $this->actingAs($this->user)
        ->put(route('prices.item-preference'), [
            'item_id' => $this->item->id,
            'server_id' => $this->server->id,
            'price_mode' => 'personal',
        ])
        ->assertSessionHasNoErrors();

    expect($this->item->fresh()->getPriceForServer($this->server, $this->user)->is($personalPrice))->toBeTrue()
        ->and(UserItemPricePreference::first()->mode)->toBe('personal');

    $this->get(route('items.show', $this->item))
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.price_preferences.0.mode', 'personal')
        );
});

it('can force an item to community mode while the global mode is personal and then follow global again', function () {
    $this->user->update(['price_mode' => 'personal']);

    $communityPrice = ItemPrice::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1000,
        'created_by' => $this->user->id,
        'status' => ItemPrice::STATUS_APPROVED,
    ]);

    $personalPrice = PersonalItemPrice::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 750,
    ]);

    $this->actingAs($this->user)
        ->put(route('prices.item-preference'), [
            'item_id' => $this->item->id,
            'server_id' => $this->server->id,
            'price_mode' => 'community',
        ]);

    expect($this->item->fresh()->getPriceForServer($this->server, $this->user)->is($communityPrice))->toBeTrue();

    $this->put(route('prices.item-preference'), [
        'item_id' => $this->item->id,
        'server_id' => $this->server->id,
        'price_mode' => null,
    ]);

    expect(UserItemPricePreference::count())->toBe(0)
        ->and($this->item->fresh()->getPriceForServer($this->server, $this->user)->is($personalPrice))->toBeTrue();
});

it('keeps item overrides isolated by server', function () {
    $otherServer = Server::create([
        'name' => 'Orukam Test',
        'slug' => 'orukam-test',
    ]);

    PersonalItemPrice::create([
        'user_id' => $this->user->id,
        'server_id' => $otherServer->id,
        'item_id' => $this->item->id,
        'price' => 500,
    ]);

    $this->actingAs($this->user)
        ->put(route('prices.item-preference'), [
            'item_id' => $this->item->id,
            'server_id' => $this->server->id,
            'price_mode' => 'personal',
        ]);

    expect($this->item->fresh()->getPriceModeForServer($this->server, $this->user))->toBe('personal')
        ->and($this->item->fresh()->getPriceModeForServer($otherServer, $this->user))->toBe('community');
});

it('counts API price submissions in the same contribution history', function () {
    Sanctum::actingAs($this->user, ['write']);

    $this->postJson('/api/prices', [
        'server_id' => $this->server->id,
        'prices' => [[
            'item_id' => $this->item->id,
            'price' => 1450,
        ]],
    ])->assertOk();

    expect($this->user->submittedPrices()->count())->toBe(1)
        ->and($this->user->submittedPrices()->first()->price)->toBe(1450)
        ->and($this->user->fresh()->price_contributions_count)->toBe(1);
});

it('deduplicates repeated items in one API import and rejects invalid prices', function () {
    Sanctum::actingAs($this->user, ['write']);

    $this->postJson('/api/prices', [
        'server_id' => $this->server->id,
        'prices' => [
            ['item_id' => $this->item->id, 'price' => 1000],
            ['item_id' => $this->item->id, 'price' => 1250],
        ],
    ])->assertOk()
        ->assertJsonPath('updated_count', 1)
        ->assertJsonPath('updated_prices.0.submitted_price', 1250);

    expect($this->user->submittedPrices()->count())->toBe(1)
        ->and($this->user->fresh()->price_contributions_count)->toBe(1);

    $this->postJson('/api/prices', [
        'server_id' => $this->server->id,
        'prices' => [['item_id' => $this->item->id, 'price' => 0]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('prices.0.price');
});

it('backfills contribution counts from web history and successful legacy API logs', function () {
    Schema::dropIfExists('user_item_price_preferences');
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('price_contributions_count');
    });

    foreach ([1700, 1800] as $price) {
        PriceHistory::create([
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => $price,
            'created_by' => $this->user->id,
        ]);
    }

    ApiLog::create([
        'user_id' => $this->user->id,
        'endpoint' => 'api/prices',
        'method' => 'POST',
        'response_status' => 200,
        'items_affected' => 0,
        'request_data' => [
            'server_id' => $this->server->id,
            'prices' => [
                ['item_id' => $this->item->id, 'price' => 1800],
                ['item_id' => $this->item->id, 'price' => 1900],
                ['item_id' => $this->item->id, 'price' => 2000],
            ],
        ],
    ]);

    ApiLog::create([
        'user_id' => $this->user->id,
        'endpoint' => 'api/prices',
        'method' => 'POST',
        'response_status' => 422,
        'items_affected' => 10,
    ]);

    $migration = require database_path('migrations/2026_07_14_000001_backfill_price_contribution_counts.php');
    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('users', 'price_contributions_count'))->toBeTrue()
        ->and(Schema::hasTable('user_item_price_preferences'))->toBeTrue()
        ->and($this->user->fresh()->price_contributions_count)->toBe(5);
});
