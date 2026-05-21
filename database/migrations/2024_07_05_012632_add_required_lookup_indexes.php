<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // delete dupes before we add the index
        DB::table('account_customers as ac1')
            ->join('account_customers as ac2', function ($join) {
                $join->on('ac1.reference_id', '=', 'ac2.reference_id')
                    ->on('ac1.billing_provider_id', '=', 'ac2.billing_provider_id')
                    ->on('ac1.id', '>', 'ac2.id');
            })
            ->delete();

        Schema::table('account_customers', function (Blueprint $table) {
            $table->unique(['reference_id', 'billing_provider_id']);
        });

        Schema::table('account_agents', function (Blueprint $table) {
            $table->index(['lookup_key', 'organization_id']);
        });

        Schema::table('billing_providers', function (Blueprint $table) {
            $table->index(['lookup_key', 'organization_id']);
        });

        Schema::table('catalog_features', function (Blueprint $table) {
            $table->index(['lookup_key', 'organization_id']);
        });

        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->index(['lookup_key', 'organization_id']);
        });

        // we dont need these tables anymore
        $tablesToDelete = [
            'convert_prompts',
            'convert_checkout_mutations',
            'billing_subscription_phases',
            'metric_dimensions',
            'convert_placements',
            'connections',
            'client_sessions',
        ];
        foreach ($tablesToDelete as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['media_assets', 'convert_paywalls', 'clients', 'catalog_feature_sets'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->index(['organization_id', 'ulid']);
            });
        }

        // these two tables need ulid index because they allow public lookups
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->index('ulid');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->index('ulid');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
