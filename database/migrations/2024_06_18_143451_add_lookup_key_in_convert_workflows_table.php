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
        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->string('lookup_key', 64)->nullable()->after('id');
        });

        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });

        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->string('status', 32)->after('client_id')->default('live');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_workflows', function (Blueprint $table) {
            $table->ulid()->nullable();
            $table->dropColumn('lookup_key');

            if (Schema::hasColumn('convert_workflows', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
