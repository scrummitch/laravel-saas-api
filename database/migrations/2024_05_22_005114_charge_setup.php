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
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->string('type')->after('currency')->nullable();
            $table->jsonb('properties')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('properties');
        });
    }
};
