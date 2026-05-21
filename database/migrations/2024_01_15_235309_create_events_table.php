<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // evnet

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id');
            $table->integer('client_id');
            $table->integer('customer_id')->nullable();

            // todo: change this to "profile_id"
            $table->string('agent_id');

            // need profile_id, group_id included

            $table->string('unique_id', 200);
            $table->string('event_name', 200)->nullable();
            $table->jsonb('properties')->nullable();

            $table->ipAddress('ip')->nullable();

            $table->timestamp('timestamp', 6)->nullable(); //->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('created_at');
            //            $table->enum('type', ['track', 'identify', 'page', 'screen', 'group', 'alias']);
            $table->unique(['unique_id', 'organization_id']);
            $table->index(['timestamp', 'organization_id', 'event_name']);
        });

        Schema::create('event_definitions', function (Blueprint $table) {
            $table->id();
            $table->integer('organization_id');
            $table->string('name', 400)->nullable();
            $table->string('description', 400)->nullable();
            //            $table->jsonb('properties')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps(6);
            $table->unique(['organization_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_definitions');
        Schema::dropIfExists('events');
    }
};
