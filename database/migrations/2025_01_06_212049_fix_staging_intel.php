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
        if (Schema::hasTable('account_signals')) {
            Schema::rename('account_signals', 'intel_signals');
            Schema::rename('account_signal_dependencies', 'intel_dependencies');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intel_dependencies');
        Schema::dropIfExists('intel_signals');
    }
};
