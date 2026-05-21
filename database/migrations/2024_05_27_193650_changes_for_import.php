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
        Schema::table('mgmt_operations', function (Blueprint $table) {
            $table->text('description')->change();
        });

        Schema::table('catalog_products', function (Blueprint $table) {
            $table->integer('version_number')->nullable()->change();
            $table->string('version_name', 32)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mgmt_operations', function (Blueprint $table) {
            $table->string('description')->change();
        });

        Schema::table('catalog_products', function (Blueprint $table) {
            $table->integer('version_number')->change();
            $table->string('version_name', 32)->change();
        });
    }
};
