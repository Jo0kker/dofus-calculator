<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DesktopItemController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDesktopMode($request);

        $limit = min(max((int) $request->integer('limit', 12), 1), 30);
        $search = trim((string) $request->query('search', ''));

        $query = Item::query()
            ->with('recipe')
            ->withCount('recipe')
            ->orderByRaw('level is null, level asc')
            ->orderBy('name');

        if ($search !== '') {
            $normalizedSearch = Str::lower($search);
            $query->whereRaw('lower(name) like ?', ["%{$normalizedSearch}%"]);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('min_level')) {
            $query->where('level', '>=', (int) $request->query('min_level'));
        }

        if ($request->filled('max_level')) {
            $query->where('level', '<=', (int) $request->query('max_level'));
        }

        return response()->json([
            'items' => $query->limit($limit)->get()->map(fn (Item $item) => $this->itemSummary($item))->values(),
            'types' => Item::query()->distinct()->orderBy('type')->pluck('type')->filter()->values(),
        ]);
    }

    public function show(Request $request, Item $item)
    {
        $this->ensureDesktopMode($request);

        $item->load([
            'recipe.ingredients',
            'prices' => fn ($query) => $query->where('status', 'approved')->latest()->limit(5)->with('server'),
        ]);

        $usedInRecipes = Recipe::query()
            ->whereHas('ingredients', fn ($query) => $query->where('items.id', $item->id))
            ->with('item')
            ->limit(12)
            ->get();

        return response()->json([
            'item' => [
                ...$this->itemSummary($item),
                'metadata' => $item->metadata ?? [],
                'prices' => $item->prices->map(fn ($price) => [
                    'id' => $price->id,
                    'price' => $price->price,
                    'quantity' => $price->quantity,
                    'server' => $price->server?->name,
                    'updated_at' => $price->updated_at?->toISOString(),
                ])->values(),
                'recipe' => $item->recipe ? [
                    'id' => $item->recipe->id,
                    'profession' => $item->recipe->profession,
                    'profession_level' => $item->recipe->profession_level,
                    'quantity_produced' => $item->recipe->quantity_produced,
                    'ingredients' => $item->recipe->ingredients->map(fn (Item $ingredient) => [
                        ...$this->itemSummary($ingredient),
                        'quantity' => $ingredient->pivot->quantity,
                    ])->values(),
                ] : null,
                'used_in_recipes' => $usedInRecipes->map(fn (Recipe $recipe) => $this->itemSummary($recipe->item))->values(),
            ],
        ]);
    }

    private function ensureDesktopMode(Request $request): void
    {
        abort_unless($request->user()?->interface_mode === 'desktop', 403);
    }

    private function itemSummary(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'type' => $item->type,
            'category' => $item->category,
            'level' => $item->level,
            'image_url' => $item->image_url,
            'is_craftable' => (bool) ($item->recipe_count ?? $item->recipe),
            'recipe' => $item->recipe ? [
                'id' => $item->recipe->id,
                'profession' => $item->recipe->profession,
                'profession_level' => $item->recipe->profession_level,
                'quantity_produced' => $item->recipe->quantity_produced,
            ] : null,
        ];
    }
}
