<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePkceS256
{
    /**
     * Require the secure PKCE variant and a CSRF state value on authorization requests.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || ! $request->routeIs('passport.authorizations.authorize')) {
            return $next($request);
        }

        abort_unless(
            $request->filled('code_challenge') && $request->input('code_challenge_method') === 'S256',
            400,
            'OAuth authorization requests must use PKCE with the S256 challenge method.'
        );

        abort_unless(
            $request->filled('state'),
            400,
            'OAuth authorization requests must include a state value.'
        );

        return $next($request);
    }
}
