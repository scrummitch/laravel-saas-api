<?php

use App\Models\Values\PlanStatus;
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
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->string('status', 64)->after('name')->default(PlanStatus::Active);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_plans', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
