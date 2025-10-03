<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configuration de Scramble pour documenter uniquement les routes /api/*
        Scramble::routes(function ($route) {
            // Documenter uniquement les routes qui commencent par api/
            // Exclure api-tokens qui est une route web
            return Str::startsWith($route->uri, 'api/');
        });

        // Ajouter le schéma de sécurité Bearer Token
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });
    }
}
