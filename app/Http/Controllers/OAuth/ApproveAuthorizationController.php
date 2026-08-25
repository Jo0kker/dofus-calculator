<?php

namespace App\Http\Controllers\OAuth;

use App\OAuth\PendingAuthorizationRequestStore;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly PendingAuthorizationRequestStore $pendingAuthorizationRequests,
    ) {
        parent::__construct($server);
    }

    /**
     * Approve the authorization request matching this consent form.
     */
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $authRequest = $this->pendingAuthorizationRequests->pull($request);
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ), $authRequest->getGrantTypeId() === 'implicit');
    }
}
