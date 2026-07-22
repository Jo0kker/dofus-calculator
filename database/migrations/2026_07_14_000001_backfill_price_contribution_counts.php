<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensurePricePreferenceSchemaExists();

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $historyCount = DB::table('price_histories')
                        ->where('created_by', $user->id)
                        ->count();

                    $legacyApiCount = DB::table('api_logs')
                        ->where('user_id', $user->id)
                        ->where('method', 'POST')
                        ->where('endpoint', 'like', '%prices%')
                        ->whereBetween('response_status', [200, 299])
                        ->get(['items_affected', 'request_data'])
                        ->sum(function ($log) {
                            if ($log->items_affected > 0) {
                                return $log->items_affected;
                            }

                            $requestData = json_decode($log->request_data ?? '{}', true);

                            return is_array($requestData['prices'] ?? null)
                                ? count($requestData['prices'])
                                : 0;
                        });

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'price_contributions_count' => $historyCount + $legacyApiCount,
                        ]);
                }
            });
    }

    private function ensurePricePreferenceSchemaExists(): void
    {
        if (! Schema::hasColumn('users', 'price_contributions_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('price_contributions_count')
                    ->default(0)
                    ->after('rejected_prices_count');
            });
        }

        if (! Schema::hasTable('user_item_price_preferences')) {
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
    }

    public function down(): void
    {
        DB::table('users')->update(['price_contributions_count' => 0]);
    }
};
