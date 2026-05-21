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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('organization_id');
            $table->string('name', 128);
            $table->enum('type', ['web', 'mobile', 'server'])->default('web');
            $table->string('platform', 32)->nullable();
            $table->text('secret');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['name', 'organization_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
