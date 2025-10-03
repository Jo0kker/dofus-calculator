<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackApiUsage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Stocker la requête pour l'utiliser dans terminate()
        $request->attributes->set('_tracking_start_time', microtime(true));

        return $next($request);
    }

    /**
     * Termine le middleware et log la requête après que la réponse soit envoyée
     * Cela permet de capturer l'utilisateur authentifié par Sanctum
     */
    public function terminate(Request $request, Response $response): void
    {
        // Déterminer le nombre d'items affectés
        $itemsAffected = $this->getItemsAffected($request, $response);

        // Récupérer les infos du token si authentifié
        // À ce stade, auth:sanctum a déjà authentifié l'utilisateur
        $user = $request->user();
        $tokenName = null;

        if ($user && $request->bearerToken()) {
            // Récupérer le nom du token utilisé
            $token = $user->tokens()->where('token', hash('sha256', $request->bearerToken()))->first();
            $tokenName = $token?->name;
        }

        // Logger la requête
        try {
            ApiLog::create([
                'user_id' => $user?->id,
                'token_name' => $tokenName,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->sanitizeRequestData($request),
                'response_status' => $response->getStatusCode(),
                'items_affected' => $itemsAffected,
            ]);
        } catch (\Exception $e) {
            // En cas d'erreur de logging, on ne veut pas casser la requête
            logger()->error('Failed to log API usage: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les données de la requête en excluant les données sensibles
     */
    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();

        // Exclure les données sensibles
        unset($data['password'], $data['token'], $data['api_token']);

        return $data;
    }

    /**
     * Détermine le nombre d'items affectés par la requête
     */
    private function getItemsAffected(Request $request, Response $response): int
    {
        // Pour les requêtes POST sur /api/prices
        if ($request->isMethod('POST') && str_contains($request->path(), 'prices')) {
            $content = json_decode($response->getContent(), true);

            // Si c'est une update en masse
            if (isset($content['data']['updated_count'])) {
                return $content['data']['updated_count'];
            }

            // Si c'est une création simple
            if (isset($content['data'])) {
                return 1;
            }
        }

        // Pour les requêtes GET sur /api/items
        if ($request->isMethod('GET') && str_contains($request->path(), 'items')) {
            $content = json_decode($response->getContent(), true);

            // Liste paginée
            if (isset($content['meta']['total'])) {
                return $content['meta']['total'];
            }

            // Item unique
            if (isset($content['data']['id'])) {
                return 1;
            }
        }

        return 0;
    }
}
