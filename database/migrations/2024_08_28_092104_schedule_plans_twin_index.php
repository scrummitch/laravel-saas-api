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
        Schema::table('billing_schedule_plans', function (Blueprint $table) {
            $table->index(['twin_id']);
        });

        Schema::table('convert_themes', function (Blueprint $table) {
            $table->index('organization_id');
        });

        Schema::table('twins', function (Blueprint $table) {
            $table->index('connector_id');
            $table->index(['connector_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('billing_schedule_plans', function (Blueprint $table) {
            $table->dropIndex(['twin_id']);
        });

        Schema::table('convert_themes', function (Blueprint $table) {
            $table->dropIndex(['organization_id']);
        });

        Schema::table('twins', function (Blueprint $table) {
            $table->dropIndex(['connector_id']);
            $table->dropIndex(['connector_id', 'type']);
        });
    }
};
