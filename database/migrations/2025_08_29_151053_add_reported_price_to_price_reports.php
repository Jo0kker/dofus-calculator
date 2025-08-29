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
            $table->foreignId('price_history_id')->after('item_price_id')->nullable()->constrained('price_histories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_reports', function (Blueprint $table) {
            $table->dropForeign(['price_history_id']);
            $table->dropColumn('price_history_id');
        });
    }
};
