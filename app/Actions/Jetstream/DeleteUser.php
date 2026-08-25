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
            $ownedClientIds = DB::table('oauth_clients')
                ->where('owner_type', $user->getMorphClass())
                ->where('owner_id', $user->getKey())
                ->pluck('id');

            $accessTokenIds = DB::table('oauth_access_tokens')
                ->where(function ($query) use ($user, $ownedClientIds): void {
                    $query->where('user_id', $user->getKey())
                        ->orWhereIn('client_id', $ownedClientIds);
                })
                ->pluck('id');

            DB::table('oauth_refresh_tokens')
                ->whereIn('access_token_id', $accessTokenIds)
                ->delete();
            DB::table('oauth_access_tokens')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_access_tokens')->whereIn('client_id', $ownedClientIds)->delete();
            DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_auth_codes')->whereIn('client_id', $ownedClientIds)->delete();
            DB::table('oauth_device_codes')->where('user_id', $user->getKey())->delete();
            DB::table('oauth_device_codes')->whereIn('client_id', $ownedClientIds)->delete();
            DB::table('oauth_clients')->whereIn('id', $ownedClientIds)->delete();

            $user->tokens()->delete();
            $user->delete();
        });
    }
}
