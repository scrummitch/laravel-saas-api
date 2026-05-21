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
        Schema::table('customers', function (Blueprint $table) {
            $table->json('payment_methods')->nullable();
            $table->bigInteger('primary_payment_method_id')->nullable();
            //            $table->json('taxes')->nullable();
            //    t.string "logo_url"
            //    t.string "legal_name"
            //    t.string "legal_number"
            //            t.string "url"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('payment_methods');
        });
    }
};
