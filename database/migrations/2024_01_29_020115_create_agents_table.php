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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('organization_id');
            $table->bigInteger('customer_id')->nullable();
            $table->string('key', 128)->collation('utf8mb4_bin');
            $table->timestamps();
        });

        Schema::create('agent_associations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->nullable();
            $table->bigInteger('agent_id');
            $table->bigInteger('customer_id');
            $table->bigInteger('organization_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_associations');
        Schema::dropIfExists('agents');
    }
};
