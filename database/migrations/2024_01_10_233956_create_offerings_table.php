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
        Schema::create('catalog_offerings', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('organization_id');
            $table->integer('ancestor_id')->nullable();
            $table->integer('successor_id')->nullable();
            $table->string('key')->collation('utf8mb4_bin');
            $table->string('name', 128);
            $table->char('region', 4);
            $table->char('currency', 4);
            $table->string('version_name');
            $table->integer('version_number');
            $table->jsonb('packages');
            $table->timestamps();
        });

        Schema::create('catalog_offering_plans', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('offering_id');
            $table->bigInteger('plan_id');
            $table->bigInteger('product_id');
            $table->string('package_name', 128)->nullable();
            $table->timestamps();
            $table->unique(['offering_id', 'plan_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_offering_plans');
        Schema::dropIfExists('catalog_offerings');
    }
};
