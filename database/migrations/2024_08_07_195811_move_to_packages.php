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
        // Remove duplicates from pricing_packages table based on pricing_scheme_id and product_id
        DB::table('pricing_packages as p1')
            ->join(DB::raw('(SELECT pricing_scheme_id, product_id, MIN(id) as min_id
                        FROM pricing_packages
                        GROUP BY pricing_scheme_id, product_id
                        HAVING COUNT(*) > 1) as p2'), function ($join) {
                $join->on('p1.pricing_scheme_id', '=', 'p2.pricing_scheme_id')
                    ->on('p1.product_id', '=', 'p2.product_id');
            })
            ->where('p1.id', '>', DB::raw('p2.min_id'))
            ->delete();

        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->bigInteger('product_feature_id')->nullable()->change();
        });

        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->string('reset_anchor')->nullable()->change();
        });

        Schema::table('catalog_inclusions', function (Blueprint $table) {
            $table->dropUnique('catalog_inclusions_product_feature_id_plan_id_unique');
        });

        DB::table('catalog_products')
            ->cursor()
            ->each(function ($product) {
                $charges = DB::table('billing_charges')
                    ->where('product_id', $product->id)
                    ->get();

                $packageId = DB::table('pricing_packages')
                    ->where(['product_id' => $product->id])
                    ->value('id');

                foreach ($charges as $charge) {
                    $plan = DB::table('pricing_plans')
                        ->where('base_charge_id', $charge->id)
                        ->first();

                    if (! $plan) {
                        logger()->info(logname('no-plan-for-charge'), ['charge' => $charge]);

                        continue;
                    }

                    DB::table('pricing_plans')
                        ->where('id', $plan->id)
                        ->update(['package_id' => $packageId]);

                    DB::table('catalog_inclusions')
                        ->insert([
                            'plan_id' => $plan->id,
                            'charge_id' => $charge->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            });

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn('base_charge_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
