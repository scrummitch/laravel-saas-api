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
        if (! Schema::hasTable('pricing_schemes')) {

            Schema::rename('catalog_offerings', 'pricing_schemes');

            Schema::rename('catalog_offering_plans', 'pricing_packages');

            Schema::table('convert_paywalls', function (Blueprint $table) {
                $table->renameColumn('offering_id', 'pricing_scheme_id');
            });

            Schema::table('convert_checkouts', function (Blueprint $table) {
                $table->renameColumn('offering_id', 'pricing_scheme_id');
            });

            Schema::table('pricing_schemes', function (Blueprint $table) {
                $table->renameColumn('key', 'lookup_key');
                $table->unique(['lookup_key', 'organization_id', 'version_number']);
            });

            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->renameColumn('offering_id', 'pricing_scheme_id');
            });
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->bigInteger('plan_id')->nullable()->after('pricing_scheme_id')->change();
            });
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->bigInteger('product_id')->nullable()->after('pricing_scheme_id')->change();
            });
        }

        Schema::table('catalog_products', function (Blueprint $table) {
            $table->renameColumn('key', 'lookup_key');
        });
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->bigInteger('product_id')->nullable();
        });
        Schema::table('pricing_schemes', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('pricing_schemes', 'catalog_offerings');

        Schema::rename('pricing_packages', 'catalog_offering_plans');

        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->renameColumn('pricing_scheme_id', 'offering_id');
        });
        Schema::table('convert_checkouts', function (Blueprint $table) {
            $table->renameColumn('pricing_scheme_id', 'offering_id');
        });
        Schema::table('catalog_offerings', function (Blueprint $table) {
            $table->renameColumn('lookup_key', 'key');
            $table->dropUnique('pricing_schemes_lookup_key_organization_id_version_number_unique');
        });
        Schema::table('catalog_offering_plans', function (Blueprint $table) {
            $table->renameColumn('pricing_scheme_id', 'offering_id');
        });
        Schema::table('catalog_offering_plans', function (Blueprint $table) {
            $table->bigInteger('plan_id')->nullable()->change();
        });
        Schema::table('catalog_offering_plans', function (Blueprint $table) {
            $table->bigInteger('product_id')->nullable()->change();
        });
        Schema::table('catalog_products', function (Blueprint $table) {
            $table->renameColumn('lookup_key', 'key');
        });
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->dropColumn('product_id');
        });
    }
};
