<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $servers = [
            'Draconiros',
            'HellMina',
            'Imagiro',
            'Orukam',
            'Ombre',
            'Oto-Mustam',
            'Rubilax',
            'Pandore',
            'Ush',
            'Julith',
            'Nidas',
            'Merkator',
            'Furye',
            'Brumen',
            'Ilyzaelle',
            'Jahash',
            'Tal Kasha',
            'Tylezia',
            'Mériana',
            'Atcham',
            'Allister',
            'Agride',
            'Djaul',
            'Enutrof',
            'Henual',
            'Nabur',
            'Jorbak',
            'Menalt',
            'Ulette',
            'Hyrkul',
            'Silvosse',
            'Domen',
            'Pouchecot',
            'Ily',
            'Eratz',
            'Thanatena',
            'Herdegrize',
            'Dodge',
            'Grandapan'
        ];
        
        foreach ($servers as $serverName) {
            Server::firstOrCreate(
                ['name' => $serverName],
                [
                    'slug' => Str::slug($serverName),
                    'is_active' => true,
                ]
            );
        }
    }
}
