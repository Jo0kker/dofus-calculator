<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('ApiTokens/Index', [
            'tokens' => $request->user()->tokens()->orderBy('created_at', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:read,write']
        ]);

        $token = $request->user()->createToken(
            $request->name,
            $request->abilities ?? ['read']
        );

        return Inertia::render('ApiTokens/Index', [
            'tokens' => $request->user()->tokens()->orderBy('created_at', 'desc')->get(),
            'newToken' => $token->plainTextToken
        ]);
    }

    public function destroy(Request $request, $tokenId)
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('flash', [
            'message' => 'Token supprimé avec succès.'
        ]);
    }
}