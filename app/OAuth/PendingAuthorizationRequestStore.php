<?php

namespace App\OAuth;

use Exception;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\InvalidAuthTokenException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;

class PendingAuthorizationRequestStore
{
    private const SESSION_KEY = 'passport.pending_authorization_requests';

    private const LIFETIME_SECONDS = 600;

    private const MAX_PENDING_REQUESTS = 10;

    /**
     * Keep the authorization request associated with the consent form token.
     */
    public function remember(Request $request, string $authToken): void
    {
        $serializedRequest = $request->session()->get('authRequest');

        if (! is_string($serializedRequest)) {
            return;
        }

        $now = now()->timestamp;
        $pendingRequests = $this->pendingRequests($request);

        $pendingRequests = array_filter(
            $pendingRequests,
            fn (mixed $pendingRequest): bool => is_array($pendingRequest)
                && isset($pendingRequest['created_at'])
                && (int) $pendingRequest['created_at'] >= $now - self::LIFETIME_SECONDS
        );

        $pendingRequests[$authToken] = [
            'auth_request' => $serializedRequest,
            'created_at' => $now,
        ];

        uasort(
            $pendingRequests,
            fn (array $left, array $right): int => $left['created_at'] <=> $right['created_at']
        );

        $request->session()->put(
            self::SESSION_KEY,
            array_slice($pendingRequests, -self::MAX_PENDING_REQUESTS, null, true)
        );
    }

    /**
     * Pull the authorization request matching the submitted consent form.
     */
    public function pull(Request $request): AuthorizationRequestInterface
    {
        $authToken = $request->input('auth_token');

        if (! is_string($authToken) || $authToken === '') {
            throw InvalidAuthTokenException::different();
        }

        $pendingRequests = $this->pendingRequests($request);
        $pendingRequest = $pendingRequests[$authToken] ?? null;

        if (is_array($pendingRequest) && is_string($pendingRequest['auth_request'] ?? null)) {
            unset($pendingRequests[$authToken]);
            $request->session()->put(self::SESSION_KEY, $pendingRequests);

            if ($this->matchesCurrentToken($request, $authToken)) {
                $request->session()->forget(['authToken', 'authRequest']);
            }

            return $this->unserialize($pendingRequest['auth_request']);
        }

        // Requests opened before this fix was deployed still use Passport's
        // original single-request session keys.
        if (! $this->matchesCurrentToken($request, $authToken)) {
            throw InvalidAuthTokenException::different();
        }

        $serializedRequest = $request->session()->pull('authRequest');
        $request->session()->forget('authToken');

        if (! is_string($serializedRequest)) {
            throw new Exception('Authorization request was not present in the session.');
        }

        return $this->unserialize($serializedRequest);
    }

    /**
     * @return array<string, array{auth_request: string, created_at: int}>
     */
    private function pendingRequests(Request $request): array
    {
        $pendingRequests = $request->session()->get(self::SESSION_KEY, []);

        return is_array($pendingRequests) ? $pendingRequests : [];
    }

    private function matchesCurrentToken(Request $request, string $authToken): bool
    {
        $currentToken = $request->session()->get('authToken');

        return is_string($currentToken) && hash_equals($currentToken, $authToken);
    }

    private function unserialize(string $serializedRequest): AuthorizationRequestInterface
    {
        $authRequest = unserialize($serializedRequest, ['allowed_classes' => [
            \League\OAuth2\Server\RequestTypes\AuthorizationRequest::class,
            \Laravel\Passport\Bridge\Client::class,
            \Laravel\Passport\Bridge\Scope::class,
            \Laravel\Passport\Bridge\User::class,
        ]]);

        if (! $authRequest instanceof AuthorizationRequestInterface) {
            throw new Exception('Authorization request stored in the session is invalid.');
        }

        return $authRequest;
    }
}
