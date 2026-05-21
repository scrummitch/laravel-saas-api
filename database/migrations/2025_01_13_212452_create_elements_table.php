<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\Ulid;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->bigInteger('migrate_element_id')->nullable();
            $table->bigInteger('migrate_scenario_id')->nullable();
        });

        Schema::table('intel_activities', function (Blueprint $table) {
            $table->bigInteger('agent_id')->after('collector_id')->nullable();
            $table->bigInteger('migrate_session_id')->nullable();
        });

        Schema::table('intel_scenarios', function (Blueprint $table) {
            //flow_id
            //lookup_key
            //scheme_id
            //conditions
            //current_state
            $table->bigInteger('organization_id')->nullable();
            $table->bigInteger('element_id')->nullable();
            $table->string('display_name', 128);
            $table->string('intent', 32)->nullable();
            $table->jsonb('properties')->nullable();
//            $table->jsonb('bundle_rules')->nullable();
            $table->string('renew_interval', 8)->nullable();
            $table->unique(['organization_id', 'lookup_key']);
        });

        Schema::create('convert_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('scenario_id');
            $table->morphs('purchasable', 'purchasable_morph_index');
            $table->timestamps();
            $table->unique(['scenario_id', 'purchasable_id', 'purchasable_type'], 'unique_bundle_item');
        });

        Schema::drop('convert_elements');

        Schema::create('convert_elements', function (Blueprint $table) {
            //current_state
            //insertion_type
            //insertion_rules
            $table->id();
            $table->bigInteger('organization_id');
            $table->string('lookup_key', 64);
            $table->string('display_name')->nullable();

            $table->tinyInteger('type');
            $table->tinyInteger('mode'); // tracked, managed

            $table->jsonb('properties')->nullable();
            $table->jsonb('view')->nullable();
            $table->jsonb('template')->nullable();
            $table->jsonb('conditions')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'lookup_key']);
        });

        Schema::rename('intel_transactions', 'store_purchases');

        // upgrade transactions
        Schema::table('store_purchases', function (Blueprint $table) {
            //activity_id
            //provider_name -> store_name
            //provider_id   -> store_transaction_id
            //completed_at ? nullable

            $table->bigInteger('organization_id');
            $table->bigInteger('customer_id')->nullable();
            $table->bigInteger('activity_id')->nullable()->change();
            $table->bigInteger('billing_provider_id');
            $table->bigInteger('payment_method_id')->nullable();

            $table->string('current_state', 32)->default(\App\Models\Convert\CheckoutState::CREATED->value);

            $table->string('intent', 32);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable()->change();

            $table->char('currency', 3);
            $table->string('renew_interval', 8)->nullable();
        });

        Schema::create('store_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('purchasable');
            $table->bigInteger('purchase_id');
            $table->integer('quantity');

            $table->string('amount_discount', 32)->nullable();
            $table->string('amount_tax', 32)->nullable();
            $table->string('amount_total', 32)->nullable();
            $table->string('amount_subtotal', 32)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('store_purchase_items');

        Schema::table('store_purchases', function (Blueprint $table) {
            $table->dropColumn([
                'organization_id',
                'current_state',
                'intent',
                'payment_method_id',
                'customer_id',
                'billing_provider_id',
                'expires_at',
                'currency',
                'renew_interval'
            ]);

            // Revert nullable changes
            $table->bigInteger('activity_id')->nullable(false)->change();
            $table->timestamp('completed_at')->nullable(false)->change();
        });

        Schema::rename('store_purchases', 'intel_transactions');

        Schema::drop('convert_elements');

        Schema::create('convert_elements', function (Blueprint $table) {
            // Recreate the old table structure that was dropped
            $table->id();
            $table->timestamps();
            // Note: Add any columns that existed in the original table before the up() migration
        });

        Schema::drop('convert_bundle_items');

        Schema::table('intel_scenarios', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'lookup_key']);

            $table->dropColumn([
                'organization_id',
                'element_id',
                'display_name',
                'intent',
                'properties',
                'renew_interval'
            ]);
        });

        Schema::table('intel_activities', function (Blueprint $table) {
            $table->dropColumn([
                'agent_id',
                'migrate_session_id'
            ]);
        });

        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->dropColumn([
                'migrate_element_id',
                'migrate_scenario_id'
            ]);
        });
    }
};
