<?php

use App\Models\Item;
use App\Models\PriceHistory;
use App\Models\PriceReport;
use App\Models\Server;
use App\Models\User;
use App\Services\CommunityPriceTrustService;
use App\Services\PriceSubmissionService;

beforeEach(function () {
    $this->server = Server::create([
        'name' => 'Serveur confiance',
        'slug' => 'serveur-confiance',
    ]);
    $this->item = Item::create([
        'dofusdb_id' => 880001,
        'name' => 'Ressource de confiance',
    ]);
    $this->submissionService = app(PriceSubmissionService::class);
    $this->trustService = app(CommunityPriceTrustService::class);
});

it('publishes the first community observation immediately with low confidence', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);

    $price = $this->submissionService->submitCommunityPrice($user, $this->item->id, $this->server->id, 1200);

    expect($price->price)->toBe(1200)
        ->and($price->confidence_level)->toBe('low')
        ->and($price->confidence_score)->toBeLessThanOrEqual(39)
        ->and($price->recent_contributors_count)->toBe(1)
        ->and($price->recent_observations_count)->toBe(1)
        ->and($price->confidence_details['reason_codes'])->toContain('single_contributor')
        ->and($price->confidence_details)->not->toHaveKeys([
            'average_reliability_score',
            'latest_plausibility_score',
            'effective_contributors',
            'experienced_contributors',
        ])
        ->and(PriceHistory::firstOrFail()->toArray())->not->toHaveKeys([
            'reliability_snapshot',
            'plausibility_score',
            'influence_weight',
            'evaluation_score',
        ]);
});

it('keeps internal trust metrics out of the public price api', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    $this->submissionService->submitCommunityPrice($user, $this->item->id, $this->server->id, 1200);

    $this->getJson("/api/items/{$this->item->id}?include=prices&server_id={$this->server->id}")
        ->assertOk()
        ->assertJsonPath('data.prices.0.confidence_level', 'low')
        ->assertJsonMissingPath('data.prices.0.confidence_score')
        ->assertJsonMissingPath('data.prices.0.confidence_details.average_reliability_score')
        ->assertJsonMissingPath('data.prices.0.confidence_details.latest_plausibility_score')
        ->assertJsonMissingPath('data.prices.0.confidence_details.effective_contributors');
});

it('counts repeated observations but gives one contributor only one influence', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);

    $this->submissionService->submitCommunityPrice($user, $this->item->id, $this->server->id, 1000);
    $price = $this->submissionService->submitCommunityPrice($user, $this->item->id, $this->server->id, 1250);

    expect($price->price)->toBe(1250)
        ->and($price->recent_observations_count)->toBe(2)
        ->and($price->recent_contributors_count)->toBe(1)
        ->and(PriceHistory::count())->toBe(2);
});

it('selects the latest observation per contributor before handling a noisy history', function () {
    $honestUsers = User::factory()->count(3)->create(['created_at' => now()->subYear()]);
    $attacker = User::factory()->create(['created_at' => now()->subYear()]);

    foreach ($honestUsers as $index => $user) {
        PriceHistory::create([
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => 1000 + $index,
            'created_by' => $user->id,
        ]);
    }

    $rows = [];
    foreach (range(1, 501) as $index) {
        $rows[] = [
            'server_id' => $this->server->id,
            'item_id' => $this->item->id,
            'price' => 100000 + $index,
            'created_by' => $attacker->id,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    PriceHistory::insert($rows);

    $price = $this->trustService->recalculate($this->item->id, $this->server->id);

    expect($price->recent_contributors_count)->toBe(4)
        ->and($price->price)->toBeLessThan(2000);
});

it('keeps an implausible outlier from dominating the community consensus', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);

    $this->submissionService->submitCommunityPrice($users[0], $this->item->id, $this->server->id, 1000);
    $this->submissionService->submitCommunityPrice($users[1], $this->item->id, $this->server->id, 1020);
    $price = $this->submissionService->submitCommunityPrice($users[2], $this->item->id, $this->server->id, 100000);

    $outlier = PriceHistory::query()->where('created_by', $users[2]->id)->first();

    expect($price->price)->toBeLessThan(2000)
        ->and($price->recent_contributors_count)->toBe(3)
        ->and($outlier->plausibility_score)->toBeLessThanOrEqual(5)
        ->and($price->confidence_details['reason_codes'])->toContain('high_dispersion');
});

