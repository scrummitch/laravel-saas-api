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
        if (! Schema::hasIndex('pricing_packages', 'product_interval_currency')) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->unique(['pricing_scheme_id', 'product_id', 'interval', 'currency'], 'product_interval_currency');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('pricing_packages', 'product_interval_currency')) {
            Schema::table('pricing_packages', function (Blueprint $table) {
                $table->dropUnique('product_interval_currency');
            });
        }
    }
};
