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
        Schema::create('usage_metrics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('feature_id');

            $table->string('key', 128)->collation('utf8mb4_bin');

            $table->string('aggregation', 128);
            $table->string('type');
            $table->string('field_name')->nullable();
            $table->string('weighted_interval')->nullable();
            $table->json('filters')->nullable();

            $table->timestamps();
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();

            $table->integer('organization_id');
            $table->integer('client_id');
            $table->integer('customer_id')->nullable();
            $table->string('agent_id')->nullable();

            $table->string('event_name');

            $table->string('unique_id', 200);

            $table->jsonb('properties')->nullable();
            $table->jsonb('metadata')->nullable();

            $table->timestamps();
            $table->unique(['organization_id', 'unique_id']);
        });

        Schema::create('usage_summaries', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id');
            $table->integer('metric_id');
            $table->integer('customer_id')->nullable();

            $table->string('event_name');
            $table->integer('latest_event_id');
            $table->string('current_aggregation');

            $table->timestamps();
        });

        if (Schema::hasTable('catalog_plan_inclusions')) {
            Schema::drop('catalog_plan_inclusions');
        }

        Schema::dropIfExists('catalog_inclusions');
        Schema::create('catalog_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_feature_id')->nullable();
            $table->foreignId('feature_id');
            $table->foreignId('plan_id')->nullable();
            $table->foreignId('charge_id')->nullable();
            $table->foreignId('metric_id')->nullable();

            $table->string('grouping_key')->nullable();
            $table->string('grouping_label')->nullable();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            $table->string('name')->nullable();
            $table->string('note', 256)->nullable();
            $table->float('default_limit')->nullable();
            $table->string('limit_unit', 64)->nullable();

            $table->string('reset_anchor')->nullable();
            $table->boolean('is_approved')->default(false);

            $table->timestamps();
            $table->unique(['product_feature_id', 'plan_id']);
        });

        Schema::table('catalog_features', function (Blueprint $table) {
            $table->bigInteger('superseded_by')->nullable()->after('organization_id');
            $table->string('display_name', 128)->after('key')->nullable();
            $table->timestamp('archived_at')->nullable()->after('released_at');
            $table->bigInteger('feature_set_id')->nullable()->change();
        });

        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->dropColumn('default_limit');
        });
        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->dropColumn('limit_unit');
        });
        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->renameColumn('label', 'name');
        });
        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->string('allowance');
            $table->string('unit');
            $table->string('reset_period');
        });

        Schema::table('catalog_feature_sets', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert changes to `usage_metrics`
        Schema::dropIfExists('usage_metrics');

        // Revert changes to `usage_events`
        Schema::dropIfExists('usage_events');

        // Revert changes to `usage_summaries`
        Schema::dropIfExists('usage_summaries');

        // Drop and restore `catalog_inclusions`
        Schema::dropIfExists('catalog_inclusions');
        Schema::create('catalog_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_feature_id')->nullable();
            $table->foreignId('plan_id')->nullable();
            $table->foreignId('charge_id')->nullable();

            $table->string('grouping_key')->nullable();
            $table->string('grouping_label')->nullable();
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });

        // Restore `catalog_plan_inclusions` if it was dropped
        Schema::create('catalog_plan_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_feature_id');
            $table->foreignId('plan_id');
            $table->timestamps();
        });

        // Revert changes to `catalog_features`
        Schema::table('catalog_features', function (Blueprint $table) {
            $table->dropColumn('superseded_by');
            $table->dropColumn('display_name');
            $table->dropColumn('archived_at');
            $table->bigInteger('feature_set_id')->change();
        });

        // Revert changes to `catalog_product_features`
        Schema::table('catalog_product_features', function (Blueprint $table) {
            $table->dropColumn('allowance');
            $table->dropColumn('unit');
            $table->dropColumn('reset_period');
            $table->string('default_limit')->nullable()->after('feature_id');
            $table->string('limit_unit')->nullable()->after('default_limit');
            $table->renameColumn('name', 'label');
        });

        // Revert changes to `catalog_feature_sets`
        Schema::table('catalog_feature_sets', function (Blueprint $table) {
            $table->bigInteger('product_id')->nullable();
        });
    }
};
