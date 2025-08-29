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
            $table->dropColumn('reason');
        });
        
        Schema::table('price_reports', function (Blueprint $table) {
            // Rendre le commentaire obligatoire au lieu de la raison
            $table->text('comment')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_reports', function (Blueprint $table) {
            $table->text('comment')->nullable()->change();
        });
        
        Schema::table('price_reports', function (Blueprint $table) {
            $table->enum('reason', ['too_high', 'too_low', 'outdated', 'fake', 'other'])->default('other')->after('reported_by');
        });
    }
};
