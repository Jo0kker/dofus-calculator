<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanWritePrices
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->tokenCan('write') && ! $user->tokenCan('prices:write'))) {
            throw new AuthorizationException('Le jeton ne permet pas de publier des prix.');
        }

        return $next($request);
    }
}
