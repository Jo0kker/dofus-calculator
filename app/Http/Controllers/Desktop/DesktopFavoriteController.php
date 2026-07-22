<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class DesktopFavoriteController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDesktopMode($request);

        $favorites = $request->user()
            ->favoriteItems()
            ->withCount('recipe')
            ->orderByPivot('created_at', 'desc')
            ->get()
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'category' => $item->category,
                'level' => $item->level,
                'image_url' => $item->image_url,
                'is_craftable' => $item->recipe_count > 0,
                'favorited_at' => $item->pivot?->created_at?->toISOString(),
            ])
            ->values();

        return response()->json([
            'favorites' => $favorites,
            'types' => $favorites->pluck('type')->filter()->unique()->sort()->values(),
        ]);
    }

    public function store(Request $request, Item $item)
    {
        $this->ensureDesktopMode($request);

        $request->user()->favoriteItems()->syncWithoutDetaching([$item->id]);

        return response()->json(['is_favorite' => true]);
    }

    public function destroy(Request $request, Item $item)
    {
        $this->ensureDesktopMode($request);

        $request->user()->favoriteItems()->detach($item->id);

        return response()->noContent();
    }

    private function ensureDesktopMode(Request $request): void
    {
        abort_unless($request->user()?->interface_mode === 'desktop', 403);
    }
}
