<?php

namespace App\Services;

use App\Models\ItemPrice;
use App\Models\PriceHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class CommunityPriceTrustService
{
    public const VERSION = 1;

    public const WINDOW_DAYS = 30;

    private const RELIABILITY_PRIOR_SCORE = 60;

    private const RELIABILITY_PRIOR_WEIGHT = 5.0;

    private const RELIABILITY_WINDOW_DAYS = 180;

    public function recalculate(int $itemId, int $serverId): ?ItemPrice
    {
        $currentItemPrice = ItemPrice::query()
            ->where('item_id', $itemId)
            ->where('server_id', $serverId)
            ->first();
        $currentReasonCodes = $currentItemPrice?->confidence_details['reason_codes'] ?? [];
        $isModerationLocked = $currentItemPrice?->status === ItemPrice::STATUS_REJECTED
            && ! in_array('no_valid_observation', $currentReasonCodes, true);

        $observations = $this->loadLatestContributorObservations($itemId, $serverId);

        if ($observations->isEmpty()) {
            $currentItemPrice?->update([
                'status' => ItemPrice::STATUS_REJECTED,
                'confidence_score' => 0,
                'confidence_level' => 'low',
                'recent_observations_count' => 0,
                'recent_contributors_count' => 0,
                'confidence_details' => ['reason_codes' => ['no_valid_observation']],
                'confidence_computed_at' => now(),
                'confidence_version' => self::VERSION,
            ]);

            return $currentItemPrice;
        }

        $this->evaluateObservations($observations);
        $this->refreshUsersReliability($observations->pluck('created_by')->unique()->all());

        $observations = $this->loadLatestContributorObservations($itemId, $serverId);
        $activeContributors = $this->activeContributorsCount($serverId);
        $analysis = $this->analyze($observations, $activeContributors);
        $latestObservation = $observations->sortByDesc('created_at')->first();

        foreach ($observations as $observation) {
            $metric = $analysis['observation_metrics'][$observation->id];
            $observation->forceFill([
                'plausibility_score' => $metric['plausibility_score'],
                'consensus_deviation' => $metric['consensus_deviation'],
                'influence_weight' => $metric['influence_weight'],
            ])->save();
        }

        $recentObservationCount = PriceHistory::query()
            ->where('item_id', $itemId)
            ->where('server_id', $serverId)
            ->whereNull('rejected_at')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->count();
        $recentContributorCount = $recentObservationCount > 0 ? $observations->count() : 0;

        return ItemPrice::updateOrCreate(
            [
                'item_id' => $itemId,
                'server_id' => $serverId,
            ],
            [
                'price' => $analysis['price'],
                'created_by' => $latestObservation->created_by,
                'status' => $isModerationLocked ? ItemPrice::STATUS_REJECTED : ItemPrice::STATUS_APPROVED,
                'confidence_score' => $analysis['confidence_score'],
                'confidence_level' => $analysis['confidence_level'],
                'recent_observations_count' => $recentObservationCount,
                'recent_contributors_count' => $recentContributorCount,
                'confidence_details' => $analysis['confidence_details'],
                'confidence_computed_at' => now(),
                'confidence_version' => self::VERSION,
            ],
        );
    }

    public function rejectObservation(PriceHistory $observation): ?ItemPrice
    {
        $observation->forceFill([
            'plausibility_score' => 0,
            'evaluation_score' => 0,
            'evaluation_weight' => 2,
            'evaluated_at' => now(),
            'rejected_at' => now(),
        ])->save();

        $this->refreshUserReliability($observation->created_by);

        return $this->recalculate($observation->item_id, $observation->server_id);
    }

    public function refreshUserReliability(int $userId): void
    {
        $evaluations = PriceHistory::query()
            ->where('created_by', $userId)
            ->where('created_at', '>=', now()->subDays(self::RELIABILITY_WINDOW_DAYS))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'server_id',
                'item_id',
                'evaluation_score',
                'evaluation_weight',
                'evaluated_at',
                'created_at',
            ])
            // Mettre à jour plusieurs fois le même marché ne crée pas
            // artificiellement de nouvelles preuves de fiabilité.
            ->unique(fn (PriceHistory $evaluation) => "{$evaluation->server_id}:{$evaluation->item_id}")
            ->filter(fn (PriceHistory $evaluation) => $evaluation->evaluated_at !== null
                && $evaluation->evaluation_score !== null)
            ->values();

        $weightedScore = self::RELIABILITY_PRIOR_SCORE * self::RELIABILITY_PRIOR_WEIGHT;
        $totalWeight = self::RELIABILITY_PRIOR_WEIGHT;

        foreach ($evaluations as $evaluation) {
            $ageDays = $this->ageInDays($evaluation->created_at);
            $recency = max(0.25, exp(-$ageDays / self::RELIABILITY_WINDOW_DAYS));
            $weight = max(0.05, (float) $evaluation->evaluation_weight) * $recency;
            $weightedScore += $evaluation->evaluation_score * $weight;
            $totalWeight += $weight;
        }

        User::query()->whereKey($userId)->update([
            'price_reliability_score' => (int) round($weightedScore / $totalWeight),
            'price_reliability_samples' => $evaluations->count(),
            'price_reliability_updated_at' => now(),
        ]);
    }

    private function refreshUsersReliability(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->refreshUserReliability((int) $userId);
        }
    }

    private function evaluateObservations(Collection $observations): void
    {
        if ($observations->count() < 3) {
            return;
        }

        foreach ($observations as $observation) {
            $others = $observations->reject(fn (PriceHistory $candidate) => $candidate->id === $observation->id)->values();
            $reference = $this->analyze($others, $others->count());

            if ($reference['agreement_score'] < 60) {
                $observation->forceFill([
                    'evaluation_score' => null,
                    'evaluation_weight' => 0,
                    'evaluated_at' => null,
                ])->save();

                continue;
            }

            $deviation = abs(log(max(1, $observation->price) / max(1, $reference['price'])));
            $score = (int) round(100 * exp(-$deviation / 0.5));
            $agreementWeight = max(0.1, $reference['agreement_score'] / 100);
            $evidenceWeight = min(1, $others->count() / 3) * $agreementWeight;

            $observation->forceFill([
                'evaluation_score' => max(0, min(100, $score)),
                'evaluation_weight' => round($evidenceWeight, 4),
                'evaluated_at' => now(),
            ])->save();
        }
    }

    private function analyze(Collection $observations, int $activeContributors): array
    {
        $reference = $this->geometricMedian($observations->pluck('price')->map(fn ($price) => (float) $price)->all());
        $metrics = [];

        foreach ($observations as $observation) {
            $deviation = abs(log(max(1, $observation->price) / max(1, $reference)));
            $plausibility = max(5, (int) round(100 * exp(-$deviation / 0.45)));
            $maturity = $this->contributorMaturity($observation->user);
            $reliability = 0.25 + (0.75 * $this->reliabilityScore($observation->user) / 100);
            $recency = max(0.25, exp(-$this->ageInDays($observation->created_at) / 14));
            $weight = max(0.02, $maturity * $reliability * $recency * ($plausibility / 100));

            $metrics[$observation->id] = [
                'plausibility_score' => $plausibility,
                'consensus_deviation' => round($deviation, 6),
                'influence_weight' => round($weight, 6),
                'maturity' => $maturity,
                'reliability_score' => $this->reliabilityScore($observation->user),
            ];
        }

        $price = (int) round($this->weightedMedian(
            $observations->map(fn (PriceHistory $observation) => (float) $observation->price)->all(),
            $observations->map(fn (PriceHistory $observation) => $metrics[$observation->id]['influence_weight'])->all(),
        ));

        $totalWeight = max(0.000001, array_sum(array_column($metrics, 'influence_weight')));
        $dispersion = 0.0;
        $rawDispersion = 0.0;
        $weightedReliability = 0.0;
        $weightedFreshness = 0.0;
        $effectiveContributors = 0.0;
        $experiencedContributors = 0;

        foreach ($observations as $observation) {
            $metric = &$metrics[$observation->id];
            $metric['consensus_deviation'] = round(abs(log(max(1, $observation->price) / max(1, $price))), 6);
            $metric['plausibility_score'] = max(5, (int) round(100 * exp(-$metric['consensus_deviation'] / 0.45)));
            $dispersion += $metric['consensus_deviation'] * $metric['influence_weight'];
            $rawDispersion += $metric['consensus_deviation'];
            $weightedReliability += $metric['reliability_score'] * $metric['influence_weight'];
            $weightedFreshness += (100 * exp(-$this->ageInDays($observation->created_at) / 30))
                * $metric['influence_weight'];
            $effectiveContributors += $metric['maturity']
                * (0.25 + (0.75 * $metric['reliability_score'] / 100));
            if (($observation->user?->price_reliability_samples ?? 0) >= 3
                && $this->ageSince($observation->user?->created_at) >= 30) {
                $experiencedContributors++;
            }
        }
        unset($metric);

        $dispersion /= $totalWeight;
        $rawDispersion /= max(1, $observations->count());
        $agreementScore = 100 * exp(-$dispersion / 0.35);
        $averageReliability = $weightedReliability / $totalWeight;
        $targetContributors = max(3, min(8, 3 + (int) floor(log10(max(1, $activeContributors)) * 2)));
        $evidenceScore = min(100, 100 * $effectiveContributors / $targetContributors);
        $latestObservation = $observations->sortByDesc('created_at')->first();
        $freshnessScore = $weightedFreshness / $totalWeight;
        $confidenceScore = (int) round(
            (0.45 * $evidenceScore)
            + (0.30 * $agreementScore)
            + (0.15 * $averageReliability)
            + (0.10 * $freshnessScore)
        );

        if ($observations->count() === 1) {
            $confidenceScore = min(39, $confidenceScore);
        } elseif ($observations->count() === 2) {
            $confidenceScore = min(64, $confidenceScore);
        }
        if ($experiencedContributors < 2) {
            $confidenceScore = min(69, $confidenceScore);
        }

        $confidenceScore = max(0, min(100, $confidenceScore));
        $reasonCodes = $this->reasonCodes(
            $observations->count(),
            $experiencedContributors,
            $rawDispersion,
            $freshnessScore,
        );

        return [
            'price' => max(1, $price),
            'confidence_score' => $confidenceScore,
            'confidence_level' => $confidenceScore >= 75 ? 'high' : ($confidenceScore >= 45 ? 'medium' : 'low'),
            'agreement_score' => (int) round($agreementScore),
            'observation_metrics' => $metrics,
            'confidence_details' => [
                'latest_observation_at' => $latestObservation->created_at?->toISOString(),
                'window_days' => self::WINDOW_DAYS,
                'reason_codes' => $reasonCodes,
            ],
        ];
    }

    private function loadLatestContributorObservations(int $itemId, int $serverId): Collection
    {
        $query = PriceHistory::query()
            ->with('user')
            ->where('item_id', $itemId)
            ->where('server_id', $serverId)
            ->whereNull('rejected_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $cutoff = now()->subDays(self::WINDOW_DAYS);
        $observations = (clone $query)
            ->where('created_at', '>=', $cutoff)
            ->whereNotExists(function ($newer) use ($itemId, $serverId, $cutoff) {
                $newer->selectRaw('1')
                    ->from('price_histories as newer')
                    ->where('newer.item_id', $itemId)
                    ->where('newer.server_id', $serverId)
                    ->whereNull('newer.rejected_at')
                    ->where('newer.created_at', '>=', $cutoff)
                    ->whereColumn('newer.created_by', 'price_histories.created_by')
                    ->where(function ($moreRecent) {
                        $moreRecent->whereColumn('newer.created_at', '>', 'price_histories.created_at')
                            ->orWhere(function ($sameTime) {
                                $sameTime->whereColumn('newer.created_at', '=', 'price_histories.created_at')
                                    ->whereColumn('newer.id', '>', 'price_histories.id');
                            });
                    });
            })
            ->get();

        if ($observations->isNotEmpty()) {
            return $observations;
        }

        return $query->limit(1)->get();
    }

    private function activeContributorsCount(int $serverId): int
    {
        return PriceHistory::query()
            ->where('server_id', $serverId)
            ->whereNull('rejected_at')
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->distinct()
            ->count('created_by');
    }

    private function contributorMaturity(?User $user): float
    {
        if (! $user) {
            return 0.1;
        }

        $experience = 0.35 + (0.65 * min(1, $user->price_reliability_samples / 10));
        $accountAge = 0.35 + (0.65 * min(1, $this->ageSince($user->created_at) / 30));

        return min($experience, $accountAge);
    }

    private function reliabilityScore(?User $user): int
    {
        return (int) ($user?->price_reliability_score ?? self::RELIABILITY_PRIOR_SCORE);
    }

    private function reasonCodes(int $contributors, int $experienced, float $dispersion, float $freshnessScore): array
    {
        $codes = [];

        if ($contributors === 1) {
            $codes[] = 'single_contributor';
        } elseif ($contributors < 3) {
            $codes[] = 'few_independent_contributors';
        }
        if ($experienced < 2) {
            $codes[] = 'learning_contributors';
        }
        if ((exp($dispersion) - 1) > 0.25) {
            $codes[] = 'high_dispersion';
        }
        if ($freshnessScore < 60) {
            $codes[] = 'stale_observations';
        }
        if ($codes === []) {
            $codes[] = 'stable_consensus';
        }

        return $codes;
    }

    private function geometricMedian(array $values): float
    {
        $logs = array_map(fn (float $value) => log(max(1, $value)), $values);
        sort($logs);
        $count = count($logs);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($logs[$middle - 1] + $logs[$middle]) / 2
            : $logs[$middle];

        return exp($median);
    }

    private function weightedMedian(array $values, array $weights): float
    {
        $pairs = [];
        foreach ($values as $index => $value) {
            $pairs[] = ['value' => $value, 'weight' => $weights[$index] ?? 0.0];
        }
        usort($pairs, fn (array $left, array $right) => $left['value'] <=> $right['value']);

        $halfWeight = array_sum(array_column($pairs, 'weight')) / 2;
        $cumulative = 0.0;
        foreach ($pairs as $pair) {
            $cumulative += $pair['weight'];
            if ($cumulative >= $halfWeight) {
                return $pair['value'];
            }
        }

        return $pairs[array_key_last($pairs)]['value'];
    }

    private function ageInDays($date): float
    {
        if (! $date) {
            return self::WINDOW_DAYS;
        }

        return max(0, $date->diffInSeconds(now()) / 86400);
    }

    private function ageSince($date): float
    {
        if (! $date) {
            return 0;
        }

        return max(0, $date->diffInSeconds(now()) / 86400);
    }
}
