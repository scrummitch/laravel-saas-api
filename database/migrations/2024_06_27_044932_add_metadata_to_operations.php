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
        Schema::table('account_customers', function (Blueprint $table) {
            $table->index('reference_id');
        });

        Schema::table('mgmt_operations', function (Blueprint $table) {
            $table->jsonb('metadata')->after('status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_customers', function (Blueprint $table) {
            $table->dropIndex(['reference_id']);
        });

        Schema::table('mgmt_operations', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
