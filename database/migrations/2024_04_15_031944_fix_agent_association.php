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
        Schema::table('agent_associations', function (Blueprint $table) {
            $table->string('key')->nullable()->change();
            $table->unique(['key', 'agent_id', 'customer_id', 'organization_id'], 'agnt_assoc_key_uniq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_associations', function (Blueprint $table) {
            $table->string('key')->nullable(false)->change();
            $table->dropUnique('agnt_assoc_key_uniq');
        });
    }
};
