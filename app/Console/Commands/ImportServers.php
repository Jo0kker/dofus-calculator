<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportServers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dofus:import-servers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import/Update Dofus servers list';

    /**
     * Get HTTP client with proper headers for DofusDB API
     */
    private function getHttpClient()
    {
        return \Illuminate\Support\Facades\Http::withHeaders([
            'Referer' => config('app.url', 'https://dofus-calculator.fr'),
            'User-Agent' => 'Dofus-Calculator/1.0 (Compatible; Laravel)',
        ]);
    }
    
    /**
     * Récupérer la liste des serveurs depuis DofusDB API
     */
    private function fetchServersFromAPI(): array
    {
        $response = $this->getHttpClient()->get('https://api.dofusdb.fr/servers', [
            '$limit' => 100,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Failed to fetch servers from DofusDB API');
        }
        
        $serversData = $response->json('data', []);
        $servers = [];
        
        foreach ($serversData as $server) {
            $name = $server['name']['fr'] ?? $server['name']['en'] ?? 'Unknown';
            
            // Déterminer le type de serveur basé sur gameTypeId
            $type = match($server['gameTypeId']) {
                0 => 'classic',    // Serveur classique
                1 => 'heroic',     // Serveur héroïque  
                2 => 'event',      // Serveur PvP/Kolossium -> traité comme event car pas supporté
                3 => 'event',      // Serveur événement
                4 => 'epic',       // Serveur épique
                default => 'classic'
            };
            
            // Filtrer certains serveurs
            $isTemporary = $server['gameTypeId'] == 3 ||  // Tournois
                          $server['gameTypeId'] == 2 ||  // PvP/Kolossium
                          str_contains($name, 'Beta') ||
                          str_contains($name, 'Test') ||
                          str_contains($name, 'Tournaments') ||
                          str_contains($name, 'Kidibom') ||
                          str_contains($name, 'Tynril') ||
                          str_contains($name, 'Shukrute');
                          
            $isActive = !$isTemporary;
            
            // Ordre par type et nom
            $order = match($type) {
                'classic' => 100,
                'heroic' => 200,
                'epic' => 300,
                'pvp' => 400,
                'event' => 500,
                default => 600
            } + $server['id'];
            
            $servers[] = [
                'dofusdb_id' => $server['id'],
                'name' => $name,
                'type' => $type,
                'order' => $order,
                'active' => $isActive,
                'temporary' => $isTemporary,
                'language' => $server['language'] ?? 'fr',
                'mono_account' => $server['monoAccount'] ?? false,
            ];
        }
        
        return $servers;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching servers from DofusDB API...');
        
        try {
            $servers = $this->fetchServersFromAPI();
        } catch (\Exception $e) {
            $this->error('Failed to fetch servers: ' . $e->getMessage());
            return 1;
        }
        
        $this->info('Importing/Updating ' . count($servers) . ' Dofus servers...');
        
        $imported = 0;
        $updated = 0;
        
        foreach ($servers as $serverData) {
            $server = Server::updateOrCreate(
                ['name' => $serverData['name']], // Utiliser le nom comme clé unique
                [
                    'dofusdb_id' => $serverData['dofusdb_id'],
                    'slug' => Str::slug($serverData['name']),
                    'type' => $serverData['type'],
                    'is_temporary' => $serverData['temporary'],
                    'is_active' => $serverData['active'],
                    'display_order' => $serverData['order'],
                    'language' => $serverData['language'],
                    'mono_account' => $serverData['mono_account'],
                ]
            );
            
            if ($server->wasRecentlyCreated) {
                $imported++;
                $this->line("✓ Imported: {$serverData['name']} ({$serverData['type']})");
            } else {
                $updated++;
                $this->line("↻ Updated: {$serverData['name']} ({$serverData['type']})");
            }
        }
        
        $this->info('');
        $this->info("Import completed!");
        $this->info("- Servers imported: $imported");
        $this->info("- Servers updated: $updated");
        
        // Afficher les serveurs actifs
        $activeServers = Server::where('is_active', true)
            ->where('is_temporary', false)
            ->orderBy('display_order')
            ->get();
            
        $this->info('');
        $this->info('Active servers available for selection:');
        foreach ($activeServers as $server) {
            $type = ucfirst($server->type);
            $this->line("  - {$server->name} ($type)");
        }
        
        // Afficher les serveurs inactifs/temporaires
        $inactiveServers = Server::where(function($q) {
                $q->where('is_active', false)->orWhere('is_temporary', true);
            })
            ->orderBy('display_order')
            ->get();
            
        if ($inactiveServers->count() > 0) {
            $this->info('');
            $this->info('Inactive/Temporary servers:');
            foreach ($inactiveServers as $server) {
                $type = ucfirst($server->type);
                $status = $server->is_active ? 'temporary' : 'inactive';
                $this->line("  - {$server->name} ($type) [$status]");
            }
            
            $this->info('');
            $this->comment('Use php artisan servers:manage to activate/deactivate servers.');
        }
        
        return 0;
    }
}
