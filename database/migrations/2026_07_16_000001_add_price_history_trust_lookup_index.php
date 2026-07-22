<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('price_histories', 'price_histories_trust_lookup')) {
            Schema::table('price_histories', function (Blueprint $table) {
                $table->index(
                    ['server_id', 'item_id', 'created_by', 'created_at'],
                    'price_histories_trust_lookup',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('price_histories', 'price_histories_trust_lookup')) {
            Schema::table('price_histories', function (Blueprint $table) {
                $table->dropIndex('price_histories_trust_lookup');
            });
        }
    }
};
