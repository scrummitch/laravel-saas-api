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
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->foreignId('organization_id')->constrained('organizations');
            $table->string('name', 64)->nullable();
            $table->string('provider_name', 64);
            $table->string('provider_id', 128);
            $table->text('secret')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_live')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
