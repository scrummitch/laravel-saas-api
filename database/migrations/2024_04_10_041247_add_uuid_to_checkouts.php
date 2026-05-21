<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('convert_checkouts', 'ulid')) {
            Schema::table('convert_checkouts', function (Blueprint $table) {
                $table->ulid()->after('id')->nullable()->unique();
            });
        }

        // add a ulid to every checkout
        DB::table('convert_checkouts')
            ->orderBy('id', 'desc')
            ->each(function (\stdClass $checkout) {
                DB::table('convert_checkouts')
                    ->where('id', $checkout->id)
                    ->update(['ulid' => Str::ulid()]);
            });

        // modify to ensure its not nullable
        Schema::table('convert_checkouts', function (Blueprint $table) {
            $table->ulid()->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('convert_checkouts', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
