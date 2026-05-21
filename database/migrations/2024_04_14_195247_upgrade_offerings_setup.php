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
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->json('properties')->default(null)->change();
        });

        foreach (['region', 'currency', 'packages'] as $col) {
            Schema::table('catalog_offerings', fn (Blueprint $table) => $table->dropColumn($col));
        }

        Schema::table('catalog_offering_plans', function (Blueprint $table) {
            $table->string('interval', 16)->after('plan_id');
            $table->char('currency', 3)->after('interval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_offerings', function (Blueprint $table) {
            $table->char('region', 4);
            $table->char('currency', 4);
            $table->jsonb('packages');
        });

        Schema::table('catalog_offering_plans', function (Blueprint $table) {
            $table->dropColumn('product_id');
            $table->dropColumn('package_name');
            $table->dropColumn('interval');
            $table->dropColumn('currency');
        });
    }
};