it('learns contributor reliability only from independently comparable observations', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);

    foreach (range(1, 3) as $index) {
        $item = Item::create([
            'dofusdb_id' => 880010 + $index,
            'name' => "Ressource d’apprentissage {$index}",
        ]);

        $this->submissionService->submitCommunityPrice($users[0], $item->id, $this->server->id, 1000 + $index);
        $this->submissionService->submitCommunityPrice($users[1], $item->id, $this->server->id, 1010 + $index);
        $this->submissionService->submitCommunityPrice($users[2], $item->id, $this->server->id, 990 + $index);
    }

    $learnedUser = $users[0]->fresh();

    expect($learnedUser->price_reliability_samples)->toBe(3)
        ->and($learnedUser->price_reliability_score)->toBeGreaterThan(60);
});

it('counts distinct item markets rather than repeated updates as reliability samples', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);

    $this->submissionService->submitCommunityPrice($users[0], $this->item->id, $this->server->id, 1000);
    $this->submissionService->submitCommunityPrice($users[1], $this->item->id, $this->server->id, 1010);
    $this->submissionService->submitCommunityPrice($users[2], $this->item->id, $this->server->id, 990);

    $this->submissionService->submitCommunityPrice($users[0], $this->item->id, $this->server->id, 1005);
    $this->submissionService->submitCommunityPrice($users[0], $this->item->id, $this->server->id, 1002);

    expect($users[0]->fresh()->price_reliability_samples)->toBe(1)
        ->and(PriceHistory::query()->where('created_by', $users[0]->id)->count())->toBe(3);
});

it('prevents only new unevaluated accounts from creating high confidence', function () {
    $users = User::factory()->count(10)->create(['created_at' => now()]);
    $price = null;

    foreach ($users as $index => $user) {
        $price = $this->submissionService->submitCommunityPrice(
            $user,
            $this->item->id,
            $this->server->id,
            1000 + ($index % 2),
        );
    }

    expect($price->confidence_level)->not->toBe('high')
        ->and($price->confidence_score)->toBeLessThanOrEqual(69);
});

it('does not score a contributor against an ambiguous independent reference', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);

    $this->submissionService->submitCommunityPrice($users[0], $this->item->id, $this->server->id, 100);
    $this->submissionService->submitCommunityPrice($users[1], $this->item->id, $this->server->id, 10000);
    $this->submissionService->submitCommunityPrice($users[2], $this->item->id, $this->server->id, 10000);

    expect($users[0]->fresh()->price_reliability_samples)->toBe(1)
        ->and($users[0]->fresh()->price_reliability_score)->toBeLessThan(60)
        ->and($users[1]->fresh()->price_reliability_samples)->toBe(0)
        ->and($users[2]->fresh()->price_reliability_samples)->toBe(0);
});

it('removes a rejected observation from the consensus and penalizes its contributor', function () {
    $firstUser = User::factory()->create(['created_at' => now()->subYear()]);
    $secondUser = User::factory()->create(['created_at' => now()->subYear()]);

    $this->submissionService->submitCommunityPrice($firstUser, $this->item->id, $this->server->id, 1000);
    $this->submissionService->submitCommunityPrice($secondUser, $this->item->id, $this->server->id, 5000);

    $rejectedObservation = PriceHistory::query()->where('created_by', $firstUser->id)->first();
    $price = $this->trustService->rejectObservation($rejectedObservation);

    expect($price->price)->toBe(5000)
        ->and($price->recent_contributors_count)->toBe(1)
        ->and($rejectedObservation->fresh()->rejected_at)->not->toBeNull()
        ->and($firstUser->fresh()->price_reliability_score)->toBeLessThan(60);
});

