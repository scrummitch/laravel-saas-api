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
        Schema::create('convert_paywalls', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('organization_id');
            $table->bigInteger('workflow_id');
            $table->bigInteger('offering_id');
            $table->bigInteger('connection_id');
            $table->string('variant_key', 64)->nullable();

            $table->string('name', 128);
            $table->string('intent');
            $table->string('weight')->nullable();
            $table->string('mode', 32);
            $table->string('type', 64);

            $table->jsonb('template');
            $table->jsonb('stages');
            $table->jsonb('conditions');
            $table->jsonb('checkout_config')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_paywalls');
    }
};
