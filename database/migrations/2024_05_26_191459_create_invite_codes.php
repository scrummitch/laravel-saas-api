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
        Schema::disableForeignKeyConstraints();
        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->renameColumn('signup_token', 'invite_code');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mgmt_organizations', function (Blueprint $table) {
            $table->renameColumn('invite_code', 'signup_token');
        });
    }
};
