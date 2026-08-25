<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Services\OAuthApplicationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class OAuthApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Passport::client()->newQuery()->with('owner:id,name,email')->latest();

        $query->when($request->filled('search'), function ($query) use ($request): void {
            $search = $request->string('search')->trim()->value();
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        });

        $query->when($request->status === 'active', fn ($query) => $query->where('revoked', false));
        $query->when($request->status === 'revoked', fn ($query) => $query->where('revoked', true));

        $applications = $query->paginate(25)->withQueryString();
        $ids = $applications->getCollection()->pluck('id');

        $tokenStats = Passport::token()->newQuery()
            ->whereIn('client_id', $ids)
            ->selectRaw('client_id, count(*) as issued_tokens_count')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $activeTokenStats = Passport::token()->newQuery()
            ->whereIn('client_id', $ids)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->selectRaw('client_id, count(*) as active_tokens_count, count(distinct user_id) as active_users_count')
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');

        $usageStats = ApiLog::query()
            ->whereIn('oauth_client_id', $ids)
            ->selectRaw('oauth_client_id, count(*) as total_requests')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as requests_24h', [now()->subDay()])
            ->selectRaw('max(created_at) as last_used_at')
            ->groupBy('oauth_client_id')
            ->get()
            ->keyBy('oauth_client_id');

        $applications->through(function (Client $client) use ($activeTokenStats, $tokenStats, $usageStats): array {
            $tokens = $tokenStats->get($client->id);
            $activeTokens = $activeTokenStats->get($client->id);
            $usage = $usageStats->get($client->id);

            return [
                'id' => $client->id,
                'name' => $client->name,
                'redirect_uris' => $client->redirect_uris,
                'revoked' => $client->revoked,
                'owner' => $client->owner ? [
                    'id' => $client->owner->id,
                    'name' => $client->owner->name,
                    'email' => $client->owner->email,
                ] : null,
                'issued_tokens_count' => (int) ($tokens?->issued_tokens_count ?? 0),
                'active_tokens_count' => (int) ($activeTokens?->active_tokens_count ?? 0),
                'active_users_count' => (int) ($activeTokens?->active_users_count ?? 0),
                'total_requests' => (int) ($usage?->total_requests ?? 0),
                'requests_24h' => (int) ($usage?->requests_24h ?? 0),
                'last_used_at' => $usage?->last_used_at,
                'created_at' => $client->created_at,
            ];
        });

        return Inertia::render('Admin/OAuthApplications', [
            'applications' => $applications,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => Passport::client()->newQuery()->count(),
                'active' => Passport::client()->newQuery()->where('revoked', false)->count(),
                'active_tokens' => Passport::token()->newQuery()
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())
                    ->count(),
                'requests_24h' => ApiLog::whereNotNull('oauth_client_id')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
        ]);
    }

    public function revoke(Client $oauthApplication, OAuthApplicationManager $applications): RedirectResponse
    {
        $applications->revoke($oauthApplication);

        return back()->with('success', 'Application bloquée et sessions révoquées.');
    }

    public function restore(Client $oauthApplication, OAuthApplicationManager $applications): RedirectResponse
    {
        $applications->restore($oauthApplication);

        return back()->with('success', 'Application réactivée. Les anciennes sessions restent révoquées.');
    }

    public function revokeTokens(Client $oauthApplication, OAuthApplicationManager $applications): RedirectResponse
    {
        $applications->revokeTokens($oauthApplication);

        return back()->with('success', 'Toutes les sessions de cette application ont été révoquées.');
    }
}
