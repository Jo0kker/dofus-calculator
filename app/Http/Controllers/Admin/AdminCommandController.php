<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportRecipesJob;
use Carbon\Carbon;
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
        $lock = Cache::lock('import_recipes_lock', 10);

        if (!$lock->get()) {
            return back()->with('error', 'Une action est déjà en cours, réessayez.');
        }

        try {
            $currentStatus = Cache::get('import_recipes_status');

            if ($currentStatus === 'running') {
                return back()->with('error', 'Un import est déjà en cours.');
            }

            $userName = $request->user()->name;

            ImportRecipesJob::dispatch($userName);

            return back()->with('success', 'Import des recettes lancé en arrière-plan.');
        } finally {
            $lock->release();
        }
    }

    public function importStatus()
    {
        return response()->json($this->getImportStatus());
    }

    private function getImportStatus(): array
    {
        $status = Cache::get('import_recipes_status', 'idle');
        $startedAt = Cache::get('import_recipes_started_at');

        // Detect stuck jobs: if running for more than 2 hours, mark as failed
        if ($status === 'running' && $startedAt) {
            $startedTime = Carbon::parse($startedAt);
            if ($startedTime->diffInMinutes(now()) > 120) {
                Cache::put('import_recipes_status', 'failed', now()->addHours(6));
                Cache::forget('import_recipes_progress');
                $status = 'failed';
            }
        }

        return [
            'status' => $status,
            'started_at' => $startedAt,
            'progress' => Cache::get('import_recipes_progress'),
            'last_result' => Cache::get('import_recipes_last_result'),
        ];
    }
}
