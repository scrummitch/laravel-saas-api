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
        Schema::table('convert_paywall_sessions', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('completed_at');
            $table->string('exclusion_reason')->nullable()->after('is_excluded');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->json('exclusion_rules')->nullable()->after('environment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_paywall_sessions', function (Blueprint $table) {
            $table->dropColumn('is_excluded');
            $table->dropColumn('exclusion_reason');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('exclusion_rules');
        });
    }

};
