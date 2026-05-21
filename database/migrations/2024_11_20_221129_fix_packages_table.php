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
        $initialMigration = Schema::hasColumn('pricing_packages', 'plan_id');

        if ($initialMigration) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                if (Schema::hasIndex('pricing_packages', 'pricing_packages_lookup_key_product_id_unique')) {
                    $table->dropUnique('pricing_packages_lookup_key_product_id_unique');
                }

                $table->dropColumn('plan_id');
                $table->unsignedBigInteger('organization_id')->after('id')->nullable();
                $table->renameColumn('package_name', 'name');
                $table->string('display_name')->nullable();
            });

            DB::table('pricing_packages')
                ->join('catalog_products', 'catalog_products.id', '=', 'pricing_packages.product_id')
                ->update(['pricing_packages.organization_id' => DB::raw('catalog_products.organization_id')]);
        }

        if ($initialMigration) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->foreign('organization_id')->references('id')->on('mgmt_organizations');
            });
        }

        Schema::table('pricing_packages', function (Blueprint $table) {
            if (Schema::hasIndex('pricing_packages', 'catalog_offering_plans_offering_id_plan_id_product_id_unique')) {
                $table->dropUnique('catalog_offering_plans_offering_id_plan_id_product_id_unique');
            }

            if (Schema::hasIndex('pricing_packages', 'pricing_packages_lookup_key_product_id_unique')) {
                $table->dropUnique('pricing_packages_lookup_key_product_id_unique');
            }

            $table->dropColumn('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropForeign('pricing_packages_organization_id_foreign');
            $table->bigInteger('plan_id')->nullable();
            $table->bigInteger('product_id')->nullable();
            $table->renameColumn('name', 'package_name');
            $table->dropColumn('display_name');
            $table->dropColumn('organization_id');
        });
    }
};
