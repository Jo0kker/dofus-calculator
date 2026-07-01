<?php

use App\Models\User;

test('profile information can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    $this->put('/user/profile-information', [
        'name' => 'Test Name',
        'email' => 'test@example.com',
        'interface_mode' => 'classic',
    ]);

    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual('test@example.com');
});

test('interface mode can be updated from profile settings', function () {
    $this->actingAs($user = User::factory()->create(['interface_mode' => 'classic']));

    $this->put('/user/profile-information', [
        'name' => $user->name,
        'email' => $user->email,
        'interface_mode' => 'desktop',
    ]);

    expect($user->fresh()->interface_mode)->toBe('desktop');
});

test('interface mode must be classic or desktop', function () {
    $this->actingAs($user = User::factory()->create(['interface_mode' => 'classic']));

    $response = $this->put('/user/profile-information', [
        'name' => $user->name,
        'email' => $user->email,
        'interface_mode' => 'terminal',
    ]);

    $response->assertSessionHasErrors('interface_mode', null, 'updateProfileInformation');

    expect($user->fresh()->interface_mode)->toBe('classic');
});
