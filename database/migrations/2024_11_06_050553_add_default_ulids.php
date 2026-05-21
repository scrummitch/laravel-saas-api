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
        DB::table('catalog_inclusions')
            ->orderBy('id')
            ->chunk(100, function ($inclusions) {
                foreach ($inclusions as $inclusion) {
                    DB::table('catalog_inclusions')
                        ->where('id', $inclusion->id)
                        ->update(['ulid' => Str::ulid()->toString()]);
                }
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
