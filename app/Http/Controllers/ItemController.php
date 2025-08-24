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
                $query->with('server');
            },
            'recipe.ingredients.recipe', // Pour savoir si l'ingrédient est craftable
            'prices' => function ($query) use ($selectedServerId) {
                $query->where('status', 'approved');
                if ($selectedServerId) {
                    $query->where('server_id', $selectedServerId);
                }
                $query->with('server');
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
}
