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
        Schema::table('account_agents', function (Blueprint $table) {
            $table->renameColumn('key', 'lookup_key');
        });

        Schema::table('account_customers', function (Blueprint $table) {
            $table->bigInteger('coupon_id')->nullable();
        });

        Schema::create('billing_discounts', function (Blueprint $table) {
            $table->id();
            $table->morphs('discountable');
            $table->bigInteger('coupon_id');
            $table->string('key', 32);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_agents', function (Blueprint $table) {
            $table->renameColumn('lookup_key', 'key');
        });
        Schema::table('account_customers', function (Blueprint $table) {
            $table->dropColumn('coupon_id');
        });
        Schema::drop('billing_discounts');
    }
};
