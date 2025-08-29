<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_reports', function (Blueprint $table) {
            $table->dropUnique(['item_price_id', 'reported_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_reports', function (Blueprint $table) {
            $table->unique(['item_price_id', 'reported_by']);
        });
    }
};
