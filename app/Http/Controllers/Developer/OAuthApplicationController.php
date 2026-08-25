<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\OAuthApplicationManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class OAuthApplicationController extends Controller
{
    private const MAX_APPLICATIONS = 5;

    public function index(Request $request): Response
    {
        $showRevoked = $request->boolean('archived');
        $ownedApplications = $request->user()->oauthApps();
        $revokedApplicationsCount = (clone $ownedApplications)->where('revoked', true)->count();

        $applications = $ownedApplications
            ->when(! $showRevoked, fn ($query) => $query->where('revoked', false))
            ->withCount([
                'tokens as active_tokens_count' => fn ($query) => $query
                    ->where('revoked', false)
                    ->where('expires_at', '>', now()),
            ])
            ->latest()
            ->get()
            ->map(fn (Client $client): array => $this->applicationPayload($client));

        return Inertia::render('Developer/OAuthApplications', [
            'applications' => $applications,
            'maxApplications' => self::MAX_APPLICATIONS,
            'authorizationEndpoint' => url('/oauth/authorize'),
            'tokenEndpoint' => url('/oauth/token'),
            'scopes' => [
                ['id' => 'profile:read', 'description' => 'Consulter le profil du compte'],
            ],
            'showRevoked' => $showRevoked,
            'revokedApplicationsCount' => $revokedApplicationsCount,
        ]);
    }

    public function store(Request $request, ClientRepository $clients): RedirectResponse
    {
        if ($request->user()->oauthApps()->where('revoked', false)->count() >= self::MAX_APPLICATIONS) {
            throw ValidationException::withMessages([
                'name' => 'Vous avez atteint la limite de '.self::MAX_APPLICATIONS.' applications actives.',
            ]);
        }

        [$name, $redirectUris] = $this->validatedApplication($request);

        $clients->createAuthorizationCodeGrantClient(
            $name,
            $redirectUris,
            false,
            $request->user(),
        );

        return back()->with('success', 'Application créée.');
    }

    public function update(Request $request, Client $oauthApplication, ClientRepository $clients): RedirectResponse
    {
        $this->ensureOwner($request, $oauthApplication);
        [$name, $redirectUris] = $this->validatedApplication($request);

        $clients->update($oauthApplication, $name, $redirectUris);

        return back()->with('success', 'Application mise à jour.');
    }

    public function destroy(
        Request $request,
        Client $oauthApplication,
        OAuthApplicationManager $applications,
    ): RedirectResponse {
        $this->ensureOwner($request, $oauthApplication);
        $applications->revoke($oauthApplication);

        return back()->with('success', 'Application supprimée et sessions coupées.');
    }

    /**
     * @return array{string, list<string>}
     */
    private function validatedApplication(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'redirect_uris' => ['required', 'string', 'max:2000'],
        ]);

        $redirectUris = collect(preg_split('/\R/', $validated['redirect_uris']))
            ->map(fn (string $uri): string => trim($uri))
            ->filter()
            ->unique()
            ->values();

        if ($redirectUris->isEmpty() || $redirectUris->count() > 5) {
            throw ValidationException::withMessages([
                'redirect_uris' => 'Indiquez entre 1 et 5 URL de redirection, une par ligne.',
            ]);
        }

        foreach ($redirectUris as $redirectUri) {
            if (! $this->isAllowedRedirectUri($redirectUri)) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "L’URL de redirection « {$redirectUri} » n’est pas valide.",
                ]);
            }
        }

        return [$validated['name'], $redirectUris->all()];
    }

    private function isAllowedRedirectUri(string $uri): bool
    {
        if (preg_match('/\s/', $uri)) {
            return false;
        }

        $parts = parse_url($uri);

        if (! is_array($parts) || empty($parts['scheme']) || isset($parts['fragment'], $parts['user'], $parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host'] ?? '');

        if ($scheme === 'https') {
            return $host !== '';
        }

        if ($scheme === 'http') {
            return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true);
        }

        return preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme) === 1
            && ! in_array($scheme, ['data', 'file', 'javascript'], true)
            && str_starts_with($uri, $scheme.'://');
    }

    private function ensureOwner(Request $request, Client $client): void
    {
        abort_unless(
            $client->owner_type === $request->user()->getMorphClass()
                && (string) $client->owner_id === (string) $request->user()->getKey(),
            404,
        );
    }

    /** @return array<string, mixed> */
    private function applicationPayload(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'redirect_uris' => $client->redirect_uris,
            'revoked' => $client->revoked,
            'active_tokens_count' => $client->active_tokens_count,
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ];
    }
}
