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
        Schema::table('servers', function (Blueprint $table) {
            $table->enum('type', ['classic', 'heroic', 'epic', 'event'])->default('classic')->after('slug');
            $table->boolean('is_temporary')->default(false)->after('type');
            $table->integer('display_order')->default(999)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_temporary', 'display_order']);
        });
    }
};