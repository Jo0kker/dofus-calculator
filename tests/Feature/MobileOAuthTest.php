<?php

use App\Models\OAuthUser;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privateKey);
    $publicKey = openssl_pkey_get_details($key)['key'];

    config([
        'passport.private_key' => $privateKey,
        'passport.public_key' => $publicKey,
    ]);
});

test('oauth authorization server metadata advertises the secure mobile flow', function () {
    $this->getJson(route('oauth.metadata'))
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', url('/oauth/authorize'))
        ->assertJsonPath('token_endpoint', url('/oauth/token'))
        ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
        ->assertJsonFragment(['profile:read']);
});

test('oauth authorization requests reject unsafe pkce methods', function () {
    $this->get('/oauth/authorize?response_type=code&client_id=test&code_challenge='.str_repeat('a', 43).'&code_challenge_method=plain&state=test-state')
        ->assertBadRequest();

    $this->get('/oauth/authorize?response_type=code&client_id=test&code_challenge='.str_repeat('a', 43).'&code_challenge_method=S256')
        ->assertBadRequest();
});

test('mobile clients can complete authorization code pkce and rotate refresh tokens', function () {
    $user = User::factory()->create();
    $redirectUri = 'https://example.test/mobile/oauth/callback';
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Dofus Calculator Mobile',
        [$redirectUri],
        false,
    );
    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = Str::random(40);

    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'profile:read',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk();

    $authorization = $this->actingAs($user)->post('/oauth/authorize', [
        'auth_token' => session('authToken'),
        'state' => $state,
    ]);
    $authorization->assertRedirect();

    parse_str(parse_url($authorization->headers->get('Location'), PHP_URL_QUERY), $callback);
    expect($callback['state'])->toBe($state);

    $tokens = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->id,
        'redirect_uri' => $redirectUri,
        'code' => $callback['code'],
        'code_verifier' => $verifier,
    ], ['Accept' => 'application/json']);
    $tokens->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

    $this->withToken($tokens->json('access_token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $refreshedTokens = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $client->id,
        'refresh_token' => $tokens->json('refresh_token'),
    ], ['Accept' => 'application/json']);
    $refreshedTokens->assertOk()->assertJsonStructure(['access_token', 'refresh_token', 'expires_in']);

    expect($refreshedTokens->json('refresh_token'))->not->toBe($tokens->json('refresh_token'));

    $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $client->id,
        'refresh_token' => $tokens->json('refresh_token'),
    ], ['Accept' => 'application/json'])
        ->assertBadRequest()
        ->assertJsonPath('error', 'invalid_grant');
});

test('mobile profile requires a passport access token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('mobile profile requires the profile read scope', function () {
    $user = User::factory()->create();
    $oauthUser = OAuthUser::query()->findOrFail($user->id);

    Passport::actingAs($oauthUser, []);

    $this->getJson('/api/v1/me')->assertForbidden();
});

test('mobile profile returns a limited account payload', function () {
    $user = User::factory()->create([
        'name' => 'Joueur mobile',
        'email' => 'mobile@example.test',
        'role' => 'user',
    ]);
    $oauthUser = OAuthUser::query()->findOrFail($user->id);

    Passport::actingAs($oauthUser, ['profile:read']);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Joueur mobile')
        ->assertJsonPath('data.email', 'mobile@example.test')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.price_reliability_score');
});

test('oauth users keep the same relationship foreign keys as web users', function () {
    $user = User::factory()->create();
    $oauthUser = OAuthUser::query()->findOrFail($user->id);

    expect($oauthUser->favorites()->getForeignKeyName())->toBe('user_id')
        ->and($oauthUser->favoriteItems()->getForeignPivotKeyName())->toBe('user_id');
});

test('mobile logout revokes the current access and refresh tokens', function () {
    $user = User::factory()->create();
    $oauthUser = OAuthUser::query()->findOrFail($user->id);
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Dofus Calculator Mobile',
        ['https://example.test/mobile/oauth/callback'],
        false,
    );
    $accessTokenId = Str::random(80);
    $refreshTokenId = Str::random(80);

    Passport::token()->newQuery()->forceCreate([
        'id' => $accessTokenId,
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['profile:read'],
        'revoked' => false,
        'expires_at' => now()->addMinutes(15),
    ]);

    Passport::refreshToken()->newQuery()->forceCreate([
        'id' => $refreshTokenId,
        'access_token_id' => $accessTokenId,
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    Passport::actingAs($oauthUser, ['profile:read'], client: $client);
    $oauthUser->withAccessToken(new AccessToken([
        'oauth_access_token_id' => $accessTokenId,
        'oauth_client_id' => $client->id,
        'oauth_user_id' => $user->id,
        'oauth_scopes' => ['profile:read'],
    ]));

    $this->deleteJson('/api/v1/session')->assertNoContent();

    $this->assertDatabaseHas('oauth_access_tokens', [
        'id' => $accessTokenId,
        'revoked' => true,
    ]);
    $this->assertDatabaseHas('oauth_refresh_tokens', [
        'id' => $refreshTokenId,
        'revoked' => true,
    ]);
});

test('legacy sanctum api tokens keep working during the oauth migration', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Legacy integration', ['read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});
