<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'price_reliability_score')) {
                $table->unsignedSmallInteger('price_reliability_score')->default(60);
            }
            if (! Schema::hasColumn('users', 'price_reliability_samples')) {
                $table->unsignedInteger('price_reliability_samples')->default(0);
            }
            if (! Schema::hasColumn('users', 'price_reliability_updated_at')) {
                $table->timestamp('price_reliability_updated_at')->nullable();
            }
        });

        Schema::table('item_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('item_prices', 'confidence_score')) {
                $table->unsignedSmallInteger('confidence_score')->default(0);
            }
            if (! Schema::hasColumn('item_prices', 'confidence_level')) {
                $table->string('confidence_level', 20)->default('low');
            }
            if (! Schema::hasColumn('item_prices', 'recent_observations_count')) {
                $table->unsignedInteger('recent_observations_count')->default(0);
            }
            if (! Schema::hasColumn('item_prices', 'recent_contributors_count')) {
                $table->unsignedInteger('recent_contributors_count')->default(0);
            }
            if (! Schema::hasColumn('item_prices', 'confidence_details')) {
                $table->json('confidence_details')->nullable();
            }
            if (! Schema::hasColumn('item_prices', 'confidence_computed_at')) {
                $table->timestamp('confidence_computed_at')->nullable();
            }
            if (! Schema::hasColumn('item_prices', 'confidence_version')) {
                $table->unsignedSmallInteger('confidence_version')->default(1);
            }
        });

        Schema::table('price_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('price_histories', 'plausibility_score')) {
                $table->unsignedSmallInteger('plausibility_score')->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'reliability_snapshot')) {
                $table->unsignedSmallInteger('reliability_snapshot')->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'consensus_deviation')) {
                $table->decimal('consensus_deviation', 10, 6)->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'influence_weight')) {
                $table->decimal('influence_weight', 10, 6)->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'evaluation_score')) {
                $table->unsignedSmallInteger('evaluation_score')->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'evaluation_weight')) {
                $table->decimal('evaluation_weight', 8, 4)->default(0);
            }
            if (! Schema::hasColumn('price_histories', 'evaluated_at')) {
                $table->timestamp('evaluated_at')->nullable();
            }
            if (! Schema::hasColumn('price_histories', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('price_histories', function (Blueprint $table) {
            $columns = collect([
                'plausibility_score',
                'reliability_snapshot',
                'consensus_deviation',
                'influence_weight',
                'evaluation_score',
                'evaluation_weight',
                'evaluated_at',
                'rejected_at',
            ])->filter(fn (string $column) => Schema::hasColumn('price_histories', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $columns = collect([
                'confidence_score',
                'confidence_level',
                'recent_observations_count',
                'recent_contributors_count',
                'confidence_details',
                'confidence_computed_at',
                'confidence_version',
            ])->filter(fn (string $column) => Schema::hasColumn('item_prices', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $columns = collect([
                'price_reliability_score',
                'price_reliability_samples',
                'price_reliability_updated_at',
            ])->filter(fn (string $column) => Schema::hasColumn('users', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
