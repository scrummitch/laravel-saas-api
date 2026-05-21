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
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('amount', 16)->nullable();
        });

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('name', 128)->change();
            $table->enum('mode', ['in_advance', 'in_arrears', 'on_demand'])->default('in_advance');
            $table->string('min_amount', 32)->nullable();
            $table->json('properties')->nullable()->change();
        });

        DB::table('billing_charges')->update(['mode' => DB::raw('type')]);
        DB::table('billing_charges')->update(['amount' => DB::raw('min_amount_cents')]);

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('min_amount_cents');
        });

        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->renameColumn('period', 'renew_interval');
        });
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->string('invoice_interval', 16)->nullable();
            $table->enum('billing_anchor', ['anniversary', 'calendar'])->default('anniversary');
        });
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->dropColumn('minimum_charge_frequency');
        });
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->dropColumn('minimum_charge');
        });

        DB::table('catalog_plans')->update(['invoice_interval' => DB::raw('renew_interval')]);
        DB::table('catalog_plans')->update(['billing_anchor' => 'anniversary']);

        DB::table('catalog_plans')->where('renew_interval', 'monthly')->update(['renew_interval' => 'P1M']);
        DB::table('catalog_plans')->where('renew_interval', 'yearly')->update(['renew_interval' => 'P1Y']);

        DB::table('catalog_plans')->where('invoice_interval', 'monthly')->update(['renew_interval' => 'P1M']);
        DB::table('catalog_plans')->where('invoice_interval', 'yearly')->update(['renew_interval' => 'P1Y']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        // Revert changes to `billing_charges`
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->enum('mode', ['in_advance', 'in_arrears', 'on_demand'])->default(null)->change();
            $table->string('name', 64)->change();
            $table->dropColumn('mode');
            $table->string('min_amount', 16)->nullable()->change();
            $table->integer('min_amount_cents')->nullable();
            $table->json('properties')->nullable(false)->change();
        });

        DB::table('billing_charges')->update(['min_amount_cents' => DB::raw('amount')]);

        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        // Revert changes to `catalog_plans`
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->renameColumn('renew_interval', 'period');
            $table->dropColumn('invoice_interval');
            $table->dropColumn('billing_anchor');
            $table->integer('minimum_charge_frequency')->nullable();
            $table->decimal('minimum_charge', 10, 2)->nullable();
        });

        DB::table('catalog_plans')->where('period', 'P1M')->update(['period' => 'monthly']);
        DB::table('catalog_plans')->where('period', 'P1Y')->update(['period' => 'yearly']);
    }
};
