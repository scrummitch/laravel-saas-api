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
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->jsonb('settings')->nullable()->after('checkout_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_paywalls', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
