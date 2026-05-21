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
        // Add the client_id column
        Schema::table('convert_paywall_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->after('id');
            $table->index(['client_id', 'created_at'], 'idx_client_created');
        });

        // Copy client_id values from collectors
        DB::statement('
            UPDATE convert_paywall_sessions s
            INNER JOIN stats_collectors c ON s.collector_id = c.id
            SET s.client_id = c.client_id
        ');

        // Make client_id NOT NULL
        Schema::table('convert_paywall_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_paywall_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_client_created');
            $table->dropColumn('client_id');
        });
    }
};
