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
            $table->integer('dofusdb_id')->nullable()->unique()->after('id');
            // type, is_temporary, display_order existent déjà
            $table->string('language', 2)->default('fr')->after('display_order');
            $table->boolean('mono_account')->default(false)->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'dofusdb_id',
                'language',
                'mono_account'
            ]);
        });
    }
};
