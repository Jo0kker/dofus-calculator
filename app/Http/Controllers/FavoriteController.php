<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Server;
use App\Services\FavoriteAnalysisService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteAnalysisService $favoriteAnalysisService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $serverId = $user->server_id ?: session('selected_server_id');
        $server = Server::find($serverId);

        return Inertia::render('Favorites/Index', [
            'favorites' => $this->favoriteAnalysisService->forUser($user, $server)->values(),
        ]);
    }

    public function toggle(Item $item)
    {
        $isFavorite = auth()->user()->toggleFavorite($item);

        return back()->with('success', $isFavorite ? 'Ajouté aux favoris' : 'Retiré des favoris');
    }

    public function destroy(Item $item)
    {
        auth()->user()->favoriteItems()->detach($item->id);

        return back()->with('success', 'Retiré des favoris');
    }
}
