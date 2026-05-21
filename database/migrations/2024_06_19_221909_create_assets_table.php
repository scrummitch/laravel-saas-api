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
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->foreignId('organization_id')->constrained('mgmt_organizations');
            $table->foreignId('user_id')->constrained();

            $table->enum('current_state', ['pending', 'uploaded', 'ready'])->default('pending');
            $table->string('name');
            $table->string('original_name');
            $table->string('content_type', 64);

            $table->string('disk', 32)->default('s3');
            $table->string('bucket', 64);
            $table->string('type', 32)->default('unknown');

            $table->integer('size');
            $table->string('key')->unique();

            $table->enum('visibility', ['public-read', 'private']);

            $table->timestamps();
        });

        Schema::create('media_attachments', function (Blueprint $table) {
            $table->foreignId('asset_id')->constrained('media_assets');
            $table->morphs('attachable');
            $table->string('type');
            $table->timestamps();
        });

        Schema::create('media_transformations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('media_assets');
            $table->uuid();
            $table->string('type', 32);
            $table->string('name', 128);
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::table('catalog_product_families', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_transformations');
        Schema::dropIfExists('media_attachments');
        Schema::dropIfExists('media_assets');
    }
};
