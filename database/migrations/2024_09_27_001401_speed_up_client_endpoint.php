<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection()->getDriverName();

        if ($connection === 'mysql') {
            // MySQL-specific index creation with ALGORITHM=INPLACE
            DB::statement('ALTER TABLE billing_charges ADD INDEX idx_deleted_at (deleted_at), ALGORITHM=INPLACE, LOCK=NONE');
            DB::statement('ALTER TABLE clients ADD INDEX clients_ulid_deleted_at (ulid, deleted_at), ALGORITHM=INPLACE, LOCK=NONE');
            DB::statement('ALTER TABLE stats_collectors ADD INDEX idx_client_id_uuid (client_id, uuid), ALGORITHM=INPLACE, LOCK=NONE');
        } else {
            // Generic index creation for SQLite and other databases
            Schema::table('billing_charges', function (Blueprint $table) {
                $table->index('deleted_at', 'idx_deleted_at');
            });

            Schema::table('clients', function (Blueprint $table) {
                $table->index(['ulid', 'deleted_at'], 'clients_ulid_deleted_at');
            });

            Schema::table('stats_collectors', function (Blueprint $table) {
                $table->index(['client_id', 'uuid'], 'idx_client_id_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('billing_charges', function (Blueprint $table) {
            $table->dropIndex('idx_deleted_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_ulid_deleted_at');
        });

        Schema::table('stats_collectors', function (Blueprint $table) {
            $table->dropIndex('idx_client_id_uuid');
        });
    }
};
