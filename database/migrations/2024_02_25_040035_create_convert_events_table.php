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
        Schema::create('convert_events', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('session_id');
            $table->string('name');
            $table->morphs('model');
            //            session_id
            //            model_id
            //            model_type
            //            event
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_events');
    }
};