it('uses a validated moderation report as a reliability signal', function () {
    $contributor = User::factory()->create(['created_at' => now()->subYear()]);
    $moderator = User::factory()->create(['role' => 'admin']);
    $price = $this->submissionService->submitCommunityPrice(
        $contributor,
        $this->item->id,
        $this->server->id,
        2500,
    );

    $this->actingAs($moderator)
        ->post(route('prices.report', $price), ['comment' => 'Relevé manifestement erroné'])
        ->assertSessionHasNoErrors();

    $report = PriceReport::firstOrFail();

    $this->post(route('moderation.reports.approve', $report), ['action' => 'reject_price'])
        ->assertSessionHasNoErrors();
    $this->post(route('moderation.reports.approve', $report), ['action' => 'reject_price'])
        ->assertSessionHasNoErrors();

    expect($report->fresh()->status)->toBe('reviewed')
        ->and($report->priceHistory->fresh()->rejected_at)->not->toBeNull()
        ->and($contributor->fresh()->price_reliability_score)->toBeLessThan(60)
        ->and($contributor->fresh()->rejected_prices_count)->toBe(1)
        ->and($price->fresh()->status)->toBe('rejected');
});

it('keeps a rejected multi-contributor consensus locked during recalculation', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);
    $moderator = User::factory()->create(['role' => 'admin']);
    $price = null;

    foreach ($users as $user) {
        $price = $this->submissionService->submitCommunityPrice(
            $user,
            $this->item->id,
            $this->server->id,
            1400,
        );
    }

    $this->actingAs($users[0])
        ->post(route('prices.report', $price), ['comment' => 'Le consensus semble incorrect'])
        ->assertSessionHasNoErrors();

    expect(PriceReport::firstOrFail()->price_history_id)->toBeNull();

    $report = PriceReport::firstOrFail();
    $this->actingAs($moderator)
        ->post(route('moderation.reports.approve', $report), ['action' => 'reject_price'])
        ->assertSessionHasNoErrors();
    $this->artisan('prices:recalculate-confidence')->assertSuccessful();

    expect($price->fresh()->status)->toBe('rejected');
});

it('puts a price under review from three independent pending reports', function () {
    $contributor = User::factory()->create(['created_at' => now()->subYear()]);
    $reporters = User::factory()->count(3)->create();
    $price = $this->submissionService->submitCommunityPrice(
        $contributor,
        $this->item->id,
        $this->server->id,
        1700,
    );

    foreach ($reporters as $reporter) {
        $this->actingAs($reporter)
            ->post(route('prices.report', $price), ['comment' => 'Prix à vérifier'])
            ->assertSessionHasNoErrors();
    }

    expect($price->fresh()->reports_count)->toBe(3)
        ->and($price->fresh()->status)->toBe('pending_review');
});

it('keeps the real observation date and zero recent evidence for an old fallback', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    $observedAt = now()->subDays(60)->startOfSecond();
    $history = PriceHistory::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1600,
        'created_by' => $user->id,
    ]);
    $history->forceFill(['created_at' => $observedAt, 'updated_at' => $observedAt])->save();

    $price = $this->trustService->recalculate($this->item->id, $this->server->id);

    expect($price->recent_observations_count)->toBe(0)
        ->and($price->recent_contributors_count)->toBe(0)
        ->and($price->confidence_details['latest_observation_at'])->toBe($observedAt->toISOString())
        ->and($price->confidence_details['reason_codes'])->toContain('stale_observations');
});

it('recalculates confidence retroactively from existing price history', function () {
    $user = User::factory()->create(['created_at' => now()->subYear()]);
    PriceHistory::create([
        'server_id' => $this->server->id,
        'item_id' => $this->item->id,
        'price' => 1800,
        'created_by' => $user->id,
    ]);

    $this->artisan('prices:recalculate-confidence')
        ->expectsOutput('1 prix communautaire(s) recalculé(s).')
        ->assertSuccessful();

    $price = $this->item->prices()->firstOrFail();

    expect($price->price)->toBe(1800)
        ->and($price->confidence_level)->toBe('low')
        ->and($price->confidence_computed_at)->not->toBeNull();
});
