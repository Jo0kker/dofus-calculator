<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Server;
use App\Services\FavoriteAnalysisService;
use Illuminate\Http\Request;

class DesktopFavoriteController extends Controller
{
    public function __construct(private readonly FavoriteAnalysisService $favoriteAnalysisService) {}

    public function index(Request $request)
    {
        $this->ensureDesktopMode($request);

        $user = $request->user();
        $serverId = $user->server_id ?: session('selected_server_id');
        $server = Server::find($serverId);
        $favorites = $this->favoriteAnalysisService
            ->forUser($user, $server, includeCraftTree: false)
            ->map(function (array $analysis) {
                $item = $analysis['item'];

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'category' => $item->category,
                    'level' => $item->level,
                    'image_url' => $item->image_url,
                    'is_craftable' => $item->recipe !== null,
                    'profession' => $item->recipe?->profession,
                    'favorited_at' => $item->pivot?->created_at?->toISOString(),
                    'direct_price' => $analysis['direct_price'],
                    'craft_cost' => $analysis['craft_cost'],
                    'best_option' => $analysis['best_option'],
                    'savings' => $analysis['savings'],
                ];
            })
            ->values();

        return response()->json([
            'favorites' => $favorites,
            'types' => $favorites->pluck('type')->filter()->unique()->sort()->values(),
            'professions' => $favorites->pluck('profession')->filter()->unique()->sort()->values(),
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
