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
        Schema::create('billing_charges', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('product_id');
            $table->bigInteger('metric_id')->nullable();
            $table->string('frequency');
            $table->string('type');
            $table->string('name', 128);
            $table->string('currency', 4);
            $table->string('min_amount_cents', 32)->nullable();

            $table->jsonb('properties');
            // todo: group-props?
            // dimension_properties
            // dimensions table? facet, dimension, property, value

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_charges');
    }
};
