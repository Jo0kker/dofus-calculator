<?php

namespace App\Providers;

use Carbon\CarbonInterval;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Passport::tokensCan([
            'profile:read' => 'Consulter le profil du compte',
        ]);

        Passport::defaultScopes(['profile:read']);
        Passport::tokensExpireIn(CarbonInterval::minutes(config('passport.access_token_lifetime')));
        Passport::refreshTokensExpireIn(CarbonInterval::minutes(config('passport.refresh_token_lifetime')));
        Passport::authorizationView('oauth.authorize');

        Gate::define('moderate', function ($user) {
            return $user->canModerate();
        });

        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });
    }
}
