<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use App\Services\PriceSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PriceController extends Controller
{
    public function __construct(private readonly PriceSubmissionService $priceSubmissionService) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'server_id' => 'required|exists:servers,id',
            'price' => 'required|integer|min:1|max:999999999',
            'price_mode' => ['required', Rule::in(['community', 'personal'])],
        ]);

        $result = DB::transaction(function () use ($request, $validated) {
            if ($validated['price_mode'] === 'personal') {
                $this->priceSubmissionService->submitPersonalPrice(
                    $request->user(),
                    $validated['item_id'],
                    $validated['server_id'],
                    $validated['price'],
                );

                return null;
            }

            return $this->priceSubmissionService->submitCommunityPriceWithResult(
                $request->user(),
                $validated['item_id'],
                $validated['server_id'],
                $validated['price'],
            );
        });

        $message = match (true) {
            $validated['price_mode'] === 'personal' => 'Prix personnel enregistré avec succès.',
            ! $result['recorded'] => 'Prix identique à votre dernier relevé : aucun doublon créé.',
            default => sprintf(
                'Prix communautaire mis à jour : %s K.',
                number_format($result['price']->price, 0, ',', ' '),
            ),
        };

        return back()->with('success', $message);
    }

    public function updateItemPreference(Request $request)
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'server_id' => ['required', 'exists:servers,id'],
            'price_mode' => ['nullable', Rule::in(['community', 'personal'])],
        ]);

        $this->priceSubmissionService->rememberItemMode(
            $request->user(),
            $validated['item_id'],
            $validated['server_id'],
            $validated['price_mode'] ?? null,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'price_mode' => $validated['price_mode'] === 'personal' ? 'personal' : 'community',
            ]);
        }

        return back();
    }

    public function report(ItemPrice $itemPrice, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reported = $itemPrice->report(auth()->user(), $validated['reason'] ?? null);

        if (! $reported) {
            return back()->with('error', 'Vous avez déjà signalé ce prix');
        }

        return back()->with('success', 'Prix signalé avec succès');
    }

    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'prices' => 'required|array|min:1|max:500',
            'prices.*.item_id' => 'required|exists:items,id',
            'prices.*.price' => 'required|integer|min:1|max:999999999',
            'price_mode' => ['sometimes', Rule::in(['community', 'personal'])],
        ]);

        $stats = DB::transaction(function () use ($request, $validated) {
            $mode = $validated['price_mode'] ?? 'community';
            $prices = collect($validated['prices'])->keyBy('item_id')->values();
            $recorded = 0;
            $ignored = 0;

            foreach ($prices as $priceData) {
                if ($mode === 'personal') {
                    $this->priceSubmissionService->submitPersonalPrice(
                        $request->user(),
                        $priceData['item_id'],
                        $validated['server_id'],
                        $priceData['price'],
                    );
                    $recorded++;

                    continue;
                }

                $result = $this->priceSubmissionService->submitCommunityPriceWithResult(
                    $request->user(),
                    $priceData['item_id'],
                    $validated['server_id'],
                    $priceData['price'],
                );
                $result['recorded'] ? $recorded++ : $ignored++;
            }

            return compact('recorded', 'ignored');
        });

        $message = $stats['ignored'] > 0
            ? sprintf('%d relevé(s) enregistré(s), %d doublon(s) ignoré(s).', $stats['recorded'], $stats['ignored'])
            : sprintf('%d prix mis à jour avec succès.', $stats['recorded']);

        return back()->with('success', $message);
    }
}
