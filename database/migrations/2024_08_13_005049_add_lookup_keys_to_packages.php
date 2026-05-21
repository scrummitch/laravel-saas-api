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
        DB::table('pricing_packages')
            ->update([
                'lookup_key' => DB::raw('(SELECT lookup_key FROM catalog_products WHERE catalog_products.id = pricing_packages.product_id)'),
            ]);

        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->string('lookup_key')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->string('lookup_key')->nullable()->change();
        });
    }
};
