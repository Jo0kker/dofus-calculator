<?php

namespace App\Services;

use Laravel\Passport\Client;
use Laravel\Passport\Token;

class OAuthApplicationManager
{
    public function revoke(Client $client): void
    {
        $client->getConnection()->transaction(function () use ($client): void {
            $this->revokeTokens($client);
            $client->forceFill(['revoked' => true])->save();
        });
    }

    public function restore(Client $client): void
    {
        $client->forceFill(['revoked' => false])->save();
    }

    public function revokeTokens(Client $client): void
    {
        $client->tokens()
            ->where('revoked', false)
            ->with('refreshToken')
            ->each(function (Token $token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
    }
}
