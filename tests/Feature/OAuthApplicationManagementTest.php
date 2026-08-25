<?php

use App\Models\ApiLog;
use App\Models\User;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

test('a verified user can register and manage a public oauth application', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('developer.oauth-applications.store'), [
        'name' => 'Compagnon Dofus',
        'redirect_uris' => "https://compagnon.example/oauth/callback\ncompagnon://oauth/callback",
    ])->assertRedirect();

    $application = Passport::client()->newQuery()->where('name', 'Compagnon Dofus')->firstOrFail();

    expect((string) $application->owner_id)->toBe((string) $user->id)
        ->and($application->owner_type)->toBe($user->getMorphClass())
        ->and($application->confidential())->toBeFalse()
        ->and($application->redirect_uris)->toBe([
            'https://compagnon.example/oauth/callback',
            'compagnon://oauth/callback',
        ]);

    $this->actingAs($user)->put(route('developer.oauth-applications.update', $application), [
        'name' => 'Compagnon Dofus 2',
        'redirect_uris' => 'https://compagnon.example/auth/callback',
    ])->assertRedirect();

    expect($application->fresh()->name)->toBe('Compagnon Dofus 2')
        ->and($application->fresh()->redirect_uris)->toBe(['https://compagnon.example/auth/callback']);

    $this->actingAs($user)
        ->get(route('developer.oauth-applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Developer/OAuthApplications')
            ->has('applications', 1)
            ->where('applications.0.id', $application->id));
});

test('oauth application redirect urls reject insecure remote http urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('developer.oauth-applications.store'), [
        'name' => 'Application non sûre',
        'redirect_uris' => 'http://example.com/callback',
    ])->assertSessionHasErrors('redirect_uris');

    expect(Passport::client()->newQuery()->where('name', 'Application non sûre')->exists())->toBeFalse();
});

test('a user cannot manage another users oauth application', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Application privée',
        ['https://example.test/callback'],
        false,
        $owner,
    );

    $this->actingAs($intruder)->put(route('developer.oauth-applications.update', $application), [
        'name' => 'Application volée',
        'redirect_uris' => 'https://evil.example/callback',
    ])->assertNotFound();

    $this->actingAs($intruder)
        ->delete(route('developer.oauth-applications.destroy', $application))
        ->assertNotFound();

    expect($application->fresh()->revoked)->toBeFalse();
});

test('revoking an oauth application also revokes its active sessions', function () {
    $owner = User::factory()->create();
    $application = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Application à supprimer',
        ['https://example.test/callback'],
        false,
        $owner,
    );

    $token = Passport::token()->newQuery()->forceCreate([
        'id' => str_repeat('a', 80),
        'user_id' => $owner->id,
        'client_id' => $application->id,
        'scopes' => ['profile:read'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    Passport::refreshToken()->newQuery()->forceCreate([
        'id' => str_repeat('b', 80),
        'access_token_id' => $token->id,
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($owner)
        ->delete(route('developer.oauth-applications.destroy', $application))
        ->assertRedirect();

    expect($application->fresh()->revoked)->toBeTrue()
        ->and($token->fresh()->revoked)->toBeTrue()
        ->and($token->refreshToken->revoked)->toBeTrue();

    $this->actingAs($owner)
        ->get(route('developer.oauth-applications.index'))
        ->assertInertia(fn ($page) => $page
            ->has('applications', 0)
            ->where('showRevoked', false)
            ->where('revokedApplicationsCount', 1));

    $this->actingAs($owner)
        ->get(route('developer.oauth-applications.index', ['archived' => 1]))
        ->assertInertia(fn ($page) => $page
            ->has('applications', 1)
            ->where('applications.0.id', $application->id)
            ->where('applications.0.revoked', true)
            ->where('showRevoked', true));
});

test('only administrators can supervise block and restore oauth applications', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $application = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Application supervisée',
        ['https://example.test/callback'],
        false,
        $owner,
    );

    ApiLog::create([
        'user_id' => $owner->id,
        'oauth_client_id' => $application->id,
        'token_name' => $application->name,
        'endpoint' => 'api/v1/me',
        'method' => 'GET',
        'response_status' => 200,
        'items_affected' => 0,
    ]);

    $this->actingAs($user)->get(route('admin.oauth-applications.index'))->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.oauth-applications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/OAuthApplications')
            ->where('applications.data.0.id', $application->id)
            ->where('applications.data.0.requests_24h', 1));

    $this->actingAs($admin)
        ->post(route('admin.oauth-applications.revoke', $application))
        ->assertRedirect();
    expect($application->fresh()->revoked)->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.oauth-applications.restore', $application))
        ->assertRedirect();
    expect($application->fresh()->revoked)->toBeFalse();
});
