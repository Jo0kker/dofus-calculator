<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceApiController extends Controller
{
    /**
     * Update item prices
     *
     * Updates prices for one or multiple items. Requires 'write' permission.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'prices' => 'required|array',
            'prices.*.item_id' => 'required|exists:items,id',
            'prices.*.price' => 'required|integer|min:0',
        ]);

        $serverId = $request->input('server_id');
        $updatedPrices = [];

        DB::beginTransaction();
        try {
            foreach ($request->prices as $priceData) {
                $item = Item::find($priceData['item_id']);

                $price = ItemPrice::updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'server_id' => $serverId,
                    ],
                    [
                        'price' => $priceData['price'],
                        'created_by' => $request->user()->id,
                        'status' => 'approved', // API prices are auto-approved like web prices
                    ]
                );

                $updatedPrices[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'server_id' => $serverId,
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
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update item prices
     *
     * Updates prices for multiple items using their names. Requires 'write' permission.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'server_id' => 'required|exists:servers,id',
            'prices' => 'required|array',
            'prices.*.item_name' => 'required|string',
            'prices.*.price' => 'required|integer|min:0',
        ]);

        $serverId = $request->input('server_id');
        $updatedPrices = [];
        $notFound = [];

        DB::beginTransaction();
        try {
            foreach ($request->prices as $priceData) {
                $item = Item::where('name', 'like', $priceData['item_name'])->first();

                if (!$item) {
                    $notFound[] = $priceData['item_name'];
                    continue;
                }

                $price = ItemPrice::updateOrCreate(
                    [
                        'item_id' => $item->id,
                        'server_id' => $serverId,
                    ],
                    [
                        'price' => $priceData['price'],
                        'created_by' => $request->user()->id,
                        'status' => 'approved', // API prices are auto-approved like web prices
                    ]
                );

                $updatedPrices[] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'server_id' => $serverId,
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
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
