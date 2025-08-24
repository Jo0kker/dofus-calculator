<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;
use App\Models\Server;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Priorité : serveur de l'utilisateur > session > premier serveur actif
        $selectedServerId = null;
        
        if ($request->user() && $request->user()->server_id) {
            $selectedServerId = $request->user()->server_id;
            // Synchroniser la session avec le serveur de l'utilisateur
            session(['selected_server_id' => $selectedServerId]);
        } elseif (session('selected_server_id')) {
            $selectedServerId = session('selected_server_id');
        } else {
            // Serveur par défaut si aucun n'est sélectionné
            $defaultServer = Server::where('is_active', true)
                ->where('is_temporary', false)
                ->orderBy('display_order')
                ->orderBy('name')
                ->first();
            if ($defaultServer) {
                $selectedServerId = $defaultServer->id;
                session(['selected_server_id' => $selectedServerId]);
            }
        }
        
        return [
            ...parent::share($request),
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'servers' => Server::where('is_active', true)
                ->where('is_temporary', false)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get(),
            'selected_server_id' => $selectedServerId,
        ];
    }
}
