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
        Schema::create('catalog_feature_sets', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('product_id');
            $table->string('key', 128)->collation('utf8mb4_bin');
            $table->string('name', 128);
            $table->string('description', 256)->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();
        });

        Schema::create('catalog_features', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->foreignId('feature_set_id');
            $table->string('key', 128)->collation('utf8mb4_bin');
            $table->string('name', 128);
            $table->string('description', 256)->nullable();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();
        });

        Schema::create('catalog_product_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('feature_id');
            //            $table->string('grouping_key')->nullable();
            //            $table->string('grouping_label')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->string('note', 256)->nullable();
            $table->float('default_limit')->nullable();
            $table->string('limit_unit', 64)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_product_features');
        Schema::dropIfExists('catalog_features');
        Schema::dropIfExists('catalog_feature_sets');
    }
};
