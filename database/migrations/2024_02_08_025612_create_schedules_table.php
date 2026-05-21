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
        Schema::create('billing_schedules', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id');
            $table->bigInteger('organization_id');
            $table->bigInteger('latest_payment_id')->nullable();
            $table->bigInteger('first_purchase_id')->nullable();

            $table->char('country_code', 2)->nullable();
            $table->char('currency_code', 4)->nullable();
            $table->string('payment_state')->default('pending');
            $table->string('cancel_reason')->nullable();
            $table->string('cancel_message')->nullable();

            $table->boolean('will_auto_renew')->default(false);

            $table->timestamp('resume_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // payment state: pending, received, trial, deferred
            // cancel reason: system, error, replaced, canceled
            // upcoming price change info
            // promo / discount info
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_schedules');
    }
};
