<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('billing_schedule_plans', function (Blueprint $table) {
            $table->index('schedule_id');
            $table->index('plan_id');
            $table->index(['schedule_id', 'plan_id']);
        });

        Schema::table('billing_schedules', function (Blueprint $table) {
            $table->index('customer_id');
        });
    }

    public function down()
    {
        Schema::table('billing_schedule_plans', function (Blueprint $table) {
            $table->dropIndex(['schedule_id']);
            $table->dropIndex(['plan_id']);
            $table->dropIndex(['schedule_id', 'plan_id']);
        });

        Schema::table('billing_schedules', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
        });
    }
};
