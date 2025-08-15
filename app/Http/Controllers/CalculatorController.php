<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\Server;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalculatorController extends Controller
{
    public function index(Request $request)
    {
        try {
            $serverId = session('selected_server_id');
        } catch (\Exception $e) {
            $serverId = null;
        }

        if (!$serverId) {
            $defaultServer = Server::where('is_active', true)->first();
            $serverId = $defaultServer ? $defaultServer->id : null;
            if ($serverId) {
                try {
                    session(['selected_server_id' => $serverId]);
                } catch (\Exception $e) {
                    // Ignore session errors
                }
            }
        }

        $server = Server::find($serverId);

        $profitableRecipes = [];

        if ($server) {
            $query = Recipe::query();

            // Profession obligatoire
            if (!$request->filled('profession')) {
                // Pas de profession = pas de résultats
                $profitableRecipes = [];
            } else {
                $query->where('profession', $request->profession);

                if ($request->filled('min_level')) {
                    $query->where('profession_level', '>=', $request->min_level);
                }

                if ($request->filled('max_level')) {
                    $query->where('profession_level', '<=', $request->max_level);
                }

                $recipes = $query->get();
                
                // Charger les relations après
                $recipes->load('item', 'ingredients');

                foreach ($recipes as $recipe) {
                $profitData = $recipe->calculateProfit($server);

                if ($profitData && $profitData['profit'] > 0) {
                    $profitableRecipes[] = [
                        'recipe' => $recipe,
                        'item' => $recipe->item,
                        'cost' => $profitData['cost'],
                        'revenue' => $profitData['revenue'],
                        'profit' => $profitData['profit'],
                        'profit_margin' => $profitData['profit_margin'],
                    ];
                }
            }

            usort($profitableRecipes, function ($a, $b) use ($request) {
                $sortBy = $request->get('sort_by', 'profit');

                switch ($sortBy) {
                    case 'profit_margin':
                        return $b['profit_margin'] <=> $a['profit_margin'];
                    case 'revenue':
                        return $b['revenue'] <=> $a['revenue'];
                    case 'cost':
                        return $a['cost'] <=> $b['cost'];
                    default:
                        return $b['profit'] <=> $a['profit'];
                }
            });

                $profitableRecipes = array_slice($profitableRecipes, 0, 50);
            }
        }

        $professions = Recipe::distinct()->pluck('profession')->filter();

        return Inertia::render('Calculator/Index', [
            'profitableRecipes' => $profitableRecipes,
            'professions' => $professions,
            'filters' => $request->only(['profession', 'min_level', 'max_level', 'sort_by']),
        ]);
    }

    public function show(Recipe $recipe, Request $request)
    {
        try {
            $serverId = session('selected_server_id');
        } catch (\Exception $e) {
            $serverId = null;
        }

        $server = Server::find($serverId);

        if (!$server) {
            return redirect()->route('calculator.index');
        }

        $recipe->load([
            'item.prices' => function ($q) use ($serverId) {
                $q->where('server_id', $serverId)
                  ->where('status', 'approved');
            },
            'ingredients.prices' => function ($q) use ($serverId) {
                $q->where('server_id', $serverId)
                  ->where('status', 'approved');
            }
        ]);

        $profitData = $recipe->calculateProfit($server);

        return Inertia::render('Calculator/Show', [
            'recipe' => $recipe,
            'profitData' => $profitData,
        ]);
    }
}
