<?php

use App\Models\Item;
use Illuminate\Support\Facades\DB;

it('searches items by name without being sensitive to case', function () {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA case_sensitive_like = ON');
    }

    $matchingItem = Item::create([
        'dofusdb_id' => 991001,
        'name' => 'Bois de Frêne',
    ]);
    Item::create([
        'dofusdb_id' => 991002,
        'name' => 'Minerai de Fer',
    ]);

    $this->getJson('/api/items?search=bois')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingItem->id);
});
