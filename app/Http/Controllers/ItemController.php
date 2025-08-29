<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with(['recipe.ingredients']);
        
        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('min_level')) {
            $query->where('level', '>=', $request->min_level);
        }
        
        if ($request->filled('max_level')) {
            $query->where('level', '<=', $request->max_level);
        }
        
        $items = $query->paginate(20);
        
        $types = Item::distinct()->pluck('type')->filter();
        
        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'type', 'min_level', 'max_level']),
            'types' => $types,
        ]);
    }
    
    public function show(Item $item, Request $request)
    {
        // Récupérer le serveur sélectionné (session ou utilisateur)
        $selectedServerId = session('selected_server_id');
        if (auth()->check() && auth()->user()->server_id) {
            $selectedServerId = auth()->user()->server_id;
        }
        
        $item->load([
            'recipe.ingredients.prices' => function ($query) use ($selectedServerId) {
                $query->where('status', 'approved');
                if ($selectedServerId) {
                    $query->where('server_id', $selectedServerId);
                }
                $query->orderBy('updated_at', 'desc')
                    ->with('server');
            },
            'recipe.ingredients.recipe', // Pour savoir si l'ingrédient est craftable
            'prices' => function ($query) use ($selectedServerId) {
                $query->where('status', 'approved');
                if ($selectedServerId) {
                    $query->where('server_id', $selectedServerId);
                }
                $query->orderBy('updated_at', 'desc')
                    ->with('server');
            },
            'priceHistories' => function ($query) use ($selectedServerId) {
                if ($selectedServerId) {
                    $query->where('server_id', $selectedServerId);
                }
                $query->orderBy('created_at', 'desc')
                    ->limit(30)
                    ->with('server');
            },
        ]);
        
        // Récupérer les recettes qui utilisent cet item
        $usedInRecipes = \App\Models\Recipe::whereHas('ingredients', function ($query) use ($item) {
            $query->where('item_id', $item->id);
        })->with('item')->get();
        
        // Vérifier si l'item est dans les favoris de l'utilisateur connecté
        $isFavorite = auth()->check() ? auth()->user()->isFavorite($item) : false;
        
        return Inertia::render('Items/Show', [
            'item' => $item,
            'usedInRecipes' => $usedInRecipes,
            'isFavorite' => $isFavorite,
            'selectedServerId' => $selectedServerId,
        ]);
    }
    
    public function calculateRecursiveCost(Item $item, Request $request)
    {
        $serverId = $request->input('server_id');
        
        if (!$serverId) {
            return response()->json(['error' => 'Server ID required'], 400);
        }
        
        $server = Server::find($serverId);
        
        if (!$server) {
            return response()->json(['error' => 'Server not found'], 404);
        }
        
        if (!$item->recipe) {
            return response()->json(['error' => 'Item has no recipe'], 404);
        }
        
        // Charger les relations nécessaires
        $item->load([
            'recipe.ingredients.recipe',
            'recipe.ingredients.prices' => function ($query) use ($serverId) {
                $query->where('server_id', $serverId)
                    ->where('status', 'approved');
            }
        ]);
        
        // Calculer le coût récursif
        $calculated = [];
        $craftCost = $item->recipe->calculateCost($server, $calculated);
        
        // Construire l'arbre de craft détaillé
        $craftTree = $this->buildCraftTree($item, $server, $calculated);
        
        // Obtenir le prix direct
        $directPrice = $item->getPriceForServer($server);
        
        return response()->json([
            'craftCost' => $craftCost,
            'directPrice' => $directPrice ? $directPrice->price : null,
            'craftTree' => $craftTree,
            'bestOption' => $this->determineBestOption($craftCost, $directPrice),
        ]);
    }
    
    private function buildCraftTree($item, $server, &$calculated = [])
    {
        if (!$item->recipe) {
            return null;
        }
        
        $ingredients = [];
        $totalCost = 0;
        
        foreach ($item->recipe->ingredients as $ingredient) {
            $quantity = $ingredient->pivot->quantity;
            $directPrice = $ingredient->getPriceForServer($server);
            
            $ingredientData = [
                'id' => $ingredient->id,
                'name' => $ingredient->name,
                'quantity' => $quantity,
                'image_url' => $ingredient->image_url,
                'directPrice' => $directPrice ? $directPrice->price : null,
            ];
            
            // Si l'ingrédient a une recette, calculer récursivement
            if ($ingredient->recipe && !isset($calculated[$ingredient->id])) {
                $craftCost = $ingredient->recipe->calculateCost($server, $calculated);
                $ingredientData['craftCost'] = $craftCost;
                $ingredientData['hasCraft'] = true;
                
                // Déterminer quelle option est utilisée
                if ($craftCost !== null && $directPrice) {
                    $ingredientData['usedPrice'] = min($craftCost, $directPrice->price);
                    $ingredientData['usedMethod'] = $craftCost < $directPrice->price ? 'craft' : 'buy';
                } elseif ($craftCost !== null) {
                    $ingredientData['usedPrice'] = $craftCost;
                    $ingredientData['usedMethod'] = 'craft';
                } else {
                    $ingredientData['usedPrice'] = $directPrice ? $directPrice->price : null;
                    $ingredientData['usedMethod'] = 'buy';
                }
                
                // Construire l'arbre récursif si on utilise le craft
                if ($ingredientData['usedMethod'] === 'craft') {
                    $ingredientData['craftTree'] = $this->buildCraftTree($ingredient, $server, $calculated);
                }
            } else {
                $ingredientData['hasCraft'] = false;
                $ingredientData['usedPrice'] = $directPrice ? $directPrice->price : null;
                $ingredientData['usedMethod'] = 'buy';
            }
            
            if ($ingredientData['usedPrice'] !== null) {
                $totalCost += $ingredientData['usedPrice'] * $quantity;
            }
            
            $ingredients[] = $ingredientData;
        }
        
        return [
            'ingredients' => $ingredients,
            'totalCost' => $totalCost,
        ];
    }
    
    private function determineBestOption($craftCost, $directPrice)
    {
        if ($craftCost === null && $directPrice === null) {
            return 'none';
        }
        
        if ($craftCost === null) {
            return 'buy';
        }
        
        if ($directPrice === null) {
            return 'craft';
        }
        
        return $craftCost < $directPrice->price ? 'craft' : 'buy';
    }
}
