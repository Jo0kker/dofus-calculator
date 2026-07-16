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
        ->and($price->confidence_details['reason_codes'])->toContain('single_contributor');
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
        ->and($price->confidence_score)->toBeLessThanOrEqual(69)
        ->and($price->confidence_details['experienced_contributors'])->toBe(0);
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

    expect($report->fresh()->status)->toBe('reviewed')
        ->and($report->priceHistory->fresh()->rejected_at)->not->toBeNull()
        ->and($contributor->fresh()->price_reliability_score)->toBeLessThan(60)
        ->and($price->fresh()->status)->toBe('rejected');
});

it('does not attach a consensus report to an arbitrary contributor', function () {
    $users = User::factory()->count(3)->create(['created_at' => now()->subYear()]);
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
