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
        Schema::create('convert_workflows', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->integer('organization_id');
            $table->integer('client_id');
            $table->string('name', 128);
            $table->jsonb('triggers');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_workflows');
    }
};
