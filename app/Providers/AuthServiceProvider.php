<?php

namespace App\Providers;

use App\Http\Controllers\OAuth\ApproveAuthorizationController;
use App\Http\Controllers\OAuth\DenyAuthorizationController;
use App\OAuth\PendingAuthorizationRequestStore;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController as PassportDenyAuthorizationController;
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

        $this->app->bind(PassportApproveAuthorizationController::class, ApproveAuthorizationController::class);
        $this->app->bind(PassportDenyAuthorizationController::class, DenyAuthorizationController::class);
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
        Passport::authorizationView(function (array $parameters) {
            /** @var Request $request */
            $request = $parameters['request'];

            app(PendingAuthorizationRequestStore::class)->remember($request, $parameters['authToken']);

            return response()->view('oauth.authorize', $parameters);
        });

        Gate::define('moderate', function ($user) {
            return $user->canModerate();
        });

        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });
    }
}
