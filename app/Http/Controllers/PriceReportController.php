<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use App\Models\PriceReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceReportController extends Controller
{
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

        // Trouver l'entrée PriceHistory qui correspond au prix actuel signalé
        $currentPriceHistory = \App\Models\PriceHistory::where('item_id', $itemPrice->item_id)
            ->where('server_id', $itemPrice->server_id)
            ->where('price', $itemPrice->price)
            ->orderBy('created_at', 'desc')
            ->first();

        PriceReport::create([
            'item_price_id' => $itemPrice->id,
            'price_history_id' => $currentPriceHistory?->id,
            'reported_by' => auth()->id(),
            'comment' => $request->comment,
        ]);

        return back()->with('success', 'Prix signalé avec succès. Merci pour votre contribution !');
    }

    public function index(Request $request)
    {
        // Vérifier que l'utilisateur peut modérer
        if (!auth()->user()->canModerate()) {
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
        if (!auth()->user()->canModerate()) {
            abort(403);
        }

        $report->update([
            'status' => 'reviewed',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Si le report est approuvé, on rejette le prix et on restaure le prix précédent si nécessaire
        if ($request->input('action') === 'reject_price') {
            $this->rejectPrice($report);
        }

        return back()->with('success', 'Signalement traité avec succès.');
    }

    public function dismiss(PriceReport $report)
    {
        if (!auth()->user()->canModerate()) {
            abort(403);
        }

        $report->update([
            'status' => 'dismissed',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Signalement rejeté.');
    }

    private function rejectPrice($report)
    {
        // Le $report contient la relation vers le prix exact qui a été signalé
        if (!$report->priceHistory) {
            // Fallback si pas de PriceHistory
            $report->itemPrice->update(['status' => 'rejected']);
            return;
        }

        // Incrémenter le compteur pour l'utilisateur qui avait soumis ce prix spécifique
        if ($report->priceHistory->created_by) {
            \App\Models\User::where('id', $report->priceHistory->created_by)
                ->increment('rejected_prices_count');
        }

        // Chercher le prix le plus récent qui n'est PAS celui qui a été signalé
        // ET qui n'est pas non plus un prix qui a été signalé dans un autre rapport traité
        $excludedPriceHistoryIds = PriceReport::where('item_price_id', $report->itemPrice->id)
            ->whereIn('status', ['reviewed', 'dismissed'])
            ->whereNotNull('price_history_id')
            ->pluck('price_history_id')
            ->push($report->priceHistory->id) // Inclure aussi le prix actuel signalé
            ->toArray();

        $alternativePriceHistory = \App\Models\PriceHistory::where('item_id', $report->itemPrice->item_id)
            ->where('server_id', $report->itemPrice->server_id)
            ->whereNotIn('id', $excludedPriceHistoryIds) // Exclure tous les prix déjà signalés
            ->orderBy('created_at', 'desc')
            ->first();

        if ($alternativePriceHistory) {
            // Restaurer le prix alternatif dans ItemPrice (le plus récent qui n'est pas celui signalé)
            $report->itemPrice->update([
                'price' => $alternativePriceHistory->price,
                'status' => 'approved',
                'created_by' => $alternativePriceHistory->created_by,
            ]);
        } else {
            // Pas d'autre prix, on marque juste comme rejeté
            $report->itemPrice->update(['status' => 'rejected']);
        }
    }
}