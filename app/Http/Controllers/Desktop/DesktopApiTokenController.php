<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DesktopApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureDesktopMode($request);

        return response()->json([
            'tokens' => $this->tokens($request),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureDesktopMode($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'in:read,write'],
        ]);

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'] ?? ['read'],
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'tokens' => $this->tokens($request),
        ], 201);
    }

    public function destroy(Request $request, string $tokenId)
    {
        $this->ensureDesktopMode($request);

        $request->user()->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'tokens' => $this->tokens($request),
        ]);
    }

    private function ensureDesktopMode(Request $request): void
    {
        abort_unless($request->user()?->interface_mode === 'desktop', 403);
    }

    private function tokens(Request $request)
    {
        return $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'created_at' => $token->created_at?->toISOString(),
                'last_used_at' => $token->last_used_at?->toISOString(),
            ])
            ->values();
    }
}
