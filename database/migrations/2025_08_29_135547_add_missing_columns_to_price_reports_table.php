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
            // Modifier la colonne reason pour avoir un enum au lieu de text
            $table->dropColumn('reason');
        });
        
        Schema::table('price_reports', function (Blueprint $table) {
            $table->enum('reason', ['too_high', 'too_low', 'outdated', 'fake', 'other'])->default('other')->after('reported_by');
            $table->text('comment')->nullable()->after('reason');
            $table->enum('status', ['pending', 'reviewed', 'dismissed'])->default('pending')->after('comment');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_reports', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['comment', 'status', 'reviewed_by', 'reviewed_at']);
        });
        
        Schema::table('price_reports', function (Blueprint $table) {
            $table->dropColumn('reason');
        });
        
        Schema::table('price_reports', function (Blueprint $table) {
            $table->text('reason')->nullable();
        });
    }
};
