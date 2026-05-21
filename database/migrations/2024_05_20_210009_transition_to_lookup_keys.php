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
        Schema::table('catalog_features', function (Blueprint $table) {
            $table->renameColumn('key', 'lookup_key');
        });

        Schema::table('catalog_feature_sets', function (Blueprint $table) {
            $table->ulid()->after('id');
        });

        Schema::table('usage_metrics', function (Blueprint $table) {
            $table->ulid()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_metrics', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
        Schema::table('catalog_feature_sets', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
        Schema::table('catalog_features', function (Blueprint $table) {
            $table->renameColumn('lookup_key', 'key');
        });
    }
};
