<?php

use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\Ulid;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('intel_activities', function (Blueprint $table) {
            $table->timestamp('last_interaction_at', 3)->nullable()->change();
        });

        // if convert_attributions doesnt have activity_id lets add it
        if (!Schema::hasColumn('convert_attributions', 'activity_id')) {
            Schema::table('convert_attributions', function (Blueprint $table) {
                $table->bigInteger('activity_id')->nullable()->after('id');
            });
        }

        if (class_exists(\App\Convert\PaywallService::class)
            && method_exists(\App\Convert\PaywallService::class, 'migrateToActivities')
        ) {
            \App\Convert\PaywallService::migrateToActivities();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
