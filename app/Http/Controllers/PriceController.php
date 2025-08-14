<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\PriceHistory;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PriceController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'server_id' => 'required|exists:servers,id',
            'price' => 'required|integer|min:1|max:999999999',
        ]);
        
        DB::transaction(function () use ($validated) {
            $price = ItemPrice::updateOrCreate(
                [
                    'item_id' => $validated['item_id'],
                    'server_id' => $validated['server_id'],
                ],
                [
                    'price' => $validated['price'],
                    'created_by' => auth()->id(),
                    'status' => 'approved',
                    'reports_count' => 0,
                ]
            );
            
            PriceHistory::create([
                'item_id' => $validated['item_id'],
                'server_id' => $validated['server_id'],
                'price' => $validated['price'],
                'created_by' => auth()->id(),
            ]);
        });
        
        return back()->with('success', 'Prix mis à jour avec succès');
    }
    
    public function report(ItemPrice $itemPrice, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        
        $reported = $itemPrice->report(auth()->user(), $validated['reason'] ?? null);
        
        if (!$reported) {
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
        ]);
        
        DB::transaction(function () use ($validated) {
            foreach ($validated['prices'] as $priceData) {
                $price = ItemPrice::updateOrCreate(
                    [
                        'item_id' => $priceData['item_id'],
                        'server_id' => $validated['server_id'],
                    ],
                    [
                        'price' => $priceData['price'],
                        'created_by' => auth()->id(),
                        'status' => 'approved',
                        'reports_count' => 0,
                    ]
                );
                
                PriceHistory::create([
                    'item_id' => $priceData['item_id'],
                    'server_id' => $validated['server_id'],
                    'price' => $priceData['price'],
                    'created_by' => auth()->id(),
                ]);
            }
        });
        
        return back()->with('success', 'Prix mis à jour avec succès');
    }
}
