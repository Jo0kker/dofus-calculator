<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;

class ManageServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'servers:manage 
                            {action : list|activate|deactivate|set-type|set-order}
                            {server? : Server name or ID}
                            {value? : Value for set operations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage servers (activate/deactivate, set type, set order)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        
        switch ($action) {
            case 'list':
                $this->listServers();
                break;
            case 'activate':
                $this->toggleServer(true);
                break;
            case 'deactivate':
                $this->toggleServer(false);
                break;
            case 'set-type':
                $this->setServerType();
                break;
            case 'set-order':
                $this->setDisplayOrder();
                break;
            default:
                $this->error("Unknown action: {$action}");
        }
    }
    
    private function listServers()
    {
        $servers = Server::orderBy('display_order')->orderBy('name')->get();
        
        $headers = ['ID', 'Name', 'Type', 'Temporary', 'Active', 'Order'];
        $data = $servers->map(function ($server) {
            return [
                $server->id,
                $server->name,
                $server->type,
                $server->is_temporary ? 'Yes' : 'No',
                $server->is_active ? 'Yes' : 'No',
                $server->display_order,
            ];
        })->toArray();
        
        $this->table($headers, $data);
    }
    
    private function toggleServer($activate)
    {
        $server = $this->findServer();
        if (!$server) return;
        
        $server->is_active = $activate;
        $server->save();
        
        $status = $activate ? 'activated' : 'deactivated';
        $this->info("Server '{$server->name}' has been {$status}.");
    }
    
    private function setServerType()
    {
        $server = $this->findServer();
        if (!$server) return;
        
        $type = $this->argument('value');
        if (!in_array($type, ['classic', 'heroic', 'epic', 'event'])) {
            $this->error("Invalid type. Must be one of: classic, heroic, epic, event");
            return;
        }
        
        $server->type = $type;
        $server->is_temporary = ($type === 'event');
        $server->save();
        
        $this->info("Server '{$server->name}' type set to '{$type}'.");
    }
    
    private function setDisplayOrder()
    {
        $server = $this->findServer();
        if (!$server) return;
        
        $order = (int) $this->argument('value');
        $server->display_order = $order;
        $server->save();
        
        $this->info("Server '{$server->name}' display order set to {$order}.");
    }
    
    private function findServer()
    {
        $identifier = $this->argument('server');
        if (!$identifier) {
            $this->error("Server name or ID required for this action.");
            return null;
        }
        
        $server = Server::where('name', $identifier)
            ->orWhere('id', $identifier)
            ->first();
            
        if (!$server) {
            $this->error("Server '{$identifier}' not found.");
            return null;
        }
        
        return $server;
    }
}