<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('convert_flows');

        // Add a temporary column to store the tinyint equivalent
        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_state')->default(\App\Intel\Enums\FlowState::Active->value)->nullable()->after('organization_id');
            $table->jsonb('triggers')->nullable()->change();
        });

        // Move event handlers to here so we're not listening on the flow
        Schema::table('convert_handlers', function (Blueprint $table) {
            $table->string('listen', 32)->nullable()->after('element_id');
            $table->string('qualifier', 32)->nullable()->after('listen');
            $table->bigInteger('element_id')->nullable()->change();
            $table->jsonb('props')->nullable()->after('qualifier');
        });

        // Create scenarios, which is "headless paywall"
        Schema::create('intel_scenarios', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('flow_id');
            $table->string('lookup_key', 128);
            $table->bigInteger('scheme_id')->nullable();
            $table->json('conditions')->nullable();
            $table->json('bundle_rules')->nullable();
            $table->unsignedTinyInteger('current_state')->default(\App\Intel\Enums\ScenarioState::Draft);
            $table->timestamps();
            $table->unique(['flow_id', 'lookup_key']);
        });

        // move activities work to intel module
        Schema::rename('convert_activities', 'intel_activities');
        Schema::rename('convert_activity_events', 'intel_actions');

        Schema::table('intel_activities', function (Blueprint $table) {
            if ($this->hasForeignKey('intel_activities', 'convert_activities_handler_id_foreign')) {
                $table->dropForeign('convert_activities_handler_id_foreign');
            }

            $table->dropColumn('external_process_id');

            $table->bigInteger('client_id')->after('id')->nullable();
            $table->bigInteger('scenario_id')->after('client_id')->nullable();
            $table->bigInteger('handler_id')->nullable()->change();

            $table->boolean('has_entered')->default(false)->after('current_state');
            $table->boolean('has_completed')->default(false)->after('has_started');

            $table->dropColumn('process_id');
            $table->dropColumn('process_type');

            $table->timestamp('last_interaction_at', 3)->change();
            $table->timestamp('created_at', 3)->change();
        });

        Schema::table('intel_signals', function (Blueprint $table) {
            $table->bigInteger('activity_id')->nullable()->after('customer_id');
        });

        Schema::table('intel_actions', function (Blueprint $table) {
            $table->unsignedTinyInteger('type')->after('activity_id');
            $table->string('event_name', 64)->nullable()->change();
            $table->renameColumn('message_id', 'event_id');
            $table->string('event_id', 128)->nullable()->change();
            $table->renameColumn('data', 'properties');
        });

        Schema::create('intel_transactions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('activity_id');
            $table->string('provider_name', 32);
            $table->string('provider_id', 128);
            $table->timestamps();
            $table->timestamp('completed_at');

            $table->index(['activity_id', 'provider_name', 'provider_id']);
        });

        Schema::table('convert_paywall_events', function (Blueprint $table) {
            $table->bigInteger('activity_id')->nullable();
        });

        Schema::table('convert_attributions', function (Blueprint $table) {
            $table->bigInteger('activity_id')->nullable();
            $table->bigInteger('session_id')->nullable()->change();
        });

        // Convert old workflow triggers to handlers
        DB::table('convert_workflows')
            ->orderBy('id', 'desc')
            ->each(function ($wf) {
                foreach (json_decode($wf->triggers) ?? [] as $trigger) {
                    DB::table('convert_handlers')
                        ->insert([
                            'uuid' => Str::uuid()->toString(),
                            'element_id' => null,
                            'flow_id' => $wf->id,
                            'listen' => data_get($trigger, 'listen'),
                            'qualifier' => data_get($trigger, 'qualifier') ?? 'none',
                            'event_name' => data_get($trigger, 'event'),
                            'props' => empty(data_get($trigger, 'props')) ? null : json_encode($trigger->props),
                        ]);
                }
            });


    }

    private function hasForeignKey(string $table, string $foreignKeyName): bool
    {
        return DB::select("
        SELECT COUNT(*) as count
        FROM information_schema.table_constraints
        WHERE constraint_type = 'FOREIGN KEY'
        AND table_name = ?
        AND constraint_name = ?",
                [$table, $foreignKeyName]
            )[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intel_actions', function (Blueprint $table) {
            if ($this->hasForeignKey('intel_actions', 'convert_activity_events_activity_id_foreign')) {
                $table->dropForeign('convert_activity_events_activity_id_foreign');
            }
        });

        DB::table('intel_actions')->truncate();
        DB::table('intel_activities')->truncate();


        // Restore the original table names
        Schema::rename('intel_activities', 'convert_activities');
        Schema::rename('intel_actions', 'convert_activity_events');

        // Drop the new tables created in up()
        Schema::dropIfExists('intel_scenarios');
        Schema::dropIfExists('intel_transactions');

        // Revert changes to convert_activities table
        Schema::table('convert_activities', function (Blueprint $table) {
            $table->dropColumn('client_id');
            $table->dropColumn('scenario_id');
            $table->dropColumn('has_entered');
            $table->dropColumn('has_completed');
            $table->string('external_process_id')->nullable();

            // Revert timestamp precision changes
            $table->timestamp('last_interaction_at')->change();
            $table->timestamp('created_at')->change();
        });

        // Revert changes to intel_signals table
        Schema::table('intel_signals', function (Blueprint $table) {
            $table->dropColumn('activity_id');
        });

        // Revert changes to convert_activity_events (renamed from intel_actions)
        Schema::table('convert_activity_events', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->string('event_name', 255)->change();
            $table->renameColumn('event_id', 'message_id');
            $table->string('message_id', 255)->change();
            $table->renameColumn('properties', 'data');
        });

        // Revert changes to convert_workflows table
        // First, convert handlers back to triggers
        $workflowTriggers = [];
        DB::table('convert_handlers')
            ->whereNotNull('listen')
            ->orderBy('id')
            ->each(function ($handler) use (&$workflowTriggers) {
                $trigger = [
                    'listen' => $handler->listen,
                    'qualifier' => $handler->qualifier === 'none' ? null : $handler->qualifier,
                    'event' => $handler->event_name,
                    'props' => json_decode($handler->props ?? '{}')
                ];

                $workflowTriggers[$handler->flow_id][] = $trigger;
            });

        foreach ($workflowTriggers as $flowId => $triggers) {
            DB::table('convert_workflows')
                ->where('id', $flowId)
                ->update(['triggers' => json_encode($triggers)]);
        }

        // Revert changes to convert_handlers table
        Schema::table('convert_handlers', function (Blueprint $table) {
            $table->dropColumn('listen');
            $table->dropColumn('qualifier');
            $table->dropColumn('props');
            $table->bigInteger('element_id')->change();
        });

        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->dropColumn('current_state');
            $table->json('triggers')->change();
        });

        // Recreate the dropped table if it existed
        Schema::create('convert_flows', function (Blueprint $table) {
            // Add the original schema structure for convert_flows
            // Note: You'll need to add the correct columns based on the original table structure
            $table->id();
            $table->timestamps();
        });
    }
};
