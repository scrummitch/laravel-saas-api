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
        Schema::rename('billing_schedule_plans', 'billing_subscriptions');

        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->bigInteger('schedule_id')->nullable()->change();
            $table->bigInteger('previous_subscription_id')->nullable()->after('schedule_id');
            $table->bigInteger('customer_id')->nullable()->after('organization_id');
            $table->string('current_state', 32)->default('unknown');

            $table->timestamp('start_at')->nullable()->after('created_at');
            $table->timestamp('end_at')->nullable()->after('start_at');
            $table->timestamp('cancel_at')->nullable()->after('end_at');
            $table->timestamp('invoiced_at')->nullable()->after('cancel_at');
            $table->timestamp('renewed_at')->nullable()->after('invoiced_at');
        });

        DB::statement('CREATE VIEW billing_schedule_plans AS SELECT * FROM billing_subscriptions WITH CHECK OPTION');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW billing_schedule_plans');

        Schema::rename('billing_subscriptions', 'billing_schedule_plans');

//            $table->dropColumn('renew_interval');
        Schema::table('billing_schedule_plans', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->dropColumn('start_at');
            $table->dropColumn('end_at');
            $table->dropColumn('cancel_at');
            $table->dropColumn('previous_subscription_id');
            $table->dropColumn('invoiced_at');
            $table->dropColumn('renewed_at');
        });
    }
};
