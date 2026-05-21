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
        Schema::create('convert_elements', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->string('lookup_key', 64);
            $table->string('display_name')->nullable();
            $table->string('type', 16);
            $table->string('current_state')->default('draft');
            $table->string('insertion_type')->default('manual');
            $table->jsonb('insertion_rules')->nullable();
            $table->jsonb('view')->nullable();
            $table->jsonb('conditions')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'lookup_key']);
        });

        Schema::create('convert_flows', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->string('lookup_key', 64);

            $table->string('display_name');
            $table->string('current_state')->default('active');
            $table->jsonb('scenarios');
            $table->integer('inactivity_timeout_seconds')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'lookup_key']);
        });

        Schema::create('convert_handlers', function (Blueprint $table) {
            $table->id();
            $table->uuid();

            $table->bigInteger('element_id');
            $table->bigInteger('flow_id');

            $table->string('event_name');
        });

        Schema::create('convert_activities', function (Blueprint $table) {
            $table->id();
            $table->uuid();

            $table->bigInteger('parent_id')->nullable();
            $table->string('external_process_id')->nullable();
            $table->foreignId('handler_id')->constrained('convert_handlers');
            $table->foreignId('collector_id')->constrained('stats_collectors');
            $table->foreignId('customer_id')->nullable()->constrained('account_customers');

            $table->nullableMorphs('process');

            $table->string('current_state', 32)->default('created');
            $table->boolean('has_started')->default(false);

            $table->string('error_reason', 128)->nullable();
            $table->string('result_code', 64)->nullable();
            $table->string('result_message', 256)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();

            $table->timestamps();
        });

        Schema::create('convert_activity_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id');
            $table->foreignId('activity_id')->constrained('convert_activities');
            $table->string('event_name', 32);
            $table->jsonb('data')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convert_activity_events');
        Schema::dropIfExists('convert_activities');
        Schema::dropIfExists('convert_handlers');
        Schema::dropIfExists('convert_flows');
        Schema::dropIfExists('convert_elements');
    }
};
