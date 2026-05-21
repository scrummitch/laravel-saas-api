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
        if (Schema::hasTable('stats_collectors')) {
            return;
        }

        Schema::dropIfExists('convert_events');
        Schema::dropIfExists('client_events');

        Schema::create('stats_collectors', function (Blueprint $table) {
            $table->id();
            $table->uuid()->index();
            $table->integer('client_id');
            $table->uuid('anonymous_id')->nullable();
            $table->bigInteger('agent_id')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('origin', 128)->nullable();
            $table->char('os', 3)->nullable();
            $table->char('browser', 2)->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('client_events', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->index();
            $table->bigInteger('collector_id');
            $table->string('type', 16);
            $table->char('message_id', 36);
            $table->string('event_name', 64);
            $table->string('library', 64);
            $table->json('properties')->nullable();
            $table->timestamp('created_at');
            $table->unique(['client_id', 'message_id']);
        });

        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->dropColumn('template');
        });

        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->json('stages')->nullable()->change();
        });

        Schema::create('publish_rollouts', function (Blueprint $table) {
            $table->id();
            $table->ulid();
            $table->bigInteger('organization_id');
            $table->bigInteger('client_id');
            $table->morphs('publishable');
            $table->jsonb('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('account_agents', function (Blueprint $table) {
            $table->boolean('is_anonymous_user')->default(false)->after('is_sandbox_user');
        });

        Schema::create('publish_participations', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('rollout_id');
            $table->morphs('scope');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('convert_paywall_sessions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('paywall_id');
            $table->bigInteger('collector_id');
            $table->bigInteger('agent_id')->nullable();
            $table->bigInteger('workflow_id');
            $table->bigInteger('customer_id')->nullable();

            $table->string('cart_token', 64)->nullable();
            $table->string('placement')->nullable();

            $table->boolean('has_entered')->default(false);
            $table->boolean('has_started')->default(false);
            $table->boolean('has_completed')->default(false);

            $table->timestamp('entered_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();

            $table->timestamps();
        });

        Schema::create('convert_paywall_events', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('collector_id');
            $table->bigInteger('session_id');
            $table->string('name');
            $table->jsonb('data')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('convert_attributions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('session_id');
            // producer, source, cause,
            $table->morphs('producer');
            $table->morphs('purchase');

            $table->string('type', 16);
            // adjustment = -$10 ?
            // total-amount agg?
            // attributable? purchasable?
            $table->jsonb('data')->nullable();
            $table->jsonb('processed_invoices')->nullable();

            // adjustment?
            $table->string('currency', 3);
            $table->string('proceeds_amount_gross')->nullable();
            $table->string('proceeds_amount_net')->nullable();

            $table->timestamps();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the tables created in reverse order
        Schema::dropIfExists('convert_attributions');
        Schema::dropIfExists('convert_paywall_events');
        Schema::dropIfExists('convert_paywall_sessions');
        Schema::dropIfExists('publish_participations');
        Schema::dropIfExists('publish_rollouts');
        Schema::dropIfExists('client_events');
        Schema::dropIfExists('stats_collectors');

        // Reverse the changes to convert_paywalls table
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->text('template')->nullable();
        });

        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->text('stages')->nullable()->change();
        });

        // Remove the column added to account_agents
        Schema::table('account_agents', function (Blueprint $table) {
            $table->dropColumn('is_anonymous_user');
        });
    }
};
