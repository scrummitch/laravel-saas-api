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
        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->dropColumn('product_feature_id');
            $table->ulid()->after('id');
            $table->bigInteger('product_id')->after('plan_id')->nullable();
            $table->bigInteger('plan_id')->nullable(false)->change();

            $table->unique(['product_id', 'plan_id', 'feature_id']);
        });

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropIndex('catalog_plans_organization_id_name_unique');
            $table->dropIndex('catalog_plans_lookup_key_organization_id_unique');
            $table->unique(['organization_id', 'lookup_key', 'package_id']);
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('metric_id');
            $table->bigInteger('product_id')->nullable()->comment('deprecated')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the unique constraint first
        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->dropUnique('catalog_inclusions_product_id_plan_id_feature_id_unique');
        });

        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->dropColumn('ulid');
            $table->dropColumn('product_id');
            $table->bigInteger('product_feature_id')->after('id');
        });

        Schema::table('billing_charges', function (Blueprint $table) {
//            $table->bigInteger('metric_id')->nullable()->comment('deprecated')->change();
        });
    }
};
