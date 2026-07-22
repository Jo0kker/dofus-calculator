<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use App\Models\PriceHistory;
use App\Models\PriceReport;
use App\Models\User;
use App\Services\CommunityPriceTrustService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceReportController extends Controller
{
    public function __construct(private readonly CommunityPriceTrustService $trustService) {}

    public function store(ItemPrice $itemPrice, Request $request)
    {
        $request->validate([
            'comment' => 'required|string|max:500',
        ]);

        // Vérifier si l'utilisateur a déjà un signalement en attente pour ce prix
        $existingPendingReport = PriceReport::where('item_price_id', $itemPrice->id)
            ->where('reported_by', auth()->id())
            ->where('status', 'pending')
            ->first();

        if ($existingPendingReport) {
            return back()->withErrors(['report' => 'Vous avez déjà un signalement en attente pour ce prix.']);
        }

        // Un report sur un consensus multi-contributeurs ne doit pas pénaliser
        // arbitrairement un seul utilisateur. On ne rattache donc un relevé
        // individuel que lorsqu'il est l'unique source du prix affiché.
        $recentContributorCount = PriceHistory::query()
            ->where('item_id', $itemPrice->item_id)
            ->where('server_id', $itemPrice->server_id)
            ->whereNull('rejected_at')
            ->where('created_at', '>=', now()->subDays(CommunityPriceTrustService::WINDOW_DAYS))
            ->distinct()
            ->count('created_by');

        $currentPriceHistory = null;
        if ($recentContributorCount <= 1) {
            $currentPriceHistory = PriceHistory::where('item_id', $itemPrice->item_id)
                ->where('server_id', $itemPrice->server_id)
                ->where('price', $itemPrice->price)
                ->whereNull('rejected_at')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        PriceReport::create([
            'item_price_id' => $itemPrice->id,
            'price_history_id' => $currentPriceHistory?->id,
            'reported_by' => auth()->id(),
            'comment' => $request->comment,
        ]);
        $this->syncPendingReports($itemPrice);

        return back()->with('success', 'Prix signalé avec succès. Merci pour votre contribution !');
    }

    public function index(Request $request)
    {
        // Vérifier que l'utilisateur peut modérer
        if (! auth()->user()->canModerate()) {
            abort(403, 'Accès refusé. Vous devez être modérateur ou administrateur.');
        }

        $query = PriceReport::with(['itemPrice.item', 'itemPrice.user', 'priceHistory', 'reporter', 'reviewer'])
            ->orderBy('created_at', 'desc');

        // Filtrer par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(20);

        return inertia('Moderation/PriceReports', [
            'reports' => $reports,
            'filters' => $request->only(['status']),
        ]);
    }

    public function approve(PriceReport $report, Request $request)
    {
        if (! auth()->user()->canModerate()) {
            abort(403);
        }

        $processed = DB::transaction(function () use ($report, $request) {
            $lockedReport = PriceReport::query()->lockForUpdate()->findOrFail($report->id);
            if ($lockedReport->status !== 'pending') {
                return false;
            }

            $lockedReport->update([
                'status' => 'reviewed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Un report validé devient un signal fort pour la fiabilité du relevé concerné.
            if ($request->input('action') === 'reject_price') {
                $this->rejectPrice($lockedReport);
            }
            $this->syncPendingReports($lockedReport->itemPrice);

            return true;
        });

        if (! $processed) {
            return back()->with('success', 'Ce signalement avait déjà été traité.');
        }

        return back()->with('success', 'Signalement traité avec succès.');
    }

    public function dismiss(PriceReport $report)
    {
        if (! auth()->user()->canModerate()) {
            abort(403);
        }

        $dismissed = PriceReport::query()
            ->whereKey($report->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'dismissed',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

        if ($dismissed) {
            $this->syncPendingReports($report->itemPrice);
        }

        return back()->with('success', 'Signalement rejeté.');
    }

    private function rejectPrice(PriceReport $report): void
    {
        if (! $report->priceHistory) {
            $report->itemPrice->update(['status' => 'rejected']);

            return;
        }

        if ($report->priceHistory->created_by) {
            User::where('id', $report->priceHistory->created_by)
                ->increment('rejected_prices_count');
        }

        $this->trustService->rejectObservation($report->priceHistory);
    }

    private function syncPendingReports(ItemPrice $itemPrice): void
    {
        $pendingCount = PriceReport::query()
            ->where('item_price_id', $itemPrice->id)
            ->where('status', 'pending')
            ->count();

        $attributes = ['reports_count' => $pendingCount];
        if ($itemPrice->status === ItemPrice::STATUS_APPROVED
            && $pendingCount >= ItemPrice::REPORT_THRESHOLD) {
            $attributes['status'] = ItemPrice::STATUS_PENDING_REVIEW;
        } elseif ($itemPrice->status === ItemPrice::STATUS_PENDING_REVIEW
            && $pendingCount < ItemPrice::REPORT_THRESHOLD) {
            $attributes['status'] = ItemPrice::STATUS_APPROVED;
        }

        $itemPrice->update($attributes);
    }
}
