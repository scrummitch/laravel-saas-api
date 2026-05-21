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
        // Account
        Schema::table('account_agents', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
        Schema::table('account_customers', function (Blueprint $table) {
            $table->string('external_id')->nullable()->collation('utf8mb4_bin');

            $table->unique(['external_id', 'organization_id']);
        });

        Schema::table('usage_metrics', function (Blueprint $table) {
            $table->renameColumn('key', 'event_name');
        });

        // Billing
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('min_amount');
        });
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('properties');
        });

        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->string('lookup_key')->nullable()->collation('utf8mb4_bin');
            $table->unique(['lookup_key', 'organization_id']);
        });

        Schema::drop('events');
        Schema::drop('event_definitions');

        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->string('allowance')->nullable()->change();
            $table->string('unit')->nullable()->change();
            $table->string('reset_period')->nullable()->change();
        });

        Schema::dropIfExists('metrics');
        Schema::dropIfExists('metric_dimensions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Account
        Schema::table('account_agents', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable();
        });
        Schema::table('account_customers', function (Blueprint $table) {
            $table->dropColumn('external_id');

            $table->dropUnique(['external_id', 'organization_id']);
        });

        Schema::table('usage_metrics', function (Blueprint $table) {
            $table->renameColumn('event_name', 'key');
        });

        // Billing
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->nullable();
        });
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->json('properties')->nullable();
        });

        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->dropColumn('lookup_key');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->collation('utf8mb4_bin');
            $table->string('description', 256)->nullable();
            $table->timestamps();
        });

        Schema::create('event_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id');
            $table->string('field_name', 128)->collation('utf8mb4_bin');
            $table->string('display_name', 128)->nullable();
            $table->string('unit', 64)->nullable();
            $table->string('reset_period', 64)->nullable();
            $table->string('allowance', 64)->nullable();
            $table->timestamps();
        });

        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->string('allowance')->nullable(false)->change();
            $table->string('unit')->nullable(false)->change();
            $table->string('reset_period')->nullable(false)->change();
        });
    }
};
