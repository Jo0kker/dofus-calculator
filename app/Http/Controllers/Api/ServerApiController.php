<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\Request;

class ServerApiController extends Controller
{
    /**
     * Get all available Dofus servers
     *
     * Returns a list of all available Dofus servers with their details.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $servers = Server::orderBy('name')->get();

        return response()->json([
            'data' => $servers->map(function ($server) {
                return [
                    'id' => $server->id,
                    'name' => $server->name,
                    'created_at' => $server->created_at,
                    'updated_at' => $server->updated_at,
                ];
            })
        ]);
    }

    /**
     * Get a specific server by ID
     *
     * Returns details for a specific Dofus server.
     *
     * @param Server $server
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Server $server)
    {
        return response()->json([
            'data' => [
                'id' => $server->id,
                'name' => $server->name,
                'created_at' => $server->created_at,
                'updated_at' => $server->updated_at,
            ]
        ]);
    }
}
