<?php

namespace App\Actions\Jetstream;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $user->deleteProfilePhoto();

        DB::transaction(function () use ($user): void {
            $accessTokenIds = DB::table('oauth_access_tokens')
                ->where('user_id', $user->getKey())
                ->pluck('id');

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $accessTokenIds)
                ->delete();
            DB::table('oauth_access_tokens')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->getKey())->delete();

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
