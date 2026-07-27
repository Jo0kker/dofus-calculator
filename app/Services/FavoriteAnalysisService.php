<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Collection;

class FavoriteAnalysisService
{
    public function forUser(User $user, ?Server $server, bool $includeCraftTree = true): Collection
    {
        $relations = ['recipe'];

        if ($server) {
            $serverId = $server->id;
            $personalPricesForUser = fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('server_id', $serverId);
            $preferencesForUser = fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('server_id', $serverId);
            $approvedPricesForServer = fn ($query) => $query
                ->where('server_id', $serverId)
                ->where('status', 'approved')
                ->orderBy('updated_at', 'desc');

            $relations = [
                'recipe.ingredients.recipe.ingredients.prices' => $approvedPricesForServer,
                'recipe.ingredients.prices' => $approvedPricesForServer,
                'prices' => $approvedPricesForServer,
                'personalPrices' => $personalPricesForUser,
                'pricePreferences' => $preferencesForUser,
                'recipe.ingredients.personalPrices' => $personalPricesForUser,
                'recipe.ingredients.pricePreferences' => $preferencesForUser,
                'recipe.ingredients.recipe.ingredients.personalPrices' => $personalPricesForUser,
                'recipe.ingredients.recipe.ingredients.pricePreferences' => $preferencesForUser,
            ];
        }

        $favorites = $user->favoriteItems()
            ->with($relations)
            ->orderByPivot('created_at', 'desc')
            ->get();

        return $favorites->map(fn (Item $item) => $server
            ? $this->analyze($item, $server, $user, $includeCraftTree)
            : $this->withoutAnalysis($item));
    }

    private function analyze(Item $item, Server $server, User $user, bool $includeCraftTree): array
    {
        $analysis = $this->withoutAnalysis($item);
        $directPrice = $item->getPriceForServer($server, $user);

        if ($directPrice) {
            $analysis['direct_price'] = $directPrice->price;
        }

        if ($item->recipe) {
            $calculated = [];
            $craftCost = $item->recipe->calculateCost($server, $calculated, $user);
            if ($craftCost !== null) {
                $analysis['craft_cost'] = $craftCost;
                if ($includeCraftTree) {
                    $analysis['craft_tree'] = $this->buildCraftTree($item, $server, $calculated, $user);
                }
            }
        }

        if ($analysis['direct_price'] && $analysis['craft_cost']) {
            if ($analysis['craft_cost'] < $analysis['direct_price']) {
                $analysis['best_option'] = 'craft';
                $analysis['savings'] = $analysis['direct_price'] - $analysis['craft_cost'];
            } else {
                $analysis['best_option'] = 'buy';
                $analysis['savings'] = $analysis['craft_cost'] - $analysis['direct_price'];
            }
        } elseif ($analysis['direct_price']) {
            $analysis['best_option'] = 'buy';
        } elseif ($analysis['craft_cost']) {
            $analysis['best_option'] = 'craft';
        }

        return $analysis;
    }

    private function withoutAnalysis(Item $item): array
    {
        return [
            'item' => $item,
            'direct_price' => null,
            'craft_cost' => null,
            'best_option' => 'unavailable',
            'savings' => 0,
            'craft_tree' => null,
        ];
    }

    private function buildCraftTree(Item $item, Server $server, array &$calculated, User $user): array
    {
        if (! $item->recipe) {
            return [];
        }

        $tree = [];
        foreach ($item->recipe->ingredients as $ingredient) {
            $ingredientData = [
                'item' => $ingredient,
                'quantity' => $ingredient->pivot->quantity,
                'direct_price' => null,
                'craft_cost' => null,
                'chosen_method' => 'unavailable',
                'subtree' => [],
            ];

            $directPrice = $ingredient->getPriceForServer($server, $user);
            if ($directPrice) {
                $ingredientData['direct_price'] = $directPrice->price;
            }

            if ($ingredient->recipe) {
                if (! isset($calculated[$ingredient->id])) {
                    $craftCost = $ingredient->recipe->calculateCost($server, $calculated, $user);
                    if ($craftCost !== null) {
                        $calculated[$ingredient->id] = $craftCost;
                    }
                }

                if (isset($calculated[$ingredient->id])) {
                    $ingredientData['craft_cost'] = $calculated[$ingredient->id];

                    if ($ingredientData['direct_price']) {
                        if ($ingredientData['craft_cost'] < $ingredientData['direct_price']) {
                            $ingredientData['chosen_method'] = 'craft';
                            $ingredientData['subtree'] = $this->buildCraftTree($ingredient, $server, $calculated, $user);
                        } else {
                            $ingredientData['chosen_method'] = 'buy';
                        }
                    } else {
                        $ingredientData['chosen_method'] = 'craft';
                        $ingredientData['subtree'] = $this->buildCraftTree($ingredient, $server, $calculated, $user);
                    }
                }
            }

            if ($ingredientData['chosen_method'] === 'unavailable' && $ingredientData['direct_price']) {
                $ingredientData['chosen_method'] = 'buy';
            }

            $tree[] = $ingredientData;
        }

        return $tree;
    }
}
