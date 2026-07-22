<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('price_contributions_count')->default(0)->after('rejected_prices_count');
        });

        Schema::create('personal_item_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('price');
            $table->timestamps();

            $table->unique(['user_id', 'server_id', 'item_id'], 'personal_item_price_unique');
            $table->index(['server_id', 'item_id'], 'personal_item_price_lookup');
        });

        Schema::create('user_item_price_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 20);
            $table->timestamps();

            $table->unique(['user_id', 'server_id', 'item_id'], 'user_item_price_pref_unique');
            $table->index(['server_id', 'item_id'], 'user_item_price_pref_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_item_price_preferences');
        Schema::dropIfExists('personal_item_prices');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('price_contributions_count');
        });
    }
};
