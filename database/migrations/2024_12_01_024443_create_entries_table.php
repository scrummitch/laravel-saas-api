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
        Schema::create('intel_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->index();
            $table->tinyInteger('type')->index(); // SignalID
            $table->string('amount_raw', 64)->nullable();
            $table->char('currency', 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('intel_dependencies', function (Blueprint $table) {
            $table->foreignId('signal_id')->constrained('intel_signals')->cascadeOnDelete();
            $table->morphs('linkable');
            $table->tinyInteger('relation_type'); // 0=from, 1=to, 2=keep
            $table->integer('quantity')->nullable();
            $table->integer('delta')->nullable();
            $table->index(['signal_id', 'linkable_type', 'linkable_id'], 'linkable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints first
        if (Schema::hasTable('intel_dependencies')) {
            Schema::table('intel_dependencies', function (Blueprint $table) {
                $table->dropForeign('account_signal_dependencies_signal_id_foreign');
            });
        }

        // Drop the tables
        Schema::dropIfExists('intel_dependencies');
        Schema::dropIfExists('intel_signals');
        Schema::dropIfExists('account_signal_dependencies');
        Schema::dropIfExists('account_signals');
    }
};
