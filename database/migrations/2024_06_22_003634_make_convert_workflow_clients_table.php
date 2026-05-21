<?php

use App\Models\Convert\ClientWorkflow;
use App\Models\Convert\Flow;
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
        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->dropColumn(['status', 'client_id']);
        });

        Schema::create('convert_workflow_clients', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id');
            $table->integer('workflow_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'workflow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->integer('client_id')->nullable();
        });

        Schema::dropIfExists('convert_workflow_clients');
    }
};
