<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('allowed_origins')->nullable()->after('secret');
            $table->json('pending_origins')->nullable()->after('allowed_origins');
            $table->enum('environment', ['live', 'test'])->default('live')->after('type');
        });

        DB::table('clients')->update(['environment' => 'live']);

        Schema::table('convert_themes', function (Blueprint $table) {
            $table->string('accent_color');
        });

        // update all themes with default accent color of a nice light blue
        DB::table('convert_themes')->update(['accent_color' => '#007bff']);

        Schema::table('billing_providers', function (Blueprint $table) {
            $table->string('current_state')->after('config')->default('created');
        });

        Schema::table('pricing_schemes', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable()->index()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
