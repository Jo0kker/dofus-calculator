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
                'recipe.ingredients.recipe.ingredients.prices' => function ($q) use ($serverId) {
                    if ($serverId) {
                        $q->where('server_id', $serverId)
                          ->where('status', 'approved')
                          ->orderBy('updated_at', 'desc');
                    }
                },
                'recipe.ingredients.prices' => function ($q) use ($serverId) {
                    if ($serverId) {
                        $q->where('server_id', $serverId)
                          ->where('status', 'approved')
                          ->orderBy('updated_at', 'desc');
                    }
                },
                'prices' => function ($q) use ($serverId) {
                    if ($serverId) {
                        $q->where('server_id', $serverId)
                          ->where('status', 'approved')
                          ->orderBy('updated_at', 'desc');
                    }
                }
            ])
            ->get();
        
        // Calculer les coûts récursifs pour chaque favori
        $favoriteAnalysis = [];
        if ($server) {
            foreach ($favorites as $favorite) {
                $analysis = $this->analyzeItem($favorite, $server);
                $favoriteAnalysis[] = $analysis;
            }
        } else {
            // Si pas de serveur sélectionné, afficher les items sans analyse
            foreach ($favorites as $favorite) {
                $favoriteAnalysis[] = [
                    'item' => $favorite,
                    'direct_price' => null,
                    'craft_cost' => null,
                    'best_option' => 'unavailable',
                    'savings' => 0,
                    'craft_tree' => null,
                ];
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
    
    private function analyzeItem(Item $item, Server $server): array
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
        
        return $analysis;
    }
    
    private function buildCraftTree(Item $item, Server $server, array &$calculated): array
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
            
            // Vérifier si l'ingrédient a une recette et peut être crafté
            if ($ingredient->recipe) {
                // Calculer le coût de craft si pas déjà fait
                if (!isset($calculated[$ingredient->id])) {
                    $craftCost = $ingredient->recipe->calculateCost($server, $calculated);
                    if ($craftCost !== null) {
                        $calculated[$ingredient->id] = $craftCost;
                    }
                }
                
                if (isset($calculated[$ingredient->id])) {
                    $ingredientData['craft_cost'] = $calculated[$ingredient->id];
                    
                    // Déterminer quelle méthode est utilisée
                    if ($ingredientData['direct_price']) {
                        if ($ingredientData['craft_cost'] < $ingredientData['direct_price']) {
                            $ingredientData['chosen_method'] = 'craft';
                            $ingredientData['subtree'] = $this->buildCraftTree($ingredient, $server, $calculated);
                        } else {
                            $ingredientData['chosen_method'] = 'buy';
                        }
                    } else {
                        $ingredientData['chosen_method'] = 'craft';
                        $ingredientData['subtree'] = $this->buildCraftTree($ingredient, $server, $calculated);
                    }
                }
            }
            
            // Si pas de craft possible ou pas choisi, utiliser l'achat direct
            if ($ingredientData['chosen_method'] === 'unavailable' && $ingredientData['direct_price']) {
                $ingredientData['chosen_method'] = 'buy';
            }
            
            $tree[] = $ingredientData;
        }
        
        return $tree;
    }
}
