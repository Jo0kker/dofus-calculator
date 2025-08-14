<?php

namespace App\Http\Controllers;

use App\Models\ItemPrice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ModerationController extends Controller
{
    public function index(Request $request)
    {
        $query = ItemPrice::with(['item', 'server', 'user', 'reports.reporter'])
            ->where('status', 'pending_review')
            ->orderBy('reports_count', 'desc')
            ->orderBy('updated_at', 'desc');
        
        $prices = $query->paginate(20);
        
        return Inertia::render('Moderation/Index', [
            'prices' => $prices,
        ]);
    }
    
    public function approve(ItemPrice $itemPrice)
    {
        $itemPrice->approve();
        
        return back()->with('success', 'Prix approuvé');
    }
    
    public function reject(ItemPrice $itemPrice)
    {
        $itemPrice->reject();
        
        return back()->with('success', 'Prix rejeté');
    }
}
