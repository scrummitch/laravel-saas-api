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
        Schema::rename('products', 'catalog_products');

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
        Schema::table('billing_charges', function (Blueprint $blueprint) {
            $blueprint->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('type');
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('frequency');
        });

        Schema::rename('catalog_products', 'products');
    }
};
