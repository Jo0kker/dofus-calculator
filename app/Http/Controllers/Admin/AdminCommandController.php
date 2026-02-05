<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportRecipesJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AdminCommandController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Commands', [
            'importStatus' => $this->getImportStatus(),
        ]);
    }

    public function importRecipes(Request $request)
    {
        $currentStatus = Cache::get('import_recipes_status');

        if ($currentStatus === 'running') {
            return back()->with('error', 'Un import est déjà en cours.');
        }

        $userName = $request->user()->name;

        ImportRecipesJob::dispatch($userName);

        return back()->with('success', 'Import des recettes lancé en arrière-plan.');
    }

    public function importStatus()
    {
        return response()->json($this->getImportStatus());
    }

    private function getImportStatus(): array
    {
        return [
            'status' => Cache::get('import_recipes_status', 'idle'),
            'started_at' => Cache::get('import_recipes_started_at'),
            'progress' => Cache::get('import_recipes_progress'),
            'last_result' => Cache::get('import_recipes_last_result'),
        ];
    }
}
