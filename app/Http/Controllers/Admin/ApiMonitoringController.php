<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ApiMonitoringController extends Controller
{
    public function index(Request $request)
    {
        // Statistiques générales
        $stats = [
            'total_requests' => ApiLog::count(),
            'total_requests_today' => ApiLog::whereDate('created_at', today())->count(),
            'total_price_updates' => ApiLog::where('endpoint', 'like', '%prices%')
                ->where('method', 'POST')
                ->count(),
            'total_price_updates_today' => ApiLog::where('endpoint', 'like', '%prices%')
                ->where('method', 'POST')
                ->whereDate('created_at', today())
                ->count(),
        ];

        // Top utilisateurs par nombre de requêtes
        $topUsers = ApiLog::select('user_id', DB::raw('count(*) as request_count'))
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('request_count')
            ->limit(10)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($log) {
                return [
                    'user' => $log->user,
                    'request_count' => $log->request_count,
                ];
            });

        // Utilisateurs avec le plus d'updates de prix
        $topPriceUpdaters = ApiLog::select('user_id', DB::raw('count(*) as update_count'), DB::raw('sum(items_affected) as total_prices_updated'))
            ->where('endpoint', 'like', '%prices%')
            ->where('method', 'POST')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('update_count')
            ->limit(10)
            ->with('user:id,name,email')
            ->get()
            ->map(function ($log) {
                return [
                    'user' => $log->user,
                    'update_count' => $log->update_count,
                    'total_prices_updated' => $log->total_prices_updated,
                ];
            });

        // Logs récents avec filtres
        $logsQuery = ApiLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        // Filtres
        if ($request->filled('user_id')) {
            $logsQuery->where('user_id', $request->user_id);
        }

        if ($request->filled('endpoint')) {
            $logsQuery->where('endpoint', 'like', '%' . $request->endpoint . '%');
        }

        if ($request->filled('method')) {
            $logsQuery->where('method', $request->method);
        }

        if ($request->filled('date')) {
            $logsQuery->whereDate('created_at', $request->date);
        }

        $logs = $logsQuery->paginate(50);

        // Liste des utilisateurs pour le filtre
        $users = User::whereHas('apiLogs')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/ApiMonitoring', [
            'stats' => $stats,
            'topUsers' => $topUsers,
            'topPriceUpdaters' => $topPriceUpdaters,
            'logs' => $logs,
            'users' => $users,
            'filters' => $request->only(['user_id', 'endpoint', 'method', 'date']),
        ]);
    }
}
