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
        Schema::table('catalog_product_families', function (Blueprint $table) {
            $table->unique(['organization_id', 'lookup_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_product_families', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'lookup_key']);
        });
    }
};
