<?php

namespace App\Http\Controllers\OAuth;

use App\OAuth\PendingAuthorizationRequestStore;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController as PassportDenyAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class DenyAuthorizationController extends PassportDenyAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly PendingAuthorizationRequestStore $pendingAuthorizationRequests,
    ) {
        parent::__construct($server);
    }

    /**
     * Deny the authorization request matching this consent form.
     */
    public function deny(Request $request, ResponseInterface $psrResponse): Response
    {
        $authRequest = $this->pendingAuthorizationRequests->pull($request);
        $authRequest->setAuthorizationApproved(false);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}
