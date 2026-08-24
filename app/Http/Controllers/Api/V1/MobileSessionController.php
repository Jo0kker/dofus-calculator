<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;

class MobileSessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('server');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'profile_photo_url' => $user->profile_photo_url,
                'role' => $user->role,
                'server' => $user->server ? [
                    'id' => $user->server->id,
                    'name' => $user->server->name,
                    'slug' => $user->server->slug,
                ] : null,
            ],
        ]);
    }

    public function destroy(Request $request): Response
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof AccessToken) {
            $accessTokenId = $accessToken->toArray()['oauth_access_token_id'] ?? null;
            $token = $accessTokenId
                ? Passport::token()->newQuery()->with('refreshToken')->find($accessTokenId)
                : null;

            $token?->refreshToken?->revoke();
            $token?->revoke();
        }

        return response()->noContent();
    }
}
