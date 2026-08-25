<?php

use App\Actions\Jetstream\DeleteUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

it('publishes the public store compliance pages', function () {
    $this->get('/privacy-policy')->assertOk()->assertSee('Dofus Calc');
    $this->get('/terms-of-service')->assertOk()->assertSee('Dofus Calculator');
    $this->get('/support')->assertOk()->assertSee('Assistance');
    $this->get('/account-deletion')->assertOk()->assertSee('Supprimer mon compte');
});

it('removes passport credentials when deleting an account', function () {
    $user = User::factory()->create();
    $clientId = (string) Str::uuid();

    DB::table('oauth_access_tokens')->insert([
        'id' => 'access-token',
        'user_id' => $user->id,
        'client_id' => $clientId,
        'name' => null,
        'scopes' => '[]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);
    DB::table('oauth_refresh_tokens')->insert([
        'id' => 'refresh-token',
        'access_token_id' => 'access-token',
        'revoked' => false,
        'expires_at' => now()->addMonth(),
    ]);
    DB::table('oauth_auth_codes')->insert([
        'id' => 'authorization-code',
        'user_id' => $user->id,
        'client_id' => $clientId,
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);
    DB::table('oauth_device_codes')->insert([
        'id' => 'device-code',
        'user_id' => $user->id,
        'client_id' => $clientId,
        'user_code' => 'ABC12345',
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);

    app(DeleteUser::class)->delete($user);

    expect(User::query()->find($user->id))->toBeNull()
        ->and(DB::table('oauth_access_tokens')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('oauth_refresh_tokens')->where('access_token_id', 'access-token')->exists())->toBeFalse()
        ->and(DB::table('oauth_auth_codes')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('oauth_device_codes')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('removes oauth applications owned by a deleted developer account', function () {
    $developer = User::factory()->create();
    $customer = User::factory()->create();
    $application = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Application du développeur',
        ['https://example.test/callback'],
        false,
        $developer,
    );

    $token = Passport::token()->newQuery()->forceCreate([
        'id' => str_repeat('c', 80),
        'user_id' => $customer->id,
        'client_id' => $application->id,
        'scopes' => ['profile:read'],
        'revoked' => false,
        'expires_at' => now()->addHour(),
    ]);

    app(DeleteUser::class)->delete($developer);

    $this->assertDatabaseMissing('oauth_clients', ['id' => $application->id]);
    $this->assertDatabaseMissing('oauth_access_tokens', ['id' => $token->id]);
});
