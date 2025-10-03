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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('token_name')->nullable(); // Nom du token utilisé
            $table->string('endpoint'); // GET /api/items, POST /api/prices, etc.
            $table->string('method', 10); // GET, POST, PUT, DELETE
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_data')->nullable(); // Paramètres de la requête
            $table->integer('response_status')->nullable(); // 200, 404, 500, etc.
            $table->integer('items_affected')->default(0); // Nombre d'items retournés ou modifiés
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index('user_id');
            $table->index('endpoint');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']); // Pour compter les appels par utilisateur
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
