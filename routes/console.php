<?php

use App\Jobs\ImportRecipesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Import des recettes DofusDB tous les jours à 4h du matin
// Dispatch le job pour bénéficier des notifications Discord et du suivi de progression
Schedule::call(function () {
    if (Cache::get('import_recipes_status') === 'running') {
        return;
    }

    ImportRecipesJob::dispatch('Planification quotidienne');
})->name('import-recipes-daily')
    ->dailyAt('04:00')
    ->withoutOverlapping();
