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
        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->string('timezone')->nullable()->after('default_currency')->default('UTC');
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('amount_minimum_spend')->nullable()->after('properties');
            $table->integer('minimum_billable_usage')->nullable()->after('amount_minimum_spend');
            $table->string('invoice_description')->nullable()->after('minimum_billable_usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('amount_minimum_spend');
            $table->dropColumn('invoice_description');
            $table->dropColumn('minimum_billable_usage');
        });

        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
