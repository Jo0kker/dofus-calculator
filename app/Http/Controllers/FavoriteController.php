<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $serverId = session('selected_server_id');
        $server = Server::find($serverId);
        
        $favorites = auth()->user()->favoriteItems()
            ->with([
                'recipe.ingredients.recipe',
                'prices' => function ($q) use ($serverId) {
                    if ($serverId) {
                        $q->where('server_id', $serverId)
                          ->where('status', 'approved');
                    }
                }
            ])
            ->get();
        
        // Calculer les coûts récursifs pour chaque favori
        $favoriteAnalysis = [];
        if ($server) {
            foreach ($favorites as $favorite) {
                $analysis = $this->analyzeItem($favorite, $server);
                if ($analysis) {
                    $favoriteAnalysis[] = $analysis;
                }
            }
        }
        
        return Inertia::render('Favorites/Index', [
            'favorites' => $favoriteAnalysis,
        ]);
    }
    
    public function toggle(Item $item)
    {
        $isFavorite = auth()->user()->toggleFavorite($item);
        
        return back()->with('success', $isFavorite ? 'Ajouté aux favoris' : 'Retiré des favoris');
    }
    
    private function analyzeItem(Item $item, Server $server): ?array
    {
        $analysis = [
            'item' => $item,
            'direct_price' => null,
            'craft_cost' => null,
            'best_option' => 'unavailable',
            'savings' => 0,
            'craft_tree' => null,
        ];
        
        // Prix direct
        $directPrice = $item->getPriceForServer($server);
        if ($directPrice) {
            $analysis['direct_price'] = $directPrice->price;
        }
        
        // Coût de craft si possible
        if ($item->recipe) {
            $calculated = [];
            $craftCost = $item->recipe->calculateCost($server, $calculated);
            if ($craftCost !== null) {
                $analysis['craft_cost'] = $craftCost;
                $analysis['craft_tree'] = $this->buildCraftTree($item, $server, $calculated);
            }
        }
        
        // Déterminer la meilleure option
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
        
        return $analysis['best_option'] !== 'unavailable' ? $analysis : null;
    }
    
    private function buildCraftTree(Item $item, Server $server, array $calculated): array
    {
        if (!$item->recipe) {
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
            
            $directPrice = $ingredient->getPriceForServer($server);
            if ($directPrice) {
                $ingredientData['direct_price'] = $directPrice->price;
            }
            
            if ($ingredient->recipe && isset($calculated[$ingredient->id])) {
                $ingredientData['craft_cost'] = $calculated[$ingredient->id];
                $ingredientData['subtree'] = $this->buildCraftTree($ingredient, $server, $calculated);
            }
            
            // Déterminer quelle méthode a été choisie
            if ($ingredientData['direct_price'] && $ingredientData['craft_cost']) {
                $ingredientData['chosen_method'] = $ingredientData['craft_cost'] < $ingredientData['direct_price'] ? 'craft' : 'buy';
            } elseif ($ingredientData['direct_price']) {
                $ingredientData['chosen_method'] = 'buy';
            } elseif ($ingredientData['craft_cost']) {
                $ingredientData['chosen_method'] = 'craft';
            }
            
            $tree[] = $ingredientData;
        }
        
        return $tree;
    }
}
