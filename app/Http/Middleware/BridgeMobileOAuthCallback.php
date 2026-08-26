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
     * scheme. A same-origin HTML response can initiate the handoff explicitly
     * and also gives the user a manual fallback button.
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

        $nonce = base64_encode(random_bytes(18));

        return response()
            ->view('oauth.mobile-callback', [
                'callbackUrl' => $callbackUrl,
                'nonce' => $nonce,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'nonce-{$nonce}'; script-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
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
