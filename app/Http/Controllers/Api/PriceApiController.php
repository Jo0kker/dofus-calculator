<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\PriceSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceApiController extends Controller
{
    public function __construct(private readonly PriceSubmissionService $priceSubmissionService) {}

    /**
     * Update item prices
     *
     * Updates prices for one or multiple items. Requires 'write' permission.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'prices' => 'required|array|min:1|max:500',
            'prices.*.item_id' => 'required|exists:items,id',
            'prices.*.price' => 'required|integer|min:1|max:999999999',
        ]);

        $serverId = $request->input('server_id');
        $updatedPrices = [];

        DB::beginTransaction();
        try {
            $prices = collect($request->prices)->keyBy('item_id')->values();
            foreach ($prices as $priceData) {
                $item = Item::find($priceData['item_id']);

                $price = $this->priceSubmissionService->submitCommunityPrice(
                    $request->user(),
                    $item->id,
                    $serverId,
                    $priceData['price'],
                );

                $updatedPrices[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'server_id' => $serverId,
                    'submitted_price' => $priceData['price'],
                    'price' => $price->price,
                    'status' => $price->status,
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Prices updated successfully',
                'server_id' => $serverId,
                'updated_count' => count($updatedPrices),
                'updated_prices' => $updatedPrices,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error updating prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update item prices
     *
     * Updates prices for multiple items using their names. Requires 'write' permission.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'prices' => 'required|array|min:1|max:500',
            'prices.*.item_name' => 'required|string',
            'prices.*.price' => 'required|integer|min:1|max:999999999',
        ]);

        $serverId = $request->input('server_id');
        $updatedPrices = [];
        $notFound = [];

        DB::beginTransaction();
        try {
            $resolvedPrices = [];
            foreach ($request->prices as $priceData) {
                $itemName = trim($priceData['item_name']);
                $item = Item::whereRaw('LOWER(name) = LOWER(?)', [$itemName])->first();

                if (! $item) {
                    $notFound[] = $itemName;

                    continue;
                }

                $resolvedPrices[$item->id] = [
                    'item' => $item,
                    'price' => $priceData['price'],
                ];
            }

            foreach ($resolvedPrices as $resolvedPrice) {
                $item = $resolvedPrice['item'];

                $price = $this->priceSubmissionService->submitCommunityPrice(
                    $request->user(),
                    $item->id,
                    $serverId,
                    $resolvedPrice['price'],
                );

                $updatedPrices[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'server_id' => $serverId,
                    'submitted_price' => $resolvedPrice['price'],
                    'price' => $price->price,
                    'status' => $price->status,
                ];
            }

            DB::commit();

            return response()->json([
                'message' => 'Prices updated successfully',
                'server_id' => $serverId,
                'updated_count' => count($updatedPrices),
                'not_found_count' => count($notFound),
                'updated_prices' => $updatedPrices,
                'not_found_items' => $notFound,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error updating prices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
