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
        Schema::create('client_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('client_id');
            $table->bigInteger('agent_id');
            $table->bigInteger('offering_id')->nullable();

            // figure out the 'offering' for this session
            // let people see future offerings

            $table->ipAddress()->nullable();
            $table->string('user_agent', 256)->nullable();
            $table->string('referer', 256)->nullable();
            $table->string('origin', 256)->nullable();
            $table->string('locale', 64)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('browser', 64)->nullable();

            // auto-expire after a certain time
            // decide when to count dropoff

            $table->timestamps();
        });

        Schema::create('client_events', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('session_id');
            $table->string('type', 64);
            $table->jsonb('data');
            $table->timestamp('created_at');
        });

        // client events
        // track session id
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_events');
        Schema::dropIfExists('client_sessions');
    }
};
