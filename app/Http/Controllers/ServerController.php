<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;

class ServerController extends Controller
{
    public function select(Request $request)
    {
        $validated = $request->validate([
            'server_id' => 'required|exists:servers,id',
        ]);
        
        $server = Server::find($validated['server_id']);
        
        // Stocker en session
        session(['selected_server_id' => $server->id]);
        
        // Si l'utilisateur est connecté, on peut aussi stocker en base de données
        if (auth()->check()) {
            auth()->user()->update(['selected_server_id' => $server->id]);
        }
        
        return back();
    }
}
