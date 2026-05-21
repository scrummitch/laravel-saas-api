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
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id');
            $table->uuid('batch_id')->nullable();
            $table->morphs('model');
            $table->string('name', 64);
            $table->string('description')->nullable();
            $table->jsonb('output')->nullable();
            $table->integer('progress')->default(0);
            $table->enum('status', ['pending', 'running', 'failed', 'finished', 'cancelled']);
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
