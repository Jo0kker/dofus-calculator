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

        DB::transaction(function () use ($request, $validated) {
            if ($validated['price_mode'] === 'personal') {
                $this->priceSubmissionService->submitPersonalPrice(
                    $request->user(),
                    $validated['item_id'],
                    $validated['server_id'],
                    $validated['price'],
                );
            } else {
                $this->priceSubmissionService->submitCommunityPrice(
                    $request->user(),
                    $validated['item_id'],
                    $validated['server_id'],
                    $validated['price'],
                );
            }

        });

        $message = $validated['price_mode'] === 'personal'
            ? 'Prix personnel enregistré avec succès'
            : 'Prix communautaire mis à jour avec succès';

        return back()->with('success', $message);
    }

    public function updatePreference(Request $request)
    {
        $validated = $request->validate([
            'price_mode' => ['required', Rule::in(['community', 'personal'])],
        ]);

        $this->priceSubmissionService->rememberMode($request->user(), $validated['price_mode']);

        return back();
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
            'prices' => 'required|array',
            'prices.*.item_id' => 'required|exists:items,id',
            'prices.*.price' => 'required|integer|min:1|max:999999999',
            'price_mode' => ['sometimes', Rule::in(['community', 'personal'])],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $mode = $validated['price_mode'] ?? 'community';

            foreach ($validated['prices'] as $priceData) {
                if ($mode === 'personal') {
                    $this->priceSubmissionService->submitPersonalPrice(
                        $request->user(),
                        $priceData['item_id'],
                        $validated['server_id'],
                        $priceData['price'],
                    );
                } else {
                    $this->priceSubmissionService->submitCommunityPrice(
                        $request->user(),
                        $priceData['item_id'],
                        $validated['server_id'],
                        $priceData['price'],
                    );
                }
            }

        });

        return back()->with('success', 'Prix mis à jour avec succès');
    }
}
