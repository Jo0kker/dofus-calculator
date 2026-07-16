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

        $userId = $request->user()->id;
        $selectedServerId = session('selected_server_id');
        if ($request->user()?->server_id) {
            $selectedServerId = $request->user()->server_id;
        }

        $approvedPricesForServer = function ($query) use ($selectedServerId) {
            $query->where('status', 'approved');
            if ($selectedServerId) {
                $query->where('server_id', $selectedServerId);
            }
            $query->orderBy('updated_at', 'desc')->with([
                'server',
                'user:id,name,price_contributions_count,price_reliability_score,price_reliability_samples',
            ]);
        };

        $personalPricesForUser = function ($query) use ($userId, $selectedServerId) {
            $query->where('user_id', $userId);
            if ($selectedServerId) {
                $query->where('server_id', $selectedServerId);
            }
        };

        $preferencesForUser = function ($query) use ($userId, $selectedServerId) {
            $query->where('user_id', $userId);
            if ($selectedServerId) {
                $query->where('server_id', $selectedServerId);
            }
        };

        $item->load([
            'recipe.ingredients.prices' => $approvedPricesForServer,
            'recipe.ingredients.personalPrices' => $personalPricesForUser,
            'recipe.ingredients.pricePreferences' => $preferencesForUser,
            'recipe.ingredients.recipe.ingredients.prices' => $approvedPricesForServer,
            'recipe.ingredients.recipe.ingredients.personalPrices' => $personalPricesForUser,
            'recipe.ingredients.recipe.ingredients.pricePreferences' => $preferencesForUser,
            'prices' => $approvedPricesForServer,
            'personalPrices' => $personalPricesForUser,
            'pricePreferences' => $preferencesForUser,
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
                'prices' => $item->prices->map(fn ($price) => $this->priceSummary($price))->values(),
                'personal_prices' => $item->personalPrices->map(fn ($price) => $this->personalPriceSummary($price))->values(),
                'price_preferences' => $item->pricePreferences->map(fn ($preference) => $this->pricePreferenceSummary($preference))->values(),
                'recipe' => $this->recipePayload($item->recipe),
                'used_in_recipes' => $usedInRecipes->map(fn (Recipe $recipe) => $this->itemSummary($recipe->item))->values(),
            ],
        ]);
    }

    private function recipePayload(?Recipe $recipe, int $depth = 0): ?array
    {
        if (! $recipe) {
            return null;
        }

        return [
            'id' => $recipe->id,
            'profession' => $recipe->profession,
            'profession_level' => $recipe->profession_level,
            'quantity_produced' => $recipe->quantity_produced,
            'ingredients' => $recipe->ingredients->map(fn (Item $ingredient) => [
                ...$this->itemSummary($ingredient),
                'pivot' => ['quantity' => $ingredient->pivot->quantity],
                'quantity' => $ingredient->pivot->quantity,
                'prices' => $ingredient->prices->map(fn ($price) => $this->priceSummary($price))->values(),
                'personal_prices' => $ingredient->personalPrices->map(fn ($price) => $this->personalPriceSummary($price))->values(),
                'price_preferences' => $ingredient->pricePreferences->map(fn ($preference) => $this->pricePreferenceSummary($preference))->values(),
                'recipe' => $depth < 2 && $ingredient->relationLoaded('recipe')
                    ? $this->recipePayload($ingredient->recipe, $depth + 1)
                    : null,
            ])->values(),
        ];
    }

    private function priceSummary($price): array
    {
        return [
            'id' => $price->id,
            'price' => $price->price,
            'quantity' => $price->quantity,
            'server_id' => $price->server_id,
            'server' => $price->server ? [
                'id' => $price->server->id,
                'name' => $price->server->name,
            ] : null,
            'updated_at' => $price->updated_at?->toISOString(),
            'confidence_score' => $price->confidence_score,
            'confidence_level' => $price->confidence_level,
            'recent_observations_count' => $price->recent_observations_count,
            'recent_contributors_count' => $price->recent_contributors_count,
            'confidence_details' => $price->confidence_details,
            'confidence_computed_at' => $price->confidence_computed_at?->toISOString(),
            'user' => $price->user ? [
                'name' => $price->user->name,
                'price_contributions_count' => $price->user->price_contributions_count,
                'price_reliability_score' => $price->user->price_reliability_score,
                'price_reliability_samples' => $price->user->price_reliability_samples,
            ] : null,
        ];
    }

    private function personalPriceSummary($price): array
    {
        return [
            'id' => $price->id,
            'price' => $price->price,
            'server_id' => $price->server_id,
            'updated_at' => $price->updated_at?->toISOString(),
        ];
    }

    private function pricePreferenceSummary($preference): array
    {
        return [
            'server_id' => $preference->server_id,
            'mode' => $preference->mode,
        ];
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
