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
        DB::table('convert_workflow_clients')
            ->join('convert_workflows', 'convert_workflow_clients.workflow_id', '=', 'convert_workflows.id')
            ->latest('convert_workflow_clients.id')
            ->each(function ($wf) {
                DB::table('publish_rollouts')
                    ->insert([
                        'ulid' => strval(Str::ulid()),
                        'organization_id' => $wf->organization_id,
                        'client_id' => $wf->client_id,
                        'publishable_id' => $wf->workflow_id,
                        'publishable_type' => 'workflow',
                        'is_active' => $wf->is_active,
                        'rules' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            });

        Schema::table('convert_checkouts', function (Blueprint $table) {
            $table->bigInteger('session_id')->nullable();
        });

        Schema::dropIfExists('convert_workflow_clients');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
