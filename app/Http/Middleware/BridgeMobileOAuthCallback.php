<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BridgeMobileOAuthCallback
{
    /**
     * Turn the mobile custom-scheme redirect into a tiny HTML handoff page.
     *
     * Safari on recent iOS releases can leave a web authentication sheet on
     * the consent page when a POST response redirects directly to a custom
     * scheme. A same-origin HTML response gives the user an explicit handoff
     * action. iOS requires that gesture on affected versions; attempting the
     * custom-scheme navigation automatically leaves the auth sheet blank.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (! $this->isAuthorizationDecision($request) || ! $response->isRedirection()) {
            return $response;
        }

        $callbackUrl = (string) $response->headers->get('Location');

        if (! $this->isMobileCallback($callbackUrl)) {
            return $response;
        }

        return response()
            ->view('oauth.mobile-callback', [
                'callbackUrl' => $callbackUrl,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
            );
    }

    private function isAuthorizationDecision(Request $request): bool
    {
        return $request->routeIs('passport.authorizations.approve')
            || $request->routeIs('passport.authorizations.deny');
    }

    private function isMobileCallback(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'dofuscalculator'
            && ($parts['host'] ?? null) === 'auth'
            && ($parts['path'] ?? null) === '/callback';
    }
}
